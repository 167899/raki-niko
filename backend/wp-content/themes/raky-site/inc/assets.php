<?php
/**
 * Стилі й скрипти: збірка Vite у dist/ (npm run build). Без збірки сторінки відкриваються, але без стилів і кошика.
 */
defined('ABSPATH') || exit;

function raky_site_manifest(): array {
	static $manifest = null;
	if ($manifest === null) {
		$file     = get_theme_file_path('dist/.vite/manifest.json');
		$manifest = is_file($file) ? (array) json_decode((string) file_get_contents($file), true) : [];
	}
	return $manifest;
}

add_action('wp_enqueue_scripts', static function () {
	$entry = raky_site_manifest()['src/main.ts'] ?? null;
	if (! $entry) {
		return;
	}
	foreach ($entry['css'] ?? [] as $index => $css) {
		wp_enqueue_style('raky-site-' . $index, get_theme_file_uri('dist/' . $css), [], null);
	}
	wp_enqueue_script_module('raky-site', get_theme_file_uri('dist/' . $entry['file']), [], null);
});

// Дані для скриптів: адреса API, адреси сторінок і налаштування магазину.
add_action('wp_head', static function () {
	$config = raky_site_config();
	$boot   = [
		// Як у застосунку: на хостингу працює лише ?rest_route=.
		'api'      => home_url('/?rest_route=/raky/v1'),
		'page'     => raky_site_current()['type'] === 'page' ? raky_site_current()['slug'] : raky_site_current()['type'],
		'urls'     => [
			'home'     => home_url('/'),
			'cart'     => raky_site_url('koshyk'),
			'checkout' => raky_site_url('oformlennia'),
			'orders'   => raky_site_url('zamovlennia'),
			'invite'   => raky_site_url('zaproshennia'),
			'login'    => raky_site_url('vkhid'),
			'profile'  => raky_site_url('profil'),
		],
		// Новини для лічильника на дзвіночку: непрочитані рахує браузер.
		'news'     => array_map(static fn (array $item) => (int) $item['id'], raky_site_news()),
		// Адреси сторінок товарів: кошик і замовлення ведуть на товар.
		'links'    => (object) array_column(
			array_map(static fn (array $product) => ['id' => $product['id'], 'url' => raky_site_product_url($product['id'])], raky_site_catalog()['products']),
			'url',
			'id'
		),
		'referral' => $config['referral'],
		'phone'    => RAKY_SITE_PHONES[0],
		// Статистика відвідувань (плагін, includes/stats.php): коротка назва сторінки для таблиці «Популярні сторінки».
		'label'    => raky_site_current()['type'] === 'home' ? 'Головна' : (string) preg_replace('/( у Миколаєві — .*| — .*)?( \\| ' . preg_quote(RAKY_SITE_NAME, '/') . ')?$/u', '', raky_site_meta()['title']),
		// Перегляди працівників, які увійшли в адмінку, не рахуємо.
		'track'    => ! current_user_can('edit_posts'),
	];
	echo '<script>window.RAKY=' . wp_json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ";</script>\n";
}, 2);

add_action('wp_head', static function () {
	printf("<link rel=\"icon\" href=\"%s\" type=\"image/png\">\n", esc_url(get_theme_file_uri('assets/favicon.png')));
	printf("<link rel=\"apple-touch-icon\" href=\"%s\">\n", esc_url(get_theme_file_uri('assets/logo.png')));
	printf("<meta name=\"theme-color\" content=\"%s\">\n", esc_attr(raky_site_opt('accent')));
}, 3);
