<?php
defined('ABSPATH') || exit;

/** Зміна ціни понад цю частку не застосовується автоматично: імовірна помилка в POS. */
const RAKY_SYNC_MAX_CHANGE = 0.5;

function raky_sync_permission(WP_REST_Request $request): bool {
	$secret = defined('RAKY_SYNC_SECRET') ? (string) RAKY_SYNC_SECRET : '';
	$given  = (string) $request->get_header('X-Raky-Sync-Secret');
	return $secret !== '' && hash_equals($secret, $given);
}

/**
 * Приймає [{sku, price, active}]. Товари зіставляються за артикулом,
 * нові товари не створюються: опис і фото ведуться в WooCommerce.
 * dry_run=true показує зміни, нічого не записуючи.
 */
function raky_rest_sync_prices(WP_REST_Request $request) {
	$items = $request->get_param('items');
	if (! is_array($items)) {
		return new WP_Error('raky_bad_payload', 'Очікується масив items.', ['status' => 400]);
	}
	$dry_run = (bool) $request->get_param('dry_run');
	$report  = ['updated' => [], 'unchanged' => 0, 'unknown_sku' => [], 'rejected' => []];

	foreach ($items as $item) {
		$sku   = sanitize_text_field((string) ($item['sku'] ?? ''));
		$price = $item['price'] ?? null;
		if ($sku === '') {
			continue;
		}
		$product_id = wc_get_product_id_by_sku($sku);
		if (! $product_id) {
			$report['unknown_sku'][] = $sku;
			continue;
		}
		if (! is_numeric($price) || (float) $price <= 0) {
			$report['rejected'][] = ['sku' => $sku, 'reason' => 'invalid_price', 'price' => $price];
			continue;
		}

		$product   = wc_get_product($product_id);
		$old_price = (float) $product->get_regular_price();
		$new_price = round((float) $price, 2);
		if ($old_price > 0 && abs($new_price - $old_price) / $old_price > RAKY_SYNC_MAX_CHANGE) {
			$report['rejected'][] = ['sku' => $sku, 'reason' => 'change_too_large', 'old' => $old_price, 'new' => $new_price];
			continue;
		}

		$stock = array_key_exists('active', $item) && ! $item['active'] ? 'outofstock' : 'instock';
		if (abs($new_price - $old_price) < 0.005 && $product->get_stock_status() === $stock) {
			++$report['unchanged'];
			continue;
		}

		$report['updated'][] = ['sku' => $sku, 'old' => $old_price, 'new' => $new_price, 'stock' => $stock];
		if (! $dry_run) {
			$product->set_regular_price((string) $new_price);
			$product->set_stock_status($stock);
			$product->save();
		}
	}

	if (! $dry_run) {
		update_option('raky_last_sync', ['at' => time(), 'report' => $report], false);
	}
	return $report;
}
