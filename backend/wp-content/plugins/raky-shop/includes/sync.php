<?php
defined('ABSPATH') || exit;

/**
 * Застосовує ціни й наявність до товарів WooCommerce без обмежень на розмір зміни.
 * $items: [{product_id, price, active}], ціна вже складена з позицій каси й помножена на множник
 * (raky_sky_combine). Ціна 0 теж переноситься (рішення власника). Пропускається лише товар без числової ціни.
 */
function raky_apply_prices(array $items, bool $dry_run): array {
	$report = ['updated' => [], 'unchanged' => 0, 'rejected' => []];

	foreach ($items as $item) {
		$product = wc_get_product((int) $item['product_id']);
		if (! $product) {
			continue;
		}
		$name  = $product->get_name();
		$price = $item['price'];
		if (! is_numeric($price) || (float) $price < 0) {
			$report['rejected'][] = ['name' => $name, 'reason' => 'invalid_price', 'new' => $price];
			continue;
		}
		$old_price = (float) $product->get_regular_price();
		$new_price = round((float) $price, 2);
		$stock     = $item['active'] ? 'instock' : 'outofstock';
		if (abs($new_price - $old_price) < 0.005 && $product->get_stock_status() === $stock) {
			++$report['unchanged'];
			continue;
		}

		$report['updated'][] = ['name' => $name, 'old' => $old_price, 'new' => $new_price, 'stock' => $stock];
		if (! $dry_run) {
			$product->set_regular_price((string) $new_price);
			$product->set_stock_status($stock);
			$product->save();
		}
	}

	return $report;
}

/** Причина відхилення для адмінки. */
function raky_sync_reason(array $row): string {
	switch ($row['reason']) {
		case 'invalid_price':
			return 'у касі немає ціни';
		case 'missing':
			return empty($row['ids']) ? 'товар не знайдено в касі' : 'у касі немає товару № ' . implode(', ', $row['ids']);
	}
	return (string) $row['reason'];
}
