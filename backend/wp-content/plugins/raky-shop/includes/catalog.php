<?php
defined('ABSPATH') || exit;

function raky_product_unit(WC_Product $product): string {
	return $product->get_meta(RAKY_META_UNIT) === 'kg' ? 'kg' : 'pcs';
}

function raky_product_step(WC_Product $product): float {
	$step = (float) $product->get_meta(RAKY_META_STEP);
	return $step > 0 ? $step : 1.0;
}

function raky_product_min(WC_Product $product): float {
	$min = (float) $product->get_meta(RAKY_META_MIN);
	return $min > 0 ? $min : raky_product_step($product);
}

/** Адреса зображення на тому хості, через який прийшов запит: телефон у локальній мережі не бачить 127.0.0.1. */
function raky_public_url(?string $url): ?string {
	$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
	if (! $url || ! preg_match('/^[\w.:\[\]-]+$/', $host)) {
		return $url ?: null;
	}
	$site = (string) wp_parse_url(home_url(), PHP_URL_HOST);
	$port = wp_parse_url(home_url(), PHP_URL_PORT);
	$from = $site . ($port ? ':' . $port : '');
	// Лише локальна розробка: на робочому сервері адреси вже правильні.
	return in_array($site, ['127.0.0.1', 'localhost'], true) ? str_replace('//' . $from, '//' . $host, $url) : $url;
}

/**
 * Смаки товару в порядку, заданому в адмінці. Доплата нараховується за кілограм або штуку.
 *
 * @return array<int, array{name: string, extra: float}>
 */
function raky_product_flavors(WC_Product $product): array {
	$stored = $product->get_meta(RAKY_META_FLAVORS);
	if (is_string($stored)) {
		// Старий формат: назви через кому, без доплати.
		$names  = array_filter(array_map('trim', explode(',', $stored)), static fn (string $name) => $name !== '');
		$stored = array_map(static fn (string $name) => ['name' => $name, 'extra' => 0], $names);
	}
	return array_values(array_map(
		static fn (array $flavor) => ['name' => (string) $flavor['name'], 'extra' => (float) $flavor['extra']],
		(array) $stored
	));
}

/**
 * Категорії без товарів не повертаються: "Суші" з'являться в застосунку
 * самі, щойно в категорії опублікують перший товар.
 */
function raky_rest_catalog(): array {
	$products = wc_get_products([
		'status'  => 'publish',
		'limit'   => -1,
		'orderby' => 'menu_order',
		'order'   => 'ASC',
	]);

	$items    = [];
	$term_ids = [];
	foreach ($products as $product) {
		$category_id = $product->get_category_ids()[0] ?? 0;
		if (! $category_id) {
			continue;
		}
		$term_ids[$category_id] = true;
		$image   = wp_get_attachment_image_url($product->get_image_id(), 'woocommerce_thumbnail');
		$large   = wp_get_attachment_image_url($product->get_image_id(), 'large');
		$items[] = [
			'id'          => $product->get_id(),
			'sku'         => $product->get_sku(),
			'name'        => $product->get_name(),
			'description' => wp_strip_all_tags($product->get_short_description()),
			'category_id' => $category_id,
			'price'       => (float) $product->get_price(),
			'unit'        => raky_product_unit($product),
			'step'        => raky_product_step($product),
			'min'         => raky_product_min($product),
			'in_stock'    => $product->is_in_stock(),
			'image'       => raky_public_url($image ?: null),
			'image_large' => raky_public_url($large ?: null),
			// Товари з однаковою групою показуються в застосунку як калібри одного товару.
			'group'        => (string) $product->get_meta(RAKY_META_GROUP),
			'caliber'      => (string) $product->get_meta(RAKY_META_CALIBER),
			'caliber_hint' => (string) $product->get_meta(RAKY_META_CALIBER_HINT),
			'per_kg'       => (float) $product->get_meta(RAKY_META_PER_KG),
			'flavors'      => raky_product_flavors($product),
		];
	}

	$categories = [];
	if ($term_ids) {
		$terms = get_terms([
			'taxonomy'   => 'product_cat',
			'include'    => array_keys($term_ids),
			'hide_empty' => false,
		]);
		// Сортуємо тут, а не через meta_key: той запит пропускає категорії без поля order.
		usort($terms, static fn (WP_Term $a, WP_Term $b) => (int) get_term_meta($a->term_id, 'order', true) <=> (int) get_term_meta($b->term_id, 'order', true));
		foreach ($terms as $term) {
			$categories[] = ['id' => $term->term_id, 'name' => $term->name, 'slug' => $term->slug];
		}
	}

	return ['categories' => $categories, 'products' => $items];
}
