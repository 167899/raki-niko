<?php
defined('ABSPATH') || exit;

/**
 * Налаштування магазину. Точки та зони зберігаються як JSON, щоб їх можна було
 * змінювати в адмінці без правок коду.
 *
 * outlets: [{id, name, address, phone, lat, lng, hours}]
 * zones:   [{id, name, outlet_id, fee, min_order, color, polygon: [[lat, lng], ...]}]
 */
function raky_get_settings(): array {
	$defaults = [
		'requisites'    => '',
		'support_phone' => '',
		'referral_on'   => false,
		'referral_pct'  => 5,
		'outlets'       => [],
		'zones'         => [],
	];
	return array_merge($defaults, (array) get_option('raky_settings', []));
}

/** Соцмережі, які можна додати: ключ => назва. Іконки однакові в застосунку й на сайті. */
const RAKY_SOCIAL_NETWORKS = [
	'telegram'  => 'Telegram',
	'viber'     => 'Viber',
	'instagram' => 'Instagram',
	'facebook'  => 'Facebook',
	'tiktok'    => 'TikTok',
];

const RAKY_DEFAULT_PHONES = ['+380936473902', '+380633001953', '+380633418905'];

/** 063 300 19 53 → +380633001953; не український номер — ''. */
function raky_contact_phone(string $phone): string {
	$digits = (string) preg_replace('/\D/', '', $phone);
	if (preg_match('/^0\d{9}$/', $digits)) {
		return '+38' . $digits;
	}
	return preg_match('/^380\d{9}$/', $digits) ? '+' . $digits : '';
}

/**
 * Телефони й соцмережі магазину — одні для застосунку й сайту, редагуються в «WooCommerce → Застосунок».
 * Поки їх там не зберігали, беремо те, що було на сторінці «Сайт», а далі — типові.
 *
 * @return array{phones: string[], socials: array<int, array{network: string, label: string, url: string}>}
 */
function raky_contacts(): array {
	$saved = (array) get_option('raky_settings', []);
	$site  = (array) get_option('raky_site', []);
	if (array_key_exists('phones', $saved)) {
		$phones = (array) $saved['phones'];
	} else {
		$phones = array_filter(array_map('raky_contact_phone', preg_split('/\R/u', (string) ($site['phones'] ?? ''))));
		$phones = $phones ?: RAKY_DEFAULT_PHONES;
	}
	if (array_key_exists('socials', $saved)) {
		$socials = (array) $saved['socials'];
	} else {
		$socials = [];
		foreach (array_keys(RAKY_SOCIAL_NETWORKS) as $network) {
			$url = trim((string) ($site[$network] ?? ($network === 'telegram' ? 'https://t.me/rakiniko' : '')));
			if ($url !== '') {
				$socials[] = ['network' => $network, 'url' => $url];
			}
		}
	}
	$socials = array_values(array_filter(array_map(static fn ($item) => isset(RAKY_SOCIAL_NETWORKS[$item['network'] ?? '']) && ($item['url'] ?? '') !== ''
		? ['network' => $item['network'], 'label' => RAKY_SOCIAL_NETWORKS[$item['network']], 'url' => (string) $item['url']]
		: null, $socials)));
	return ['phones' => array_values($phones), 'socials' => $socials];
}

// Один раз переносимо контакти зі сторінки «Сайт» у спільні налаштування, поки їх там не перезаписали.
add_action('admin_init', static function () {
	$saved = (array) get_option('raky_settings', []);
	if (! array_key_exists('phones', $saved)) {
		$contacts           = raky_contacts();
		$saved['phones']    = $contacts['phones'];
		$saved['socials']   = array_map(static fn (array $item) => ['network' => $item['network'], 'url' => $item['url']], $contacts['socials']);
		$saved['support_phone'] = $contacts['phones'][0] ?? '';
		update_option('raky_settings', $saved);
	}
});

/** Перше посилання на мережу або ''. */
function raky_contact_url(string $network): string {
	foreach (raky_contacts()['socials'] as $item) {
		if ($item['network'] === $network) {
			return $item['url'];
		}
	}
	return '';
}

function raky_rest_config(): array {
	$settings = raky_get_settings();
	$contacts = raky_contacts();
	$site     = (array) get_option('raky_site', []);
	return [
		'currency'      => get_woocommerce_currency(),
		// Старі версії застосунку читають лише один номер.
		'support_phone' => $contacts['phones'][0] ?? '',
		'phones'        => $contacts['phones'],
		'socials'       => $contacts['socials'],
		'referral'      => ['enabled' => (bool) $settings['referral_on'], 'percent' => (float) $settings['referral_pct']],
		'banners_on'    => raky_banners_on(),
		// Зі сторінки «Сайт»; порожнє — застосунок покаже свій типовий текст.
		'hours'         => trim((string) ($site['hours'] ?? '')),
		'about'         => trim((string) ($site['about'] ?? '')),
		'outlets'       => array_values($settings['outlets']),
		'zones'         => array_values($settings['zones']),
	];
}
