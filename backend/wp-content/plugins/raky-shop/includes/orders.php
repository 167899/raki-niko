<?php
defined('ABSPATH') || exit;

const RAKY_ORDER_RATE_LIMIT  = 10;
const RAKY_ORDER_RATE_WINDOW = 600;
const RAKY_QUOTE_RATE_LIMIT  = 60;

function raky_error(string $code, string $message, int $status = 400, array $extra = []): WP_Error {
	return new WP_Error($code, $message, array_merge(['status' => $status], $extra));
}

function raky_rate_limited(string $bucket = 'order', int $limit = RAKY_ORDER_RATE_LIMIT): bool {
	$key   = 'raky_rl_' . md5($bucket . (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
	$count = (int) get_transient($key);
	if ($count >= $limit) {
		return true;
	}
	set_transient($key, $count + 1, RAKY_ORDER_RATE_WINDOW);
	return false;
}

/** Кількість має бути не меншою за мінімум і кратною кроку. */
function raky_quantity_valid(float $quantity, WC_Product $product): bool {
	if ($quantity < raky_product_min($product) - 1e-6) {
		return false;
	}
	$ratio = $quantity / raky_product_step($product);
	return abs($ratio - round($ratio)) < 1e-6;
}

function raky_clean_phone(string $phone): string {
	return (string) preg_replace('/[^\d+]/', '', $phone);
}

function raky_lines_subtotal(array $lines): float {
	$subtotal = 0.0;
	foreach ($lines as $line) {
		$subtotal += $line['unit_price'] * $line['quantity'];
	}
	return $subtotal;
}

/** @return array|WP_Error Рядки [product, quantity] */
function raky_resolve_lines($raw_items) {
	if (! is_array($raw_items) || ! $raw_items) {
		return raky_error('raky_empty_cart', 'Кошик порожній.');
	}
	$lines = [];
	foreach ($raw_items as $item) {
		$product  = wc_get_product(absint($item['product_id'] ?? 0));
		$quantity = (float) ($item['quantity'] ?? 0);
		if (! $product || $product->get_status() !== 'publish') {
			return raky_error('raky_unknown_product', 'Товар більше не доступний. Оновіть кошик.', 409);
		}
		if (! $product->is_in_stock()) {
			return raky_error('raky_out_of_stock', sprintf('«%s» зараз немає в наявності.', $product->get_name()), 409);
		}
		if (! raky_quantity_valid($quantity, $product)) {
			return raky_error('raky_bad_quantity', sprintf('Невірна кількість для «%s».', $product->get_name()));
		}
		$flavors = raky_product_flavors($product);
		$name    = sanitize_text_field((string) ($item['flavor'] ?? ''));
		$chosen  = current(array_filter($flavors, static fn (array $flavor) => $flavor['name'] === $name)) ?: null;
		if ($flavors && ! $chosen) {
			return raky_error('raky_bad_flavor', sprintf('Оберіть смак для «%s».', $product->get_name()));
		}
		$extra   = $chosen ? $chosen['extra'] : 0.0;
		$lines[] = [
			'product'    => $product,
			'quantity'   => $quantity,
			'flavor'     => $chosen ? $chosen['name'] : '',
			'extra'      => $extra,
			'unit_price' => (float) $product->get_price() + $extra,
		];
	}
	return $lines;
}

/**
 * Час приготування від клієнта: «Y-m-d H:i» у часовому поясі магазину.
 * Порожнє значення означає «якнайшвидше».
 *
 * @return string|WP_Error
 */
function raky_parse_ready_at(string $value) {
	if ($value === '') {
		return '';
	}
	$date = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $value, wp_timezone());
	if (! $date || $date->format('Y-m-d H:i') !== $value) {
		return raky_error('raky_bad_ready_at', 'Оберіть дату й час.');
	}
	if ($date < new DateTimeImmutable('now', wp_timezone())) {
		return raky_error('raky_bad_ready_at', 'Цей час уже минув. Оберіть інший.', 422);
	}
	return $value;
}

function raky_rest_create_order(WP_REST_Request $request) {
	if (raky_rate_limited()) {
		return raky_error('raky_rate_limited', 'Забагато спроб. Спробуйте за кілька хвилин.', 429);
	}

	$name    = sanitize_text_field((string) $request->get_param('name'));
	$phone   = raky_clean_phone((string) $request->get_param('phone'));
	$email   = sanitize_email((string) $request->get_param('email'));
	$address = sanitize_text_field((string) $request->get_param('address'));
	$comment = sanitize_textarea_field((string) $request->get_param('comment'));
	$promo   = sanitize_text_field((string) $request->get_param('promo'));
	$pickup  = $request->get_param('delivery_type') === 'pickup';
	$cash    = $request->get_param('payment_method') === 'cash';
	$ready   = raky_parse_ready_at((string) $request->get_param('ready_at'));
	if (is_wp_error($ready)) {
		return $ready;
	}

	if ($name === '') {
		return raky_error('raky_bad_name', "Вкажіть ім'я.");
	}
	if (! preg_match('/^(\+?38)?0\d{9}$/', $phone)) {
		return raky_error('raky_bad_phone', 'Вкажіть український номер телефону.');
	}

	$zone   = null;
	$outlet = null;
	if ($pickup) {
		$outlet_id = (string) $request->get_param('outlet_id');
		foreach (raky_get_settings()['outlets'] as $candidate) {
			if ($candidate['id'] === $outlet_id) {
				$outlet = $candidate;
			}
		}
		if (! $outlet) {
			return raky_error('raky_bad_outlet', 'Оберіть точку самовивозу.');
		}
		$address = $outlet['name'];
	} else {
		if ($address === '') {
			return raky_error('raky_bad_address', 'Вкажіть адресу доставки.');
		}
		// Зону визначає сервер. Якщо адресу не знайдено точно, вартість уписує менеджер.
		$zone = raky_zone_for_address($address);
	}

	$lines = raky_resolve_lines($request->get_param('items'));
	if (is_wp_error($lines)) {
		return $lines;
	}

	$subtotal = raky_lines_subtotal($lines);
	$fee      = $zone ? (float) $zone['fee'] : 0.0;
	if ($zone && $subtotal < (float) $zone['min_order']) {
		return raky_error('raky_min_order', sprintf('Мінімальне замовлення для цієї зони — %s грн.', (float) $zone['min_order']), 422);
	}

	$discount = 0.0;
	if ($promo !== '') {
		$discount = raky_coupon_discount($promo, $subtotal, $phone);
		if (is_wp_error($discount)) {
			return $discount;
		}
	}
	$total = round($subtotal - $discount + $fee, 2);

	// Ціни синхронізуються з POS, тому клієнт має підтвердити суму, яку бачить.
	$expected = $request->get_param('expected_total');
	if (! is_numeric($expected) || abs((float) $expected - $total) >= 0.01) {
		return raky_error('raky_price_changed', 'Ціни змінилися. Перевірте кошик.', 409, ['total' => $total]);
	}

	$order = wc_create_order(['status' => 'raky-new', 'created_via' => 'mobile-app']);
	if (is_wp_error($order)) {
		return raky_error('raky_order_failed', 'Не вдалося створити замовлення.', 500);
	}

	foreach ($lines as $line) {
		$line_total = $line['unit_price'] * $line['quantity'];
		$item_id    = $order->add_product($line['product'], $line['quantity'], ['subtotal' => $line_total, 'total' => $line_total]);
		if ($line['flavor'] !== '') {
			wc_add_order_item_meta($item_id, 'Смак', $line['flavor']);
		}
		if ($line['extra'] > 0) {
			wc_add_order_item_meta($item_id, 'Доплата за смак, грн за одиницю', $line['extra']);
		}
	}

	if (! $pickup) {
		// Рядок створюємо завжди, щоб менеджеру лишалося вписати суму.
		$shipping = new WC_Order_Item_Shipping();
		$shipping->set_method_title($zone ? 'Доставка: ' . $zone['name'] : 'Доставка (вартість уточнює менеджер)');
		$shipping->set_method_id('raky_zone');
		$shipping->set_total($fee);
		$order->add_item($shipping);
	}

	$order->set_billing_first_name($name);
	$order->set_billing_phone($phone);
	$order->set_billing_email($email);
	$order->set_billing_address_1($address);
	$order->set_billing_city('Миколаїв');
	$order->set_billing_country('UA');
	$order->set_shipping_first_name($name);
	$order->set_shipping_address_1($address);
	$order->set_shipping_city('Миколаїв');
	$order->set_shipping_country('UA');
	$order->set_payment_method($cash ? 'cod' : 'bacs');
	$order->set_payment_method_title($cash ? 'Готівкою при отриманні' : 'Оплата за реквізитами');
	$order->set_customer_note($comment);
	$order->update_meta_data('_raky_ready_at', $ready);
	$order->update_meta_data('_raky_delivery_type', $pickup ? 'pickup' : 'delivery');
	$order->update_meta_data('_raky_outlet_id', $pickup ? $outlet['id'] : ($zone['outlet_id'] ?? ''));
	if ($zone) {
		$order->update_meta_data('_raky_zone_id', $zone['id']);
	}
	$order->calculate_totals(false);
	$order->save();

	if ($promo !== '') {
		$applied = $order->apply_coupon($promo);
		if (is_wp_error($applied)) {
			$order->delete(true);
			return raky_error('raky_bad_promo', $applied->get_error_message(), 422);
		}
		$order->save();
	}

	return new WP_REST_Response(raky_order_payload($order), 201);
}

function raky_order_payload(WC_Order $order): array {
	$status   = raky_public_status($order->get_status());
	$weighted = false;
	$items    = [];
	foreach ($order->get_items() as $item) {
		$product = $item->get_product();
		$unit    = $product ? raky_product_unit($product) : 'pcs';
		$weighted = $weighted || $unit === 'kg';
		$items[] = [
			'name'     => $item->get_name(),
			'quantity' => (float) $item->get_quantity(),
			'unit'     => $unit,
			'flavor'   => (string) $item->get_meta('Смак'),
			'total'    => (float) $item->get_total(),
		];
	}

	$cash             = $order->get_payment_method() === 'cod';
	$awaiting_payment = $order->get_status() === 'raky-awaitpay';
	return [
		'id'           => $order->get_id(),
		'key'          => $order->get_order_key(),
		'status'       => $status,
		'created_at'   => $order->get_date_created() ? gmdate('c', $order->get_date_created()->getTimestamp()) : null,
		'items'        => $items,
		'shipping'     => (float) $order->get_shipping_total(),
		'discount'     => (float) $order->get_discount_total(),
		'delivery_type' => (string) $order->get_meta('_raky_delivery_type'),
		'total'        => (float) $order->get_total(),
		'has_weighted' => $weighted,
		'address'      => $order->get_billing_address_1(),
		'payment_method' => $cash ? 'cash' : 'requisites',
		'ready_at'     => (string) $order->get_meta('_raky_ready_at'),
		'comment'      => $order->get_customer_note(),
		// Реквізити видаємо лише після підтвердження менеджером.
		'requisites'   => $awaiting_payment && ! $cash ? raky_get_settings()['requisites'] : null,
	];
}

function raky_rest_get_order(WP_REST_Request $request) {
	$order = wc_get_order((int) $request['id']);
	$key   = (string) $request->get_param('key');
	if (! $order || $key === '' || ! hash_equals($order->get_order_key(), $key)) {
		return raky_error('raky_order_not_found', 'Замовлення не знайдено.', 404);
	}
	return raky_order_payload($order);
}

/** Раки продаються на вагу: прибираємо стандартне округлення кількості до цілого. */
add_action('plugins_loaded', static function () {
	remove_filter('woocommerce_stock_amount', 'intval');
	add_filter('woocommerce_stock_amount', 'floatval');
}, 20);
add_filter('woocommerce_quantity_input_step_admin', static fn () => 'any');
