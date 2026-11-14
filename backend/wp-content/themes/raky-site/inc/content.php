<?php
/**
 * Дані компанії для шаблонів. Значення редагуються в адмінці: «Сайт» (inc/settings.php).
 * Типові значення ті самі, що в застосунку (mobile/src/content.ts).
 */
defined('ABSPATH') || exit;

// Телефони й соцмережі спільні із застосунком: «WooCommerce → Застосунок» (плагін raky-shop).
define('RAKY_SITE_PHONES', raky_contacts()['phones'] ?: ['+380936473902']);
define('RAKY_SITE_HOURS', raky_site_opt('hours'));
define('RAKY_SITE_TELEGRAM', raky_contact_url('telegram'));
define('RAKY_SITE_VIBER', raky_contact_url('viber'));
define('RAKY_SITE_CITY', raky_site_opt('city'));
define('RAKY_SITE_TAGLINE', raky_site_opt('tagline'));
define('RAKY_SITE_CASH_NOTE', raky_site_opt('cash_note'));
/** Юрист перевірив тексти: без позначки «Макет», сторінки індексуються. */
define('RAKY_SITE_LEGAL_OK', raky_site_opt('legal_ok') === '1');

function raky_site_privacy(): array {
	return raky_site_sections('privacy');
}

function raky_site_offer(): array {
	return raky_site_sections('offer');
}

function raky_site_pay_steps(): array {
	return raky_site_lines('pay_steps');
}

/** Іконки соцмереж у порядку з адмінки. Viber іконки не має: він лише в «Написати нам». */
function raky_site_socials(): array {
	$icons = ['telegram' => 'brand-telegram', 'instagram' => 'brand-instagram', 'facebook' => 'brand-facebook', 'tiktok' => 'brand-tiktok'];
	$out   = [];
	foreach (raky_contacts()['socials'] as $item) {
		if (isset($icons[$item['network']])) {
			$out[] = ['icon' => $icons[$item['network']], 'label' => $item['label'], 'url' => $item['url']];
		}
	}
	return $out;
}
