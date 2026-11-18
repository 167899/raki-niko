<?php
/**
 * Адреси сайту.
 * - Головна, категорії (/katalog/<категорія>/) і товари (/tovar/<товар>/) — штатні адреси WooCommerce,
 *   тож їх розуміють карта сайту WordPress і SEO-плагіни.
 * - Інші сторінки задані тут і не потребують сторінок в адмінці.
 * - Усе інше, що WordPress показав би сам (записи, автори, магазин WooCommerce), віддає 404 або веде на наші сторінки.
 */
defined('ABSPATH') || exit;

/** Сторінки сайту: адреса => [шаблон, заголовок, чи показувати пошуковикам]. */
function raky_site_pages(): array {
	return [
		'aktsii'       => ['promos', 'Акції', true],
		'novyny'       => ['news', 'Новини', true],
		'dostavka'     => ['delivery', 'Оплата і доставка', true],
		'kontakty'     => ['contacts', 'Контакти', true],
		'pro-kompaniiu' => ['about', 'Про компанію', true],
		'zaproshennia' => ['invite', 'Запросити друзів', true],
		// Поки тексти — макет, пошуковикам їх не показуємо.
		'pryvatnist'   => ['legal', 'Політика конфіденційності', RAKY_SITE_LEGAL_OK],
		'oferta'       => ['legal', 'Публічна оферта', RAKY_SITE_LEGAL_OK],
		'koshyk'       => ['cart', 'Кошик', false],
		'oformlennia'  => ['checkout', 'Оформлення замовлення', false],
		'zamovlennia'  => ['orders', 'Мої замовлення', false],
		'ulyublene'    => ['favorites', 'Улюблене', false],
		'vkhid'        => ['login', 'Вхід', false],
		'profil'       => ['profile', 'Профіль', false],
	];
}

/** Версія правил: при зміні списку сторінок правила перезаписуються самі. */
const RAKY_SITE_RULES = '3';

add_action('init', static function () {
	$slugs = implode('|', array_map('preg_quote', array_keys(raky_site_pages())));
	add_rewrite_rule('^(' . $slugs . ')/?$', 'index.php?raky_page=$matches[1]', 'top');
	if (get_option('raky_site_rules') !== RAKY_SITE_RULES) {
		flush_rewrite_rules(false);
		update_option('raky_site_rules', RAKY_SITE_RULES);
	}
});

add_action('after_switch_theme', static function () {
	delete_option('raky_site_rules');
});

add_filter('query_vars', static function (array $vars): array {
	$vars[] = 'raky_page';
	return $vars;
});

/** Адреса сторінки сайту. Без «красивих» адрес — через ?raky_page=, щоб сайт працював і так. */
function raky_site_url(string $slug = ''): string {
	if ($slug === '') {
		return home_url('/');
	}
	return get_option('permalink_structure') ? home_url('/' . $slug . '/') : add_query_arg('raky_page', $slug, home_url('/'));
}

/** Поточна сторінка: home, category, product, одна з raky_site_pages() або 404. */
function raky_site_current(): array {
	static $current = null;
	if ($current !== null) {
		return $current;
	}
	$page = (string) get_query_var('raky_page');
	if ($page !== '' && isset(raky_site_pages()[$page])) {
		return $current = ['type' => 'page', 'slug' => $page, 'template' => raky_site_pages()[$page][0]];
	}
	if (is_front_page() || is_home()) {
		return $current = ['type' => 'home', 'template' => 'home'];
	}
	if (is_tax('product_cat')) {
		$term     = get_queried_object();
		$category = $term instanceof WP_Term ? raky_site_category($term->term_id) : null;
		if ($category) {
			return $current = ['type' => 'category', 'template' => 'category', 'category' => $category];
		}
		// Підрозділ показується на сторінці свого розділу: ведемо туди, одразу до підзаголовка.
		if ($term instanceof WP_Term) {
			foreach (raky_site_catalog()['subcategories'] as $sub) {
				if ($sub['id'] === $term->term_id) {
					return $current = ['type' => 'redirect', 'to' => raky_site_category_url($sub['category_id']) . '#sub-' . $sub['id']];
				}
			}
		}
		// Найглибша підкатегорія — це калібри одного товару: ведемо на сам товар.
		if ($term instanceof WP_Term && $term->parent) {
			foreach (raky_site_cards(raky_site_products_in()) as $card) {
				if ($card['product']['group'] === $term->name) {
					return $current = ['type' => 'redirect', 'to' => raky_site_product_url($card['product']['id'])];
				}
			}
		}
	}
	if (is_singular('product')) {
		$product = raky_site_product((int) get_queried_object_id());
		if ($product) {
			return $current = ['type' => 'product', 'template' => 'product', 'product' => $product];
		}
	}
	if (function_exists('is_shop') && is_shop()) {
		return $current = ['type' => 'redirect', 'to' => home_url('/')];
	}
	// Сторінки WooCommerce: на сайті їх замінюють наші.
	foreach (['cart' => 'koshyk', 'checkout' => 'oformlennia', 'myaccount' => 'zamovlennia'] as $wc => $slug) {
		$id = function_exists('wc_get_page_id') ? wc_get_page_id($wc) : 0;
		if ($id > 0 && is_page($id)) {
			return $current = ['type' => 'redirect', 'to' => raky_site_url($slug)];
		}
	}
	return $current = ['type' => '404', 'template' => '404'];
}

add_action('template_redirect', static function () {
	if (is_admin() || is_feed() || is_robots() || wp_doing_ajax() || defined('REST_REQUEST')) {
		return;
	}
	// Карта сайту WordPress має власний обробник.
	if (get_query_var('sitemap') || get_query_var('sitemap-stylesheet')) {
		return;
	}
	$current = raky_site_current();
	if ($current['type'] === 'redirect') {
		wp_safe_redirect($current['to'], 301);
		exit;
	}
	if ($current['type'] === '404') {
		global $wp_query;
		$wp_query->set_404();
		status_header(404);
		nocache_headers();
	}
	// Ціни синхронізуються з каси кожні 10 хвилин: кеш сторінок хостингу не повинен тримати їх довше.
	if (in_array($current['type'], ['home', 'category', 'product'], true)) {
		do_action('litespeed_control_set_ttl', 300);
	}
}, 5);

add_filter('template_include', static function (string $template): string {
	if (get_query_var('sitemap') || get_query_var('sitemap-stylesheet')) {
		return $template;
	}
	$current = raky_site_current();
	$file    = __DIR__ . '/../templates/' . ($current['template'] ?? '404') . '.php';
	return is_file($file) ? $file : $template;
}, 99);

// Тема сама показує всі сторінки: шаблони WooCommerce і його стилі на сайті не потрібні.
add_filter('woocommerce_enqueue_styles', '__return_empty_array');
add_action('wp_enqueue_scripts', static function () {
	foreach (['wc-blocks-style', 'woocommerce-general', 'woocommerce-layout', 'woocommerce-smallscreen', 'wp-block-library', 'global-styles', 'classic-theme-styles'] as $style) {
		wp_dequeue_style($style);
	}
	foreach (['wc-cart-fragments', 'woocommerce', 'wc-add-to-cart', 'sourcebuster-js', 'wc-order-attribution', 'jquery'] as $script) {
		wp_dequeue_script($script);
	}
}, 100);
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');
remove_action('wp_head', 'wp_generator');
remove_action('wp_head', 'wlwmanifest_link');
remove_action('wp_head', 'rsd_link');
remove_action('wp_head', 'feed_links', 2);
remove_action('wp_head', 'feed_links_extra', 3);

// Адреси латиницею робить плагін raky-latin-slugs (працює й без цієї теми).
