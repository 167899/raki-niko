<?php
defined('ABSPATH') || exit;

/**
 * Адмінка лише українською. Офіційні переклади WordPress і WooCommerce ставляться при запуску,
 * а тут закриваються рядки, яких у цих перекладах ще немає.
 */

// WordPress 7.1 бере підказки форми категорій із підписів таксономії, а WooCommerce їх не задає.
foreach (['product_cat', 'product_tag', 'product_brand'] as $raky_taxonomy) {
	add_filter("taxonomy_labels_{$raky_taxonomy}", static function ($labels) {
		$labels->slug_field_description   = 'Слаг — назва для адреси сторінки. Зазвичай лише малі латинські літери, цифри й дефіси.';
		$labels->parent_field_description = 'Оберіть батьківську категорію, щоб створити підкатегорію. Наприклад, «Раки» може бути батьківською для «Варені» й «Живі».';
		return $labels;
	});
}
unset($raky_taxonomy);

/** Рядки ядра без українського перекладу: [оригінал => переклад]. */
function raky_missing_translations(): array {
	return [
		'Show or hide panel'                                       => 'Показати або сховати панель',
		'Infinite Scrolling'                                       => 'Нескінченна прокрутка',
		'Disable infinite scrolling in the Media Library grid view' => 'Вимкнути нескінченну прокрутку в сітці медіатеки',
		'Connection lost.'                                         => 'З’єднання втрачено.',
		'Saving has been disabled until you are reconnected.'      => 'Збереження вимкнено, доки з’єднання не відновиться.',
	];
}

function raky_fill_translation(string $translation, string $text, string $domain): string {
	if (! in_array($domain, ['default', 'woocommerce'], true) || $translation !== $text) {
		return $translation;
	}
	$missing = raky_missing_translations();
	if (isset($missing[$text])) {
		return $missing[$text];
	}
	if (str_starts_with($text, 'If this is a development website, you can')) {
		return 'Якщо це сайт для розробки, <a href="%s">вкажіть відповідний тип середовища</a>, щоб увімкнути паролі застосунків.';
	}
	return $translation;
}

add_filter('gettext', 'raky_fill_translation', 10, 3);
add_filter('gettext_with_context', static fn ($translation, $text, $context, $domain) => raky_fill_translation($translation, $text, $domain), 10, 4);
