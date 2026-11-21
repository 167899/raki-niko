<?php
defined('ABSPATH') || exit;

/** Одиниці застосунку: [код => підпис]. Кількість у кошику рахується в цих одиницях. */
function raky_units(): array {
	return ['g' => 'г', 'kg' => 'кг', '100g' => '100 г', 'l' => 'л', 'ml' => 'мл', 'por' => 'порція', 'pcs' => 'шт'];
}

function raky_product_unit(WC_Product $product): string {
	$unit = (string) $product->get_meta(RAKY_META_UNIT);
	return isset(raky_units()[$unit]) ? $unit : 'pcs';
}

/** Знятий з продажу товар лишається в адмінці, але застосунок його не показує й не продає. */
function raky_product_hidden(WC_Product $product): bool {
	return $product->get_meta(RAKY_META_HIDDEN) === 'yes';
}

/** Вагові одиниці: точну вагу й суму менеджер підтверджує перед оплатою. */
function raky_unit_weighed(string $unit): bool {
	return in_array($unit, ['g', 'kg', '100g'], true);
}

function raky_product_step(WC_Product $product): float {
	$step = (float) $product->get_meta(RAKY_META_STEP);
	return $step > 0 ? $step : 1.0;
}

function raky_product_min(WC_Product $product): float {
	$min = (float) $product->get_meta(RAKY_META_MIN);
	return $min > 0 ? $min : raky_product_step($product);
}

/** Системний розділ для товарів без категорії. Клієнти його не бачать. */
function raky_default_category(): int {
	return (int) get_option('default_product_cat');
}

function raky_name_default_category(): void {
	$id = raky_default_category();
	if ($id) {
		wp_update_term($id, 'product_cat', ['name' => 'Без категорії', 'slug' => 'bez-katehorii']);
	}
}

/**
 * Перед видаленням категорії підкатегорії переходять у «Без категорії» разом зі своїми товарами,
 * а товари, що лишилися б без жодної категорії, туди ж. Працює і для сторінки «Категорії».
 */
add_action('pre_delete_term', static function (int $term_id, string $taxonomy) {
	$default = raky_default_category();
	if ($taxonomy !== 'product_cat' || ! $default || $term_id === $default) {
		return;
	}
	foreach (get_terms(['taxonomy' => 'product_cat', 'parent' => $term_id, 'hide_empty' => false, 'fields' => 'ids']) as $child) {
		wp_update_term($child, 'product_cat', ['parent' => $default]);
	}
	foreach (get_objects_in_term($term_id, 'product_cat') as $product_id) {
		$others = array_diff(wp_get_object_terms($product_id, 'product_cat', ['fields' => 'ids']), [$term_id]);
		if (! $others) {
			wp_set_object_terms((int) $product_id, [$default], 'product_cat');
		}
	}
}, 10, 2);

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
 * «Додатково» до товару: рукавички, пакети, набори. Клієнт обирає кількість кожного, ціна — за штуку.
 *
 * @return array<int, array{name: string, price: float}>
 */
function raky_product_addons(WC_Product $product): array {
	$stored = $product->get_meta(RAKY_META_ADDONS); // без поля — порожній рядок
	return array_values(array_map(
		static fn (array $addon) => ['name' => (string) $addon['name'], 'price' => (float) $addon['price']],
		array_filter(is_array($stored) ? $stored : [], 'is_array')
	));
}

/**
 * Де товар стоїть у меню. Категорія верхнього рівня — розділ (вкладка зверху), найглибша категорія товару —
 * картка, що об'єднує калібри. Рівні між ними — підкатегорії-«папки»: у розділі це картка, а в ній — свої картки.
 * «Суші та роли» → «Каліфорнія» (папка) → «Каліфорнія тунець» (картка) → товари-калібри.
 *
 * @return array{0: int, 1: string, 2: int[], 3: int} [ID розділу, назва картки, ID папок від розділу вглиб, ID категорії картки]
 */
function raky_product_placement(WC_Product $product): array {
	$ids = $product->get_category_ids();
	foreach ($ids as $id) {
		$ancestors = get_ancestors($id, 'product_cat', 'taxonomy'); // від батька до верхньої категорії
		if ($ancestors) {
			$top = (int) array_pop($ancestors);
			return [$top, get_term($id, 'product_cat')->name, array_map('intval', array_reverse($ancestors)), (int) $id];
		}
	}
	return [(int) ($ids[0] ?? 0), '', [], 0];
}

/** Фото категорії («Мініатюра» в «Товари → Категорії») розміру картки або null. */
function raky_category_image(int $term_id): ?string {
	$attachment = $term_id ? (int) get_term_meta($term_id, 'thumbnail_id', true) : 0;
	$url        = $attachment ? wp_get_attachment_image_url($attachment, 'woocommerce_thumbnail') : false;
	return raky_public_url($url ?: null);
}

/** Порядок категорії з перетягування в адмінці (поле order WooCommerce). */
function raky_category_order(int $term_id): int {
	return (int) get_term_meta($term_id, 'order', true);
}

/**
 * Папки для меню: parent — розділ або інша папка, порядок — як у дереві категорій адмінки.
 *
 * @return array<int, array{id: int, category_id: int, parent: int, name: string, image: ?string}>
 */
function raky_subcategories(array $ids): array {
	$out = [];
	foreach ($ids as $id) {
		$term = get_term($id, 'product_cat');
		$path = array_reverse(array_merge([$id], get_ancestors($id, 'product_cat', 'taxonomy'))); // від верхньої категорії
		$out[] = [
			'id'          => $id,
			'category_id' => (int) $path[0],
			'parent'      => (int) $term->parent,
			'name'        => $term->name,
			// Фото картки-папки; без нього застосунок і сайт беруть фото найдешевшого товару всередині.
			'image'       => raky_category_image((int) $id),
			'sort'        => array_map('raky_category_order', $path),
		];
	}
	usort($out, static fn (array $a, array $b) => $a['sort'] <=> $b['sort']);
	return array_map(static fn (array $item) => array_diff_key($item, ['sort' => true]), $out);
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

	$items        = [];
	$term_ids     = [];
	$group_images = [];
	$sub_ids  = [];
	foreach ($products as $product) {
		if (raky_product_hidden($product)) {
			continue;
		}
		[$category_id, $group, $path, $group_id] = raky_product_placement($product);
		$group_images[$group_id] ??= raky_category_image($group_id);
		// Товари з «Без категорії» чекають на розподіл і клієнтам не показуються.
		if (! $category_id || $category_id === raky_default_category()) {
			continue;
		}
		$term_ids[$category_id] = true;
		foreach ($path as $folder) {
			$sub_ids[$folder] = true;
		}
		$image   = wp_get_attachment_image_url($product->get_image_id(), 'woocommerce_thumbnail');
		$large   = wp_get_attachment_image_url($product->get_image_id(), 'large');
		$items[] = [
			'id'          => $product->get_id(),
			'sku'         => $product->get_sku(),
			'name'        => $product->get_name(),
			'description' => wp_strip_all_tags($product->get_short_description()),
			'category_id' => $category_id,
			// Папки від розділу вглиб: [«Каліфорнія»]. Порожньо — картка стоїть прямо в розділі.
			'path'        => $path,
			'price'       => (float) $product->get_price(),
			'unit'        => raky_product_unit($product),
			'step'        => raky_product_step($product),
			'min'         => raky_product_min($product),
			'in_stock'    => $product->is_in_stock(),
			'image'       => raky_public_url($image ?: null),
			'image_large' => raky_public_url($large ?: null),
			// Товари однієї підкатегорії показуються в застосунку як калібри одного товару.
			'group'        => $group,
			// Фото категорії-картки з адмінки; без нього картка показує фото найдешевшого калібру.
			'group_image'  => $group_images[$group_id],
			'caliber'      => (string) $product->get_meta(RAKY_META_CALIBER),
			'caliber_hint' => (string) $product->get_meta(RAKY_META_CALIBER_HINT),
			'per_kg'       => (float) $product->get_meta(RAKY_META_PER_KG),
			'flavors'      => raky_product_flavors($product),
			'addons'       => raky_product_addons($product),
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

	return ['categories' => $categories, 'subcategories' => raky_subcategories(array_keys($sub_ids)), 'products' => $items];
}
