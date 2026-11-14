<?php
/**
 * Тема сайту Raki Niko.
 *
 * inc/settings.php — сторінка «Сайт» в адмінці: контакти, тексти, колір, коди аналітики;
 * inc/content.php — телефони, години, юридичні тексти з цих налаштувань;
 * inc/data.php    — каталог, акції й новини з плагіна raky-shop, форматування цін;
 * inc/routes.php  — адреси сторінок і вибір шаблону;
 * inc/seo.php     — заголовки, опис, розмітка для Google, карта сайту;
 * inc/assets.php  — підключення зібраних стилів і скриптів (dist/);
 * templates/      — розмітка сторінок.
 */

defined('ABSPATH') || exit;

// Без плагіна магазину сайт не має даних: показуємо просту заглушку замість фатальної помилки.
if (! function_exists('raky_rest_catalog')) {
	add_action('template_redirect', static function () {
		if (! is_admin()) {
			wp_die('Сайт оновлюється. Зателефонуйте нам: +380 93 647 39 02.', 'Raki Niko', ['response' => 503]);
		}
	});
	return;
}

require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/settings.php';
require_once __DIR__ . '/inc/content.php';
require_once __DIR__ . '/inc/data.php';
require_once __DIR__ . '/inc/routes.php';
require_once __DIR__ . '/inc/seo.php';
require_once __DIR__ . '/inc/assets.php';

add_action('after_setup_theme', static function () {
	add_theme_support('title-tag');
	add_theme_support('html5', ['script', 'style']);
});

/** SVG-іконка з набору застосунку. */
function raky_site_icon(string $name, int $size = 20, string $class = ''): string {
	$icons  = raky_site_icon_paths();
	$filled = isset($icons['filled'][$name]);
	$paths  = $icons['filled'][$name] ?? $icons['outline'][$name] ?? [];
	$paint  = $filled
		? 'fill="currentColor"'
		: 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';
	$body = implode('', array_map(static fn (string $d) => '<path d="' . esc_attr($d) . '"/>', $paths));
	return sprintf(
		'<svg class="icon %s" width="%d" height="%d" viewBox="0 0 24 24" %s aria-hidden="true" focusable="false">%s</svg>',
		esc_attr($class),
		$size,
		$size,
		$paint,
		$body
	);
}

/** Шаблон частини сторінки з параметрами. */
function raky_site_part(string $name, array $args = []): void {
	get_template_part('templates/parts/' . $name, null, $args);
}
