<?php
/**
 * Дані сторінок. Беремо їх тими самими функціями плагіна raky-shop, що відповідають застосунку,
 * тому сайт показує ті самі товари, ціни, калібри, смаки й «Додатково».
 */
defined('ABSPATH') || exit;

function raky_site_catalog(): array {
	static $catalog = null;
	return $catalog ??= raky_rest_catalog();
}

function raky_site_config(): array {
	static $config = null;
	return $config ??= raky_rest_config();
}

function raky_site_banners(): array {
	static $banners = null;
	return $banners ??= raky_banners_for('site');
}

function raky_site_news(): array {
	static $news = null;
	return $news ??= raky_rest_news();
}

function raky_site_product(int $id): ?array {
	foreach (raky_site_catalog()['products'] as $product) {
		if ($product['id'] === $id) {
			return $product;
		}
	}
	return null;
}

function raky_site_category(int $id): ?array {
	foreach (raky_site_catalog()['categories'] as $category) {
		if ($category['id'] === $id) {
			return $category;
		}
	}
	return null;
}

/** Товари в наявності: інші, як і в застосунку, у списках не показуються. */
function raky_site_products_in(?int $category_id = null): array {
	return array_values(array_filter(
		raky_site_catalog()['products'],
		static fn (array $product) => $product['in_stock'] && ($category_id === null || $product['category_id'] === $category_id)
	));
}

/**
 * Картки списку: одна на підкатегорію (калібри), веде на найдешевший калібр.
 *
 * @return array<int, array{product: array, title: string, from: bool, members: array}>
 */
function raky_site_cards(array $products): array {
	$groups = [];
	foreach ($products as $product) {
		$key              = $product['group'] !== '' ? 'g:' . $product['group'] : 'p:' . $product['id'];
		$groups[$key][]   = $product;
	}
	$cards = [];
	foreach ($groups as $members) {
		$cheapest = array_reduce($members, static fn (?array $best, array $item) => $best === null || $item['price'] < $best['price'] ? $item : $best);
		$cards[]  = [
			'product' => $cheapest,
			'title'   => $cheapest['group'] !== '' ? $cheapest['group'] : $cheapest['name'],
			'from'    => count($members) > 1,
			'members' => $members,
		];
	}
	return $cards;
}

/** Калібри товару: товари тієї ж підкатегорії в наявності, від дешевшого. */
function raky_site_calibers(array $product): array {
	if ($product['group'] === '') {
		return [];
	}
	$items = array_values(array_filter(
		raky_site_catalog()['products'],
		static fn (array $item) => $item['group'] === $product['group'] && $item['category_id'] === $product['category_id'] && $item['in_stock']
	));
	usort($items, static fn (array $a, array $b) => $a['price'] <=> $b['price']);
	return $items;
}

function raky_site_product_url(int $id): string {
	return (string) get_permalink($id);
}

function raky_site_category_url(int $id): string {
	$link = get_term_link($id, 'product_cat');
	return is_wp_error($link) ? home_url('/') : $link;
}

/** Текст для швидкого пошуку картки: назви всіх калібрів, підкатегорія й категорія. */
function raky_site_card_search(array $card): string {
	$category = raky_site_category($card['product']['category_id']);
	$words    = array_map(static fn (array $item) => $item['name'], $card['members']);
	$words[]  = $card['product']['group'];
	$words[]  = $category['name'] ?? '';
	return implode(' ', array_filter($words));
}

/* Форматування — як у mobile/src/format.ts. */

function raky_site_number(float $value): string {
	$rounded = round($value, 2);
	$whole   = number_format(floor(abs($rounded)), 0, '', ' ');
	$sign    = $rounded < 0 ? '−' : '';
	$cents   = (int) round((abs($rounded) - floor(abs($rounded))) * 100);
	if (! $cents) {
		return $sign . $whole;
	}
	return $sign . $whole . ',' . rtrim(sprintf('%02d', $cents), '0');
}

function raky_site_money(float $value): string {
	return raky_site_number($value) . ' ₴';
}

function raky_site_unit_name(string $unit): string {
	return raky_units()[$unit] ?? 'шт';
}

function raky_site_unit_price(float $price, string $unit): string {
	return raky_site_number($price) . ' ₴ / ' . raky_site_unit_name($unit);
}

/** «300 г» для трьох по 100 г, «2 порц.» для порцій. */
function raky_site_quantity(float $value, string $unit): string {
	if ($unit === '100g') {
		return raky_site_number($value * 100) . ' г';
	}
	if ($unit === 'por') {
		return raky_site_number($value) . ' порц.';
	}
	return raky_site_number($value) . ' ' . raky_site_unit_name($unit);
}

function raky_site_pretty_phone(string $phone): string {
	return (string) preg_replace('/^(\+380)(\d\d)(\d{3})(\d\d)(\d\d)$/', '$1 $2 $3 $4 $5', $phone);
}

function raky_site_capitalize(string $text): string {
	return mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
}

/** Дата новини: «9 листопада 2026». */
function raky_site_date(string $iso): string {
	$months = ['січня', 'лютого', 'березня', 'квітня', 'травня', 'червня', 'липня', 'серпня', 'вересня', 'жовтня', 'листопада', 'грудня'];
	$time   = strtotime($iso);
	if (! $time) {
		return '';
	}
	return wp_date('j', $time) . ' ' . $months[(int) wp_date('n', $time) - 1] . ' ' . wp_date('Y', $time);
}
