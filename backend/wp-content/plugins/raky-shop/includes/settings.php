<?php
defined('ABSPATH') || exit;

/**
 * Налаштування магазину. Точки та зони зберігаються як JSON, щоб їх можна було
 * змінювати в адмінці без правок коду.
 *
 * outlets: [{id, name, address, phone, lat, lng, hours}]
 * zones:   [{id, name, outlet_id, fee, min_order, color, polygon: [[lat, lng], ...]}]
 */
function raky_get_settings(): array {
	$defaults = [
		'requisites'    => '',
		'support_phone' => '',
		'referral_on'   => false,
		'referral_pct'  => 5,
		'outlets'       => [],
		'zones'         => [],
	];
	return array_merge($defaults, (array) get_option('raky_settings', []));
}

function raky_rest_config(): array {
	$settings = raky_get_settings();
	return [
		'currency'      => get_woocommerce_currency(),
		'support_phone' => $settings['support_phone'],
		'referral'      => ['enabled' => (bool) $settings['referral_on'], 'percent' => (float) $settings['referral_pct']],
		'banners_on'    => raky_banners_on(),
		'outlets'       => array_values($settings['outlets']),
		'zones'         => array_values($settings['zones']),
	];
}
