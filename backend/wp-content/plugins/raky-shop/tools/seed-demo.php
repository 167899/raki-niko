<?php
/**
 * Демо-дані для локальної розробки. Адреси, ціни, зони й реквізити вигадані:
 * перед запуском їх треба замінити реальними.
 */
require_once '/wordpress/wp-load.php';

update_option('woocommerce_currency', 'UAH');
update_option('woocommerce_currency_pos', 'right_space');
update_option('woocommerce_price_decimal_sep', ',');
update_option('woocommerce_price_thousand_sep', ' ');
update_option('woocommerce_default_country', 'UA:mykolaiv');
update_option('woocommerce_calc_taxes', 'no');
update_option('woocommerce_coming_soon', 'no');
update_option('woocommerce_task_list_hidden', 'yes');
update_option('woocommerce_onboarding_profile', ['completed' => true]);
update_option('timezone_string', 'Europe/Kyiv');
update_option('blogname', 'Raki Niko');

update_option('raky_settings', [
	'requisites'    => "ДЕМО-РЕКВІЗИТИ, не використовувати\nОтримувач: ФОП Приклад\nIBAN: UA00 0000 0000 0000 0000 0000 000",
	'support_phone' => '+380936473902',
	'outlets'       => [
		['id' => 'center', 'name' => 'Рюміна 15', 'address' => 'Миколаїв, вул. Рюміна, 15', 'phone' => '+380936473902', 'lat' => 46.96842, 'lng' => 31.97566, 'hours' => 'Щодня 10:00–21:00'],
		['id' => 'north', 'name' => 'Спаська 47/3', 'address' => 'Миколаїв, вул. Спаська, 47/3', 'phone' => '+380936473902', 'lat' => 47.0120, 'lng' => 32.0080, 'hours' => 'Щодня 10:00–21:00'],
	],
	'zones'         => json_decode(file_get_contents(__DIR__ . '/delivery-zones.json'), true),
]);

foreach ([
	['RAKI10', 'percent', 10, 1, true, 'Перше замовлення'],
	['HERO15', 'percent', 15, 0, false, 'Для військових'],
] as [$code, $type, $amount, $per_person, $alone, $note]) {
	$coupon = new WC_Coupon($code);
	$coupon->set_code($code);
	$coupon->set_discount_type($type);
	$coupon->set_amount($amount);
	$coupon->set_usage_limit_per_user($per_person);
	$coupon->set_individual_use($alone);
	$coupon->set_description($note);
	$coupon->save();
}

foreach ([
	['−10% на перше замовлення', 'Акція', 'Лише до неділі', '#D92B20', 'RAKI10'],
	['Гострі раки з часником', 'Новинка', 'Спробуйте цього тижня', '#111111', ''],
] as $index => [$title, $label, $subtitle, $color, $code]) {
	$post_id = wp_insert_post(['post_type' => 'raky_banner', 'post_status' => 'publish', 'post_title' => $title, 'menu_order' => $index]);
	update_post_meta($post_id, '_raky_label', $label);
	update_post_meta($post_id, '_raky_subtitle', $subtitle);
	update_post_meta($post_id, '_raky_color', $color);
	update_post_meta($post_id, '_raky_coupon', $code);
}

wp_insert_post(['post_type' => 'raky_news', 'post_status' => 'publish', 'post_title' => 'Гострі раки вже в меню', 'post_excerpt' => 'Спробуйте новий смак цього тижня']);

raky_name_default_category();

$category_ids = [];
foreach (['Варені' => ['vareni', 1], 'Живі' => ['zhyvi', 2], 'Соуси' => ['sousy', 3], 'Закуски' => ['zakusky', 4], 'Напої' => ['napoi', 5], 'Суші' => ['sushi', 6]] as $name => [$slug, $order]) {
	$term = term_exists($slug, 'product_cat') ?: wp_insert_term($name, 'product_cat', ['slug' => $slug]);
	$category_ids[$slug] = (int) $term['term_id'];
	update_term_meta($category_ids[$slug], 'order', $order);
}

$cream_flavors  = [['name' => 'Звичайні', 'extra' => 0], ['name' => 'Гострі', 'extra' => 20], ['name' => 'З сиром Дор блю', 'extra' => 80]];
$boiled_flavors = [['name' => 'Класичні', 'extra' => 0], ['name' => 'Гострі', 'extra' => 0], ['name' => 'З часником', 'extra' => 50]];
$products = [
	['RK-BOIL-S', 'Раки варені, середні', 'Зі спеціями та кропом', 750, 'vareni', 'Раки варені', 'середні', '30–40 г', 28, $boiled_flavors],
	['RK-BOIL-L', 'Раки варені, великі', 'Зі спеціями та кропом', 950, 'vareni', 'Раки варені', 'великі', '40–60 г', 20, $boiled_flavors],
	['RK-CREAM-L', 'Раки вершкові, добірні', '', 1300, 'vareni', 'Раки вершкові', 'добірні', '70–90 г', 12, $cream_flavors, 'raki-vershkovi.jpg'],
	['RK-CREAM-M', 'Раки вершкові, середні', '', 1100, 'vareni', 'Раки вершкові', 'середні', '40–50 г', 23, $cream_flavors, 'raki-vershkovi.jpg'],
	['RK-LIVE-S', 'Раки живі, середні', 'Щойно з води', 650, 'zhyvi', '', 'середні', '30–40 г', 28, []],
	['RK-LIVE-L', 'Раки живі, великі', 'Щойно з води', 850, 'zhyvi', '', 'великі', '40–60 г', 20, []],
];
/** Підкатегорія об'єднує калібри одного товару. */
function raky_seed_subcategory(string $name, int $parent): int {
	$term = term_exists($name, 'product_cat', $parent) ?: wp_insert_term($name, 'product_cat', ['parent' => $parent]);
	return (int) $term['term_id'];
}

/** Завантажує фото з tools/seed-images у медіатеку. Один файл використовується лише раз. */
function raky_seed_image(string $file): int {
	static $ids = [];
	if (isset($ids[$file])) {
		return $ids[$file];
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$upload = wp_upload_bits($file, null, file_get_contents(__DIR__ . '/seed-images/' . $file));
	$id     = wp_insert_attachment(['post_mime_type' => 'image/jpeg', 'post_title' => $file, 'post_status' => 'inherit'], $upload['file']);
	wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $upload['file']));
	return $ids[$file] = $id;
}

foreach ($products as $index => $row) {
	[$sku, $name, $description, $price, $category, $group, $caliber, $hint, $per_kg, $flavors] = $row;
	$product = wc_get_product(wc_get_product_id_by_sku($sku)) ?: new WC_Product_Simple();
	$product->set_sku($sku);
	$product->set_name($name);
	$product->set_short_description($description);
	$product->set_regular_price((string) $price);
	// Без підкатегорії товар записується прямо в основну категорію.
	$product->set_category_ids([$group === '' ? $category_ids[$category] : raky_seed_subcategory($group, $category_ids[$category])]);
	$product->set_menu_order($index);
	$product->set_status('publish');
	if (isset($row[10])) {
		$product->set_image_id(raky_seed_image($row[10]));
	}
	$product->update_meta_data(RAKY_META_UNIT, 'kg');
	$product->update_meta_data(RAKY_META_STEP, 0.5);
	$product->update_meta_data(RAKY_META_MIN, 1);
	$product->update_meta_data(RAKY_META_CALIBER, $caliber);
	$product->update_meta_data(RAKY_META_CALIBER_HINT, $hint);
	$product->update_meta_data(RAKY_META_PER_KG, $per_kg);
	$product->update_meta_data(RAKY_META_FLAVORS, $flavors);
	$product->save();
}

// Локальний менеджер для перевірки підтвердження замовлень.
if (defined('RAKY_DEMO_MANAGER_PASSWORD') && ! username_exists('manager')) {
	wp_insert_user([
		'user_login' => 'manager',
		'user_pass'  => RAKY_DEMO_MANAGER_PASSWORD,
		'user_email' => 'manager@example.test',
		'role'       => 'shop_manager',
	]);
}

echo "seed ok\n";
