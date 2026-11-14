<?php
/**
 * Plugin Name: Raki Niko — адреси латиницею
 * Description: Адреси нових товарів, категорій і сторінок пишуться латиницею («Раки живі» → raky-zhyvi). Якщо адресу категорії змінили, старе посилання переадресовується на нове.
 * Version: 1.0.0
 * Requires PHP: 7.4
 * Text Domain: raky-latin-slugs
 */
defined('ABSPATH') || exit;

/** Українська офіційна транслітерація (спрощена: без позиційних правил для є/ї/й/ю/я). */
function raky_latin(string $text): string
{
	static $map = [
		'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'h', 'ґ' => 'g', 'д' => 'd', 'е' => 'e', 'є' => 'ie', 'ж' => 'zh', 'з' => 'z',
		'и' => 'y', 'і' => 'i', 'ї' => 'i', 'й' => 'i', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p',
		'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch',
		'ь' => '', 'ю' => 'iu', 'я' => 'ia', 'ы' => 'y', 'э' => 'e', 'ё' => 'e', 'ъ' => '', "'" => '', '’' => '', 'ʼ' => '',
	];
	return strtr(mb_strtolower($text), $map);
}

// Під час збереження WordPress робить адресу з назви — підставляємо латиницю.
add_filter('sanitize_title', static function (string $title, string $raw, string $context): string {
	if ($context !== 'save' || ! preg_match('/[а-яіїєёґыэъ]/iu', $raw)) {
		return $title;
	}
	return sanitize_title_with_dashes(raky_latin($raw), '', 'save');
}, 9, 3);

// Назви завантажених файлів теж латиницею: так їх не ламають месенджери й реклама.
add_filter('sanitize_file_name', static function (string $name): string {
	return preg_match('/[а-яіїєёґыэъ]/iu', $name) ? sanitize_file_name(raky_latin($name)) : $name;
}, 9);

/*
 * Старі адреси категорій. Для товарів WordPress сам пам'ятає старі адреси (_wp_old_slug),
 * для категорій — ні, тому зберігаємо попередню адресу в метаданих категорії.
 */
const RAKY_OLD_SLUGS = '_raky_old_slugs';

function raky_remember_old_slug(int $term_id, string $old_slug): void
{
	$old  = urldecode($old_slug);
	$list = (array) get_term_meta($term_id, RAKY_OLD_SLUGS, true);
	if ($old !== '' && ! in_array($old, $list, true)) {
		$list[] = $old;
		update_term_meta($term_id, RAKY_OLD_SLUGS, array_values(array_filter($list)));
	}
}

add_action('edit_terms', static function (int $term_id, string $taxonomy): void {
	$term = get_term($term_id, $taxonomy);
	if ($term instanceof WP_Term) {
		$GLOBALS['raky_slug_before'][$term_id] = $term->slug;
	}
}, 10, 2);

add_action('edited_term', static function (int $term_id, int $tt_id, string $taxonomy): void {
	$before = $GLOBALS['raky_slug_before'][$term_id] ?? null;
	$term   = get_term($term_id, $taxonomy);
	if ($before !== null && $term instanceof WP_Term && $term->slug !== $before) {
		raky_remember_old_slug($term_id, $before);
	}
}, 10, 3);

add_action('template_redirect', static function (): void {
	if (! is_404()) {
		return;
	}
	// На 404 WordPress очищає змінні запиту, тому категорію беремо з самої адреси: katalog/<адреса>/.
	$path = trim(urldecode((string) ($GLOBALS['wp']->request ?? '')), '/');
	foreach (get_taxonomies(['public' => true], 'objects') as $taxonomy => $object) {
		$base = trim((string) ($object->rewrite['slug'] ?? ''), '/');
		if ($base === '' || strpos($path . '/', $base . '/') !== 0 || $path === $base) {
			continue;
		}
		$parts = explode('/', substr($path, strlen($base) + 1));
		$slug  = mb_strtolower((string) end($parts));
		$terms = get_terms([
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'number'     => 1,
			'meta_query' => [['key' => RAKY_OLD_SLUGS, 'value' => '"' . $slug . '"', 'compare' => 'LIKE']],
		]);
		if (! is_wp_error($terms) && $terms) {
			$url = get_term_link($terms[0]);
			if (is_string($url)) {
				wp_safe_redirect($url, 301, 'Raki Niko');
				exit;
			}
		}
	}
}, 1);
