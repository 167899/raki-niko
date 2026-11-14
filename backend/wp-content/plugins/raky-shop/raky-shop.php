<?php
/**
 * Plugin Name: Raky Shop
 * Description: Мобільний API магазину: каталог, замовлення за підтвердженням менеджера, зони доставки, ціни зі Skyservice POS.
 * Version: 0.2.0
 * Requires Plugins: woocommerce
 */

defined('ABSPATH') || exit;

define('RAKY_NS', 'raky/v1');
define('RAKY_META_UNIT', '_raky_unit');   // ключ із raky_units()
define('RAKY_META_STEP', '_raky_step');   // крок кількості
define('RAKY_META_MIN', '_raky_min');     // мінімальне замовлення
define('RAKY_META_CALIBER', '_raky_caliber');       // «середні»
define('RAKY_META_CALIBER_HINT', '_raky_caliber_hint'); // «30–40 г»
define('RAKY_META_PER_KG', '_raky_per_kg');         // приблизно штук у кілограмі
define('RAKY_META_FLAVORS', '_raky_flavors');       // смаки: [[name, extra]], extra — доплата за одиницю товару
define('RAKY_META_HIDDEN', '_raky_hidden');         // «Зняти з продажу»: 'yes' — товар не видно в застосунку
define('RAKY_META_ADDONS', '_raky_addons');         // «Додатково»: [[name, price]], ціна за штуку (рукавички, пакет)

require_once __DIR__ . '/includes/l10n.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/zones.php';
require_once __DIR__ . '/includes/statuses.php';
require_once __DIR__ . '/includes/catalog.php';
require_once __DIR__ . '/includes/orders.php';
require_once __DIR__ . '/includes/promotions.php';
require_once __DIR__ . '/includes/customers.php';
require_once __DIR__ . '/includes/sync.php';
require_once __DIR__ . '/includes/skyservice.php';
require_once __DIR__ . '/includes/referral.php';
require_once __DIR__ . '/includes/telegram.php';
require_once __DIR__ . '/includes/admin.php';

register_activation_hook(__FILE__, 'raky_name_default_category');
register_deactivation_hook(__FILE__, static function () {
	wp_clear_scheduled_hook(RAKY_SKY_CRON);
	wp_clear_scheduled_hook(RAKY_TG_CRON);
	wp_clear_scheduled_hook(RAKY_TG_HEALTH);
});

add_action('rest_api_init', static function () {
	// Застосунок звертається напряму, а веб-збірка йде з іншого origin.
	remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
	add_filter('rest_pre_serve_request', static function ($value) {
		header('Access-Control-Allow-Origin: *');
		header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
		header('Access-Control-Allow-Headers: Content-Type');
		return $value;
	});
	// Кеш хостингу (LiteSpeed) тримав би відповіді застосунку тиждень: старі ціни, наявність і статуси замовлень.
	add_filter('rest_post_dispatch', static function ($response, $server, $request) {
		if (str_starts_with($request->get_route(), '/' . RAKY_NS)) {
			do_action('litespeed_control_set_nocache', 'Raki Niko API');
			$response->header('Cache-Control', 'no-store');
		}
		return $response;
	}, 10, 3);

	register_rest_route(RAKY_NS, '/config', [
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => 'raky_rest_config',
	]);
	register_rest_route(RAKY_NS, '/catalog', [
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => 'raky_rest_catalog',
	]);
	register_rest_route(RAKY_NS, '/delivery-check', [
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => 'raky_rest_delivery_check',
	]);
	register_rest_route(RAKY_NS, '/delivery-quote', [
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => 'raky_rest_delivery_quote',
	]);
	register_rest_route(RAKY_NS, '/orders', [
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'raky_rest_create_order',
	]);
	register_rest_route(RAKY_NS, '/orders/(?P<id>\d+)', [
		'methods'             => 'GET',
		'permission_callback' => '__return_true', // доступ перевіряється ключем замовлення
		'callback'            => 'raky_rest_get_order',
	]);
	register_rest_route(RAKY_NS, '/banners', [
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => 'raky_rest_banners',
	]);
	register_rest_route(RAKY_NS, '/news', [
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => 'raky_rest_news',
	]);
	register_rest_route(RAKY_NS, '/referral', [
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'raky_rest_referral',
	]);
	register_rest_route(RAKY_NS, '/promo-check', [
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'raky_rest_promo_check',
	]);
});
