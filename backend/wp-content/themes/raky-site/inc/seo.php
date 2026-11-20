<?php
/**
 * SEO: заголовок і опис кожної сторінки, канонічна адреса, превʼю для соцмереж,
 * розмітка schema.org (магазин, товар, хлібні крихти) і карта сайту /wp-sitemap.xml.
 * Якщо пізніше поставити SEO-плагін (Yoast, Rank Math), він перекриє заголовки сам.
 */
defined('ABSPATH') || exit;

const RAKY_SITE_NAME    = 'Raki Niko';

/** Заголовок, опис, картинка й індексація поточної сторінки. */
function raky_site_meta(): array {
	static $meta = null;
	if ($meta !== null) {
		return $meta;
	}
	$current = raky_site_current();
	$meta    = [
		'title'       => RAKY_SITE_NAME . ' — ' . RAKY_SITE_TAGLINE,
		'description' => raky_site_opt('home_desc'),
		'image'       => get_theme_file_uri('assets/logo.png'),
		'canonical'   => home_url('/'),
		'index'       => true,
		'type'        => 'website',
	];
	switch ($current['type']) {
		case 'category':
			$category            = $current['category'];
			$folder              = $current['folder'] ?? null;
			$name                = $folder['name'] ?? $category['name'];
			$prices              = array_column(array_column(raky_site_cards_at($category['id'], $folder['id'] ?? 0), 'product'), 'price');
			$meta['title']       = $name . ' у Миколаєві — купити з доставкою | ' . RAKY_SITE_NAME;
			$meta['description'] = $name . ' в Raki Niko' . ($prices ? ' від ' . raky_site_money(min($prices)) : '') . '. Доставка по Миколаєву або самовивіз.';
			$meta['canonical']   = raky_site_category_url($folder['id'] ?? $category['id']);
			break;
		case 'product':
			$product             = $current['product'];
			$meta['title']       = $product['name'] . ' — ' . raky_site_unit_price($product['price'], $product['unit']) . ' | ' . RAKY_SITE_NAME;
			$meta['description'] = raky_site_product_description($product);
			$meta['image']     = $product['image_large'] ?: $product['image'] ?: $meta['image'];
			$meta['canonical'] = raky_site_product_url($product['id']);
			$meta['type']      = 'product';
			break;
		case 'page':
			[, $title, $index]   = raky_site_pages()[$current['slug']];
			$meta['title']       = $title . ' | ' . RAKY_SITE_NAME;
			$meta['canonical']   = raky_site_url($current['slug']);
			$meta['index']       = $index;
			$meta['description'] = [
				'aktsii'   => 'Акції й промокоди Raki Niko: знижки на раків у Миколаєві.',
				'novyny'   => 'Новини Raki Niko: свіжі надходження, графік роботи, нові страви.',
				'dostavka' => 'Доставка раків по Миколаєву: зони й вартість на карті, самовивіз, оплата за реквізитами чи готівкою.',
				'kontakty' => 'Телефони, адреси й години роботи Raki Niko в Миколаєві.',
				'pro-kompaniiu' => raky_site_short_text(raky_site_opt('about') . ' Телефони, адреси й години роботи магазину в Миколаєві.'),
				'pryvatnist' => 'Політика конфіденційності Raki Niko: які дані ми збираємо під час замовлення і як їх захищаємо.',
				'oferta'     => 'Публічна оферта Raki Niko: умови замовлення, оплати й доставки раків та морепродуктів у Миколаєві.',
			][$current['slug']] ?? $meta['description'];
			break;
		case '404':
			$meta['title'] = 'Сторінку не знайдено | ' . RAKY_SITE_NAME;
			$meta['index'] = false;
			break;
	}
	return $meta;
}

/** Текст до 160 знаків по межі слова — стільки Google показує під заголовком. */
function raky_site_short_text(string $text, int $limit = 160): string {
	$text = trim(preg_replace('/\s+/u', ' ', $text));
	if (mb_strlen($text) <= $limit) {
		return $text;
	}
	$cut = mb_substr($text, 0, $limit - 1);
	$space = mb_strrpos($cut, ' ');
	return rtrim($space ? mb_substr($cut, 0, $space) : $cut, ' ,.;:—-') . '…';
}

/**
 * Опис товару для Google: назва, ціна, категорія — у кожного товару свій.
 * Короткий опис додаємо, якщо в ньому є щось, крім одиниці ціни («Ціна за 1 кг» уже видно з ціни).
 */
function raky_site_product_description(array $product): string {
	$category = raky_site_category($product['category_id']);
	$about    = trim($product['description'], " .\t\n\r");
	$extra    = $about !== '' && ! preg_match('/^(ці[нв]а за|порція)\s*[\d\s]*(кг|г|шт|порцію)?$/iu', $about) ? ' ' . $about . '.' : '';
	return raky_site_short_text(
		$product['name'] . ' — ' . raky_site_unit_price($product['price'], $product['unit']) . '.' . $extra
		. ($category ? ' ' . $category['name'] . ' в Raki Niko:' : ' Raki Niko:') . ' доставка по Миколаєву або самовивіз.'
	);
}

add_filter('pre_get_document_title', static fn () => raky_site_meta()['title']);

// Канонічну адресу ставимо самі для всіх сторінок.
remove_action('wp_head', 'rel_canonical');
remove_action('wp_head', 'wp_shortlink_wp_head');

add_filter('wp_robots', static function (array $robots): array {
	if (! raky_site_meta()['index']) {
		return ['noindex' => true, 'follow' => true];
	}
	$robots['max-image-preview'] = 'large';
	return $robots;
});

add_action('wp_head', static function () {
	$meta = raky_site_meta();
	printf("<meta name=\"description\" content=\"%s\">\n", esc_attr($meta['description']));
	if ($meta['index']) {
		printf("<link rel=\"canonical\" href=\"%s\">\n", esc_url($meta['canonical']));
	}
	$og = [
		'og:site_name'   => RAKY_SITE_NAME,
		'og:locale'      => 'uk_UA',
		'og:type'        => $meta['type'],
		'og:title'       => $meta['title'],
		'og:description' => $meta['description'],
		'og:url'         => $meta['canonical'],
		'og:image'       => $meta['image'],
	];
	foreach ($og as $property => $content) {
		printf("<meta property=\"%s\" content=\"%s\">\n", esc_attr($property), esc_attr($content));
	}
	echo "<meta name=\"twitter:card\" content=\"summary_large_image\">\n";
	foreach (raky_site_schema() as $schema) {
		echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</script>\n";
	}
}, 1);

/** Розмітка schema.org для Google: магазин на головній, товар і хлібні крихти на сторінках товарів. */
function raky_site_schema(): array {
	$current = raky_site_current();
	$config  = raky_site_config();
	$out     = [];
	if ($current['type'] === 'home' || ($current['type'] === 'page' && $current['slug'] === 'kontakty')) {
		$out[] = [
			'@context'     => 'https://schema.org',
			'@type'        => 'Store',
			'name'         => RAKY_SITE_NAME,
			'url'          => home_url('/'),
			'logo'         => get_theme_file_uri('assets/logo.png'),
			'image'        => get_theme_file_uri('assets/logo.png'),
			'telephone'    => RAKY_SITE_PHONES[0],
			'priceRange'   => '₴₴',
			'openingHours' => 'Mo-Su ' . str_replace('–', '-', RAKY_SITE_HOURS),
			'address'      => ['@type' => 'PostalAddress', 'addressLocality' => RAKY_SITE_CITY, 'addressCountry' => 'UA'],
			'areaServed'   => RAKY_SITE_CITY,
			'sameAs'       => array_values(array_column(raky_site_socials(), 'url')),
			'department'   => array_map(static fn (array $outlet) => [
				'@type'     => 'Store',
				'name'      => $outlet['name'],
				'telephone' => $outlet['phone'] ?: RAKY_SITE_PHONES[0],
				'address'   => ['@type' => 'PostalAddress', 'streetAddress' => $outlet['address'] ?: $outlet['name'], 'addressLocality' => RAKY_SITE_CITY, 'addressCountry' => 'UA'],
				'geo'       => ['@type' => 'GeoCoordinates', 'latitude' => $outlet['lat'], 'longitude' => $outlet['lng']],
			], $config['outlets']),
		];
	}
	if ($current['type'] === 'product') {
		$product  = $current['product'];
		$category = raky_site_category($product['category_id']);
		$out[]    = [
			'@context'    => 'https://schema.org',
			'@type'       => 'Product',
			'name'        => $product['name'],
			'description' => $product['description'] ?: $product['name'],
			'image'       => array_values(array_filter([$product['image_large'], $product['image']])),
			'sku'         => $product['sku'] ?: (string) $product['id'],
			'category'    => $category['name'] ?? '',
			'brand'       => ['@type' => 'Brand', 'name' => RAKY_SITE_NAME],
			'offers'      => [
				'@type'              => 'Offer',
				'url'                => raky_site_product_url($product['id']),
				'priceCurrency'      => 'UAH',
				'price'              => $product['price'],
				'availability'       => $product['in_stock'] ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
				'itemCondition'      => 'https://schema.org/NewCondition',
				'priceSpecification' => [
					'@type'          => 'UnitPriceSpecification',
					'price'          => $product['price'],
					'priceCurrency'  => 'UAH',
					'unitText'       => raky_site_unit_name($product['unit']),
				],
			],
		];
	}
	$crumbs = raky_site_breadcrumbs();
	if (count($crumbs) > 1) {
		$out[] = [
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => array_map(static fn (array $crumb, int $index) => [
				'@type'    => 'ListItem',
				'position' => $index + 1,
				'name'     => $crumb[0],
				'item'     => $crumb[1],
			], $crumbs, array_keys($crumbs)),
		];
	}
	return $out;
}

/** Хлібні крихти: [[назва, адреса], …]. */
function raky_site_breadcrumbs(): array {
	$current = raky_site_current();
	$crumbs  = [['Головна', home_url('/')]];
	if ($current['type'] === 'category') {
		$crumbs[] = [$current['category']['name'], raky_site_category_url($current['category']['id'])];
		foreach (isset($current['folder']) ? raky_site_folder_chain($current['folder']['id']) : [] as $sub) {
			$crumbs[] = [$sub['name'], raky_site_category_url($sub['id'])];
		}
	}
	if ($current['type'] === 'product') {
		$category = raky_site_category($current['product']['category_id']);
		if ($category) {
			$crumbs[] = [$category['name'], raky_site_category_url($category['id'])];
		}
		foreach ($current['product']['path'] ?? [] as $folder) {
			$sub = raky_site_subcategory($folder);
			if ($sub) {
				$crumbs[] = [$sub['name'], raky_site_category_url($sub['id'])];
			}
		}
		$crumbs[] = [$current['product']['name'], raky_site_product_url($current['product']['id'])];
	}
	if ($current['type'] === 'page') {
		$crumbs[] = [raky_site_pages()[$current['slug']][1], raky_site_url($current['slug'])];
	}
	return $crumbs;
}

/* Карта сайту WordPress (/wp-sitemap.xml): лише товари й категорії магазину та наші сторінки. */

add_filter('wp_sitemaps_add_provider', static function ($provider, string $name) {
	return in_array($name, ['users'], true) ? false : $provider;
}, 10, 2);

add_filter('wp_sitemaps_post_types', static function (array $types): array {
	return array_intersect_key($types, ['product' => true]);
});

add_filter('wp_sitemaps_taxonomies', static function (array $taxonomies): array {
	return array_intersect_key($taxonomies, ['product_cat' => true]);
});

// Приховані товари й товари з «Без категорії» сайт не показує — і в карті їх не треба.
add_filter('wp_sitemaps_posts_query_args', static function (array $args, string $type): array {
	if ($type === 'product') {
		$args['post__in'] = array_merge([0], array_column(raky_site_catalog()['products'], 'id'));
	}
	return $args;
}, 10, 2);

add_filter('wp_sitemaps_taxonomies_query_args', static function (array $args, string $taxonomy): array {
	if ($taxonomy === 'product_cat') {
		// Розділи й вкладені підкатегорії-папки («Суші» → «Каліфорнія»): у кожної своя сторінка.
		$catalog         = raky_site_catalog();
		$args['include'] = array_merge([0], array_column($catalog['categories'], 'id'), array_column($catalog['subcategories'] ?? [], 'id'));
		// Товари лежать у підкатегоріях (калібри), тож лічильник основної категорії буває нульовим.
		$args['hide_empty'] = false;
	}
	return $args;
}, 10, 2);

/** Наші сторінки в карті сайту: головна, акції, новини, доставка, контакти. */
add_action('init', static function () {
	if (! function_exists('wp_register_sitemap_provider')) {
		return;
	}
	wp_register_sitemap_provider('raky', new class() extends WP_Sitemaps_Provider {
		public function __construct() {
			$this->name        = 'raky';
			$this->object_type = 'raky';
		}

		public function get_url_list($page_num, $object_subtype = '') {
			$urls = [['loc' => home_url('/')]];
			foreach (raky_site_pages() as $slug => [, , $index]) {
				if ($index) {
					$urls[] = ['loc' => raky_site_url($slug)];
				}
			}
			return $urls;
		}

		public function get_max_num_pages($object_subtype = '') {
			return 1;
		}
	});
});
