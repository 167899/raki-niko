<?php
/**
 * Перше налаштування бойового сайту. Запускає deploy.sh --setup через `wp eval-file`.
 *
 * На відміну від tools/seed-demo.php тут немає демо-товарів, купонів, банерів і тестового менеджера.
 * Скрипт можна запускати повторно: налаштування застосунку й категорії, які вже є, він не перезаписує.
 */
defined('ABSPATH') || exit;

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
// Клієнти замовляють у застосунку, реєстрація на сайті не потрібна.
update_option('users_can_register', 0);

// Точки й зони — стартові значення. Реквізити власник уписує в адмінці.
if (! get_option('raky_settings')) {
	update_option('raky_settings', [
		'requisites'    => '',
		'support_phone' => '+380936473902',
		'referral_on'   => false,
		'referral_pct'  => 5,
		'outlets'       => [
			['id' => 'center', 'name' => 'Рюміна 15', 'address' => 'Миколаїв, вул. Рюміна, 15', 'phone' => '+380936473902', 'lat' => 46.96842, 'lng' => 31.97566, 'hours' => 'Щодня 10:00–21:00'],
			['id' => 'north', 'name' => 'Спаська 47/3', 'address' => 'Миколаїв, вул. Спаська, 47/3', 'phone' => '+380936473902', 'lat' => 47.0120, 'lng' => 32.0080, 'hours' => 'Щодня 10:00–21:00'],
		],
		'zones'         => json_decode((string) file_get_contents(WP_PLUGIN_DIR . '/raky-shop/tools/delivery-zones.json'), true),
	]);
	echo "Налаштування застосунку записано.\n";
}

raky_name_default_category();
foreach (['Варені' => ['vareni', 1], 'Живі' => ['zhyvi', 2], 'Соуси' => ['sousy', 3], 'Закуски' => ['zakusky', 4], 'Напої' => ['napoi', 5], 'Суші' => ['sushi', 6]] as $name => [$slug, $order]) {
	if (! term_exists($slug, 'product_cat')) {
		$term = wp_insert_term($name, 'product_cat', ['slug' => $slug]);
		update_term_meta((int) $term['term_id'], 'order', $order);
	}
}

echo "Готово.\n";
