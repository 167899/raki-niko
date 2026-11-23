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
			// Фото категорії з адмінки важливіше за фото товару.
			'image'   => ($cheapest['group'] !== '' ? ($cheapest['group_image'] ?? null) : null) ?: $cheapest['image'],
			'title'   => $cheapest['group'] !== '' ? $cheapest['group'] : $cheapest['name'],
			'from'    => count($members) > 1,
			'members' => $members,
		];
	}
	return $cards;
}

function raky_site_subcategory(int $id): ?array {
	foreach (raky_site_catalog()['subcategories'] as $sub) {
		if ($sub['id'] === $id) {
			return $sub;
		}
	}
	return null;
}

/** Папки від розділу до $id включно: для хлібних крихт. */
function raky_site_folder_chain(int $id): array {
	$chain = [];
	for ($sub = raky_site_subcategory($id); $sub; $sub = raky_site_subcategory($sub['parent'])) {
		array_unshift($chain, $sub);
	}
	return $chain;
}

/**
 * Картки розділу чи папки в порядку товарів: папка («Каліфорнія») — картка, що веде на сторінку з її картками,
 * найглибша категорія — картка калібрів, як і раніше.
 *
 * @return array<int, array{product: array, title: string, from: bool, members: array, folder?: array, url?: string}>
 */
function raky_site_cards_at(int $category_id, int $folder = 0): array {
	$entries = [];
	foreach (raky_site_products_in($category_id) as $product) {
		$path = $product['path'] ?? [];
		if ($folder) {
			$at = array_search($folder, $path, true);
			if ($at === false) {
				continue;
			}
			$path = array_slice($path, $at + 1);
		}
		$key             = $path ? 'f:' . $path[0] : ($product['group'] !== '' ? 'g:' . $product['group'] : 'p:' . $product['id']);
		$entries[$key][] = $product;
	}
	$cards = [];
	foreach ($entries as $key => $members) {
		$sub = str_starts_with($key, 'f:') ? raky_site_subcategory((int) substr($key, 2)) : null;
		if (! $sub) {
			$cards[] = raky_site_cards($members)[0];
			continue;
		}
		$cheapest = array_reduce($members, static fn (?array $best, array $item) => $best === null || $item['price'] < $best['price'] ? $item : $best);
		$cards[]  = [
			'product' => $cheapest,
			'image'   => ($sub['image'] ?? null) ?: $cheapest['image'],
			'title'   => $sub['name'],
			'from'    => count($members) > 1,
			'members' => $members,
			'folder'  => $sub,
			'url'     => raky_site_category_url($sub['id']),
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
	foreach ($card['product']['path'] ?? [] as $folder) {
		$words[] = raky_site_subcategory($folder)['name'] ?? '';
	}
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

/** Підпис кнопки «Додатково»: «Рукавички +10 ₴» або «Набір (1 шт. безкоштовно, далі +15 ₴)». Як addonLabel() у застосунку. */
function raky_site_addon_label(array $addon): string {
	$included = (int) ($addon['default'] ?? 0);
	$first    = $addon['default_price'] ?? null;
	if ($included > 0 && $first !== null && (float) $first !== (float) $addon['price']) {
		return sprintf('%s (%d шт. %s, далі +%s)', $addon['name'], $included, $first > 0 ? raky_site_money($first) : 'безкоштовно', raky_site_money($addon['price']));
	}
	return $addon['price'] > 0 ? $addon['name'] . ' +' . raky_site_money($addon['price']) : $addon['name'];
}
