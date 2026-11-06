<?php
defined('ABSPATH') || exit;

/**
 * Шлях замовлення:
 * raky-new (чекає менеджера) → raky-awaitpay (показуємо реквізити)
 * → processing (оплачено, готується) → raky-delivery → completed.
 * Оплата готівкою: raky-new → processing, крок raky-awaitpay не потрібен.
 * Слаги без префікса "wc-" не довші за 17 символів — вимога WooCommerce.
 */
function raky_statuses(): array {
	return [
		'wc-raky-new'      => 'Очікує підтвердження',
		'wc-raky-awaitpay' => 'Очікує оплату',
		'wc-raky-delivery' => 'Передано в доставку',
	];
}

add_action('init', static function () {
	foreach (raky_statuses() as $slug => $label) {
		register_post_status($slug, [
			'label'                     => $label,
			'public'                    => true,
			'show_in_admin_status_list' => true,
			'show_in_admin_all_list'    => true,
			'exclude_from_search'       => false,
			/* translators: %s: number of orders */
			'label_count'               => _n_noop($label . ' <span class="count">(%s)</span>', $label . ' <span class="count">(%s)</span>'),
		]);
	}
});

add_filter('wc_order_statuses', static function (array $statuses): array {
	return array_merge(raky_statuses(), $statuses);
});

/** Менеджер має змінювати вагу і суму, поки замовлення не оплачене. */
add_filter('wc_order_is_editable', static function (bool $editable, WC_Order $order): bool {
	return $editable || in_array($order->get_status(), ['raky-new', 'raky-awaitpay'], true);
}, 10, 2);

/** Текст статусу для клієнта. */
function raky_public_status(string $status): array {
	$map = [
		'raky-new'      => ['awaiting_confirmation', 'Менеджер перевіряє замовлення'],
		'raky-awaitpay' => ['awaiting_payment', 'Замовлення підтверджено, очікуємо оплату'],
		'processing'    => ['preparing', 'Оплату отримано, готуємо замовлення'],
		'raky-delivery' => ['delivering', 'Замовлення передано в доставку'],
		'completed'     => ['completed', 'Замовлення виконано'],
		'cancelled'     => ['cancelled', 'Замовлення скасовано'],
		'refunded'      => ['cancelled', 'Кошти повернено'],
		'failed'        => ['cancelled', 'Замовлення скасовано'],
	];
	[$code, $label] = $map[$status] ?? ['unknown', 'Статус уточнюється'];
	return ['code' => $code, 'label' => $label];
}
