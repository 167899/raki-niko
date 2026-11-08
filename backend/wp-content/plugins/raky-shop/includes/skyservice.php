<?php
defined('ABSPATH') || exit;

/**
 * Ціни й наявність зі Skyservice POS.
 *
 * Публічної документації API Skyservice немає. Запити повторюють кабінет Skyservice:
 * вхід за телефоном і паролем на api.auth.skyservice.online дає токен сесії, а запит redirect
 * повертає адресу API компанії: https://{акаунт}.api.skyservice.online/?section=…&action=…&token=…&companyId=…
 * API-токени для цього акаунта Skyservice не ввімкнені, тому вхід за паролем.
 *
 * Skyservice обмежує спроби входу. Сесія зберігається й оновлюється, лише коли сервер її відхилив,
 * не частіше одного входу за запит. Після відмови в паролі входи зупиняються до нового збереження налаштувань.
 * Товари зіставляються за номером товару в Skyservice: артикули в касі не заповнені.
 * Товар застосунку може складатися з кількох позицій каси: основна (раки) дає ціну й наявність,
 * додаткові (рецепт) лише додають ціну, бо рецепти в касі зазвичай вимкнені. Сума множиться
 * на множник товару, наприклад 0.1 для ціни за 100 г, коли в касі ціна за кілограм.
 */

define('RAKY_META_SKY_ID', '_raky_sky_id');         // основний товар Skyservice
define('RAKY_META_SKY_EXTRA', '_raky_sky_extra');   // додаткові позиції через кому, лише ціна
define('RAKY_META_SKY_FACTOR', '_raky_sky_factor'); // множник ціни каси, 1 за замовчуванням
const RAKY_SKY_CRON       = 'raky_skyservice_sync';
const RAKY_SKY_INTERVAL   = 10 * MINUTE_IN_SECONDS;
const RAKY_SKY_CACHE      = 'raky_sky_products';
const RAKY_SKY_DEFAULT_TP = 'Спаська';
const RAKY_SKY_AUTH_URL   = 'https://api.auth.skyservice.online/';

function raky_sky_settings(): array {
	return array_merge(
		[
			'company_id'      => '',
			'login'           => '',
			'password'        => '', // зашифрований, див. raky_sky_encrypt
			'session'         => '',
			'api_url'         => '',
			'stopped'         => '', // причина, з якої входи зупинено
			'tradepoint_id'   => '',
			'tradepoint_name' => '',
		],
		(array) get_option('raky_skyservice', [])
	);
}

function raky_sky_update(array $changes): array {
	$settings = array_merge(raky_sky_settings(), $changes);
	update_option('raky_skyservice', $settings, false);
	return $settings;
}

function raky_sky_ready(): bool {
	$settings = raky_sky_settings();
	return $settings['company_id'] !== '' && $settings['login'] !== '' && $settings['password'] !== '';
}

/** Пароль у базі зашифрований ключем із солей WordPress: дамп бази без wp-config.php його не розкриває. */
function raky_sky_key(): string {
	return sodium_crypto_generichash(wp_salt('auth') . 'raky-skyservice', '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
}

function raky_sky_encrypt(string $plain): string {
	$nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
	return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, raky_sky_key()));
}

function raky_sky_decrypt(string $stored): string {
	$raw = base64_decode($stored, true);
	if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
		return '';
	}
	$plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), raky_sky_key());
	return $plain === false ? '' : $plain;
}

/** Телефон у форматі Skyservice: лише цифри з кодом країни, 0XX… доповнюється до 380XX…. */
function raky_sky_phone(string $phone): string {
	$digits = preg_replace('/\D/', '', $phone);
	return str_starts_with($digits, '0') ? '38' . $digits : $digits;
}

/** Адреса кабінету: https://{акаунт}.dashboard.skyservice.online/{companyId}/… */
function raky_sky_parse_url(string $url): ?string {
	$host = (string) wp_parse_url(trim($url), PHP_URL_HOST);
	$path = (string) wp_parse_url(trim($url), PHP_URL_PATH);
	if (! preg_match('/\.skyservice\.online$/i', $host) || ! preg_match('/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/i', $path, $company)) {
		return null;
	}
	return strtolower($company[1]);
}

/**
 * Адреса API з відповіді redirect. Skyservice віддає адресу каси чи кабінету
 * ({акаунт}.pos… або {акаунт}.dashboard…), а API живе на {акаунт}.api.skyservice.online.
 */
function raky_sky_api_url(string $redirect): ?string {
	$host = strtolower((string) wp_parse_url($redirect, PHP_URL_HOST));
	if (! preg_match('/^([a-z0-9-]+)\.[a-z]+\.skyservice\.online$/', $host, $match)) {
		return null;
	}
	return 'https://' . $match[1] . '.api.skyservice.online/';
}

function raky_sky_post_json(string $url, array $body) {
	$response = wp_remote_post($url, [
		'timeout' => 20,
		'headers' => ['Content-Type' => 'application/json'],
		'body'    => wp_json_encode($body),
	]);
	if (is_wp_error($response)) {
		return new WP_Error('raky_sky_network', 'Skyservice недоступний: ' . $response->get_error_message());
	}
	$json = json_decode((string) wp_remote_retrieve_body($response), true);
	return is_array($json) ? $json : new WP_Error('raky_sky_response', sprintf('Skyservice відповів незрозуміло (код %d).', wp_remote_retrieve_response_code($response)));
}

/**
 * Вхід і відкриття компанії. Відмова в паролі зупиняє подальші входи.
 *
 * @return array|WP_Error налаштування з новою сесією
 */
function raky_sky_login() {
	$settings = raky_sky_settings();
	if ($settings['stopped'] !== '') {
		return new WP_Error('raky_sky_stopped', $settings['stopped']);
	}
	$password = raky_sky_decrypt($settings['password']);
	if ($password === '') {
		return new WP_Error('raky_sky_config', 'Не вдалося прочитати збережений пароль Skyservice. Введіть його ще раз.');
	}

	$auth = raky_sky_post_json(RAKY_SKY_AUTH_URL . '?section=users&action=auth', ['login' => $settings['login'], 'password' => $password, 'lang' => 'uk']);
	if (is_wp_error($auth)) {
		return $auth;
	}
	$token = ($auth['status'] ?? '') === 'done' ? (string) ($auth['data']['token'] ?? '') : '';
	if ($token === '') {
		$stopped = isset($auth['data']['canAuth']) && ! $auth['data']['canAuth']
			? 'Skyservice тимчасово заблокував вхід через невдалі спроби. Спробуйте пізніше й збережіть налаштування знову.'
			: 'Skyservice не прийняв телефон або пароль. Синхронізацію зупинено, щоб не заблокувати вхід. Виправте дані й збережіть.';
		raky_sky_update(['stopped' => $stopped, 'session' => '']);
		return new WP_Error('raky_sky_login', $stopped);
	}

	$company = raky_sky_post_json(
		RAKY_SKY_AUTH_URL . '?section=users&action=redirect&token=' . rawurlencode($token),
		['token' => $token, 'company_id' => $settings['company_id']]
	);
	if (is_wp_error($company)) {
		return $company;
	}
	$api_url = ($company['status'] ?? '') === 'done' && is_string($company['data'] ?? null) ? raky_sky_api_url($company['data']) : null;
	if (! $api_url) {
		$stopped = in_array('worker is inactive', (array) ($company['error'] ?? []), true)
			? 'Співробітника вимкнено в цій компанії Skyservice.'
			: 'Skyservice не відкрив компанію. Перевірте адресу кабінету.';
		raky_sky_update(['stopped' => $stopped, 'session' => '']);
		return new WP_Error('raky_sky_company', $stopped);
	}
	// Якщо Skyservice віддає окремий токен компанії в адресі, беремо його.
	parse_str((string) wp_parse_url($company['data'], PHP_URL_QUERY), $query);
	return raky_sky_update(['session' => (string) ($query['token'] ?? $token), 'api_url' => $api_url]);
}

/** @return array|WP_Error поле data відповіді */
function raky_sky_request(string $section, string $action, array $params = []) {
	static $logged_in = false;
	if (! raky_sky_ready()) {
		return new WP_Error('raky_sky_config', 'Skyservice не підключено.');
	}
	$settings = raky_sky_settings();
	if ($settings['session'] === '' || $settings['api_url'] === '') {
		if ($logged_in) {
			return new WP_Error('raky_sky_login', 'Skyservice не відкрив сесію.');
		}
		$logged_in = true;
		$settings  = raky_sky_login();
		if (is_wp_error($settings)) {
			return $settings;
		}
	}

	$url = add_query_arg(
		array_map('rawurlencode', array_merge(
			['section' => $section, 'action' => $action, 'token' => $settings['session'], 'companyId' => $settings['company_id']],
			$params
		)),
		raky_sky_api_url($settings['api_url']) ?? $settings['api_url']
	);
	$response = wp_remote_get($url, ['timeout' => 20]);
	if (is_wp_error($response)) {
		return new WP_Error('raky_sky_network', 'Skyservice недоступний: ' . $response->get_error_message());
	}
	$code = (int) wp_remote_retrieve_response_code($response);
	$body = json_decode((string) wp_remote_retrieve_body($response), true);
	// Відмова приходить із кодом 200: {"status":"error","error":["noauth"]}.
	$errors = is_array($body) ? (array) ($body['error'] ?? []) : [];
	if ($code === 401 || $code === 403 || in_array('noauth', $errors, true)) {
		raky_sky_update(['session' => '']);
		return $logged_in
			? new WP_Error('raky_sky_auth', 'Skyservice відхилив нову сесію. Перевірте права співробітника.')
			: raky_sky_request($section, $action, $params);
	}
	if (! is_array($body)) {
		return new WP_Error('raky_sky_response', sprintf('Skyservice відповів не даними, а сторінкою (код %d). Можливо, змінилася адреса API.', $code));
	}
	if ($code !== 200 || ($body['status'] ?? '') !== 'done' || ! isset($body['data'])) {
		$detail = $errors ? implode(', ', array_map('strval', $errors)) : sprintf('код %d', $code);
		return new WP_Error('raky_sky_response', 'Skyservice відхилив запит (' . $detail . ').');
	}
	return $body['data'];
}

/** Масив записів із відповіді: data буває списком або {items: […]}. */
function raky_sky_rows($data): array {
	if (is_array($data) && isset($data['items']) && is_array($data['items'])) {
		return $data['items'];
	}
	return is_array($data) && $data === array_values($data) ? $data : [];
}

/** @return array|WP_Error [{id, name}] */
function raky_sky_tradepoints() {
	$data = raky_sky_request('tradepoints', 'getTradepoints');
	if (is_wp_error($data)) {
		return $data;
	}
	$points = [];
	foreach (raky_sky_rows($data) as $row) {
		$id = (string) ($row['tradepointId'] ?? $row['id'] ?? '');
		if ($id !== '') {
			$points[] = ['id' => $id, 'name' => (string) ($row['tradepointName'] ?? $row['name'] ?? 'Точка ' . $id)];
		}
	}
	return $points;
}

/**
 * Точка цін: збережена або Спаська, яку знаходимо за назвою й запам'ятовуємо.
 *
 * @return string|WP_Error
 */
function raky_sky_tradepoint_id() {
	$settings = raky_sky_settings();
	if ($settings['tradepoint_id'] !== '') {
		return $settings['tradepoint_id'];
	}
	$points = raky_sky_tradepoints();
	if (is_wp_error($points)) {
		return $points;
	}
	foreach ($points as $point) {
		if (mb_stripos($point['name'], RAKY_SKY_DEFAULT_TP) !== false) {
			raky_sky_update(['tradepoint_id' => $point['id'], 'tradepoint_name' => $point['name']]);
			return $point['id'];
		}
	}
	return new WP_Error('raky_sky_tradepoint', 'У Skyservice не знайдено точку «' . RAKY_SKY_DEFAULT_TP . '». Оберіть точку цін.');
}

/** Підпис одиниці каси для списку в картці товару. */
function raky_sky_unit(string $unit): string {
	$labels = ['kg' => 'кг', '1kg' => 'кг', 'gr' => 'г', 'l' => 'л', 'pcs' => 'шт', 'por' => 'порція'];
	return $labels[strtolower($unit)] ?? '';
}

/** Номери товарів Skyservice із мети або форми: «12, 34» → ['12', '34']. */
function raky_sky_ids($value): array {
	$value = is_array($value) ? implode(',', $value) : (string) $value;
	return array_values(array_unique(array_filter(preg_split('/\D+/', $value), 'strlen')));
}

/** Множник ціни каси; порожнє або некоректне значення — 1. Кома й крапка рівноцінні. */
function raky_sky_factor($value): float {
	$factor = (float) str_replace(',', '.', (string) $value);
	return $factor > 0 ? $factor : 1.0;
}

/**
 * Ціна й наявність товару застосунку з позицій каси: наявність лише за основною позицією.
 *
 * @param string[] $ids основна позиція першою, далі додаткові
 * @return array{price: ?float, active: bool, missing: string[]} price — null, якщо в якоїсь позиції немає ціни
 */
function raky_sky_combine(array $ids, float $factor, array $products): array {
	$sum     = 0.0;
	$missing = [];
	$priced  = true;
	foreach ($ids as $id) {
		if (! isset($products[$id])) {
			$missing[] = $id;
		} elseif (is_numeric($products[$id]['price'])) {
			$sum += (float) $products[$id]['price'];
		} else {
			$priced = false;
		}
	}
	return [
		'price'   => $priced ? round($sum * $factor, 2) : null,
		'active'  => ! empty($products[$ids[0] ?? '']['active']),
		'missing' => $missing,
	];
}

/** Позиції каси товару: основна першою. Порожньо, якщо основну не вибрано. */
function raky_sky_product_ids(int $product_id): array {
	$main = raky_sky_ids(get_post_meta($product_id, RAKY_META_SKY_ID, true));
	if (! $main) {
		return [];
	}
	return array_values(array_unique(array_merge([$main[0]], raky_sky_ids(get_post_meta($product_id, RAKY_META_SKY_EXTRA, true)))));
}

/**
 * Товари каси для вибраної точки, з кешем на кілька хвилин для форми товару.
 *
 * @return array|WP_Error [id => {id, name, category, price, unit, active}]
 */
function raky_sky_products(bool $fresh = false) {
	$cached = $fresh ? false : get_transient(RAKY_SKY_CACHE);
	if (is_array($cached)) {
		return $cached;
	}
	$tradepoint = raky_sky_tradepoint_id();
	if (is_wp_error($tradepoint)) {
		return $tradepoint;
	}
	$data = raky_sky_request('products', 'getProducts', [
		'tradepointId' => $tradepoint,
		'page'         => 1,
		'pagelimit'    => 9999,
	]);
	if (is_wp_error($data)) {
		return $data;
	}
	$products = [];
	foreach (raky_sky_rows($data) as $row) {
		if (($row['type'] ?? 'product') !== 'product' || ! isset($row['id'])) {
			continue;
		}
		$products[(string) $row['id']] = [
			'id'       => (string) $row['id'],
			'name'     => (string) ($row['nomenclatureName'] ?? $row['name'] ?? ''),
			'category' => (string) ($row['categoryTitle'] ?? ''),
			'price'    => $row['price'] ?? null,
			'unit'     => raky_sky_unit((string) ($row['unit'] ?? '')),
			'active'   => ! empty($row['active']),
		];
	}
	if (! $products) {
		return new WP_Error('raky_sky_empty', 'Skyservice не повернув жодного товару. Перевірте точку й права співробітника.');
	}
	set_transient(RAKY_SKY_CACHE, $products, 5 * MINUTE_IN_SECONDS);
	return $products;
}

/** Товари WooCommerce, прив'язані до Skyservice: [product_id => {ids, factor}]. */
function raky_sky_linked(): array {
	$ids = get_posts([
		'post_type'      => 'product',
		'post_status'    => ['publish', 'draft', 'private'],
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_key'       => RAKY_META_SKY_ID, // phpcs:ignore WordPress.DB.SlowDBQuery
	]);
	$linked = [];
	foreach ($ids as $id) {
		$sky_ids = raky_sky_product_ids($id);
		if ($sky_ids) {
			$linked[$id] = ['ids' => $sky_ids, 'factor' => raky_sky_factor(get_post_meta($id, RAKY_META_SKY_FACTOR, true))];
		}
	}
	return $linked;
}

/** @return array|WP_Error звіт raky_apply_prices; товари, яких немає в касі, додаються до rejected */
function raky_sky_sync(bool $dry_run) {
	$products = raky_sky_products(true);
	if (is_wp_error($products)) {
		if (! $dry_run) {
			$last          = (array) get_option('raky_last_sync', []);
			$last['error'] = $products->get_error_message();
			$last['error_at'] = time();
			update_option('raky_last_sync', $last, false);
		}
		return $products;
	}
	$items   = [];
	$missing = [];
	foreach (raky_sky_linked() as $product_id => $link) {
		$sky = raky_sky_combine($link['ids'], $link['factor'], $products);
		if ($sky['missing']) {
			$missing[] = ['name' => get_the_title($product_id), 'reason' => 'missing', 'ids' => $sky['missing']];
			continue;
		}
		$items[] = ['product_id' => $product_id, 'price' => $sky['price'], 'active' => $sky['active']];
	}
	$report             = raky_apply_prices($items, $dry_run);
	$report['rejected'] = array_merge($report['rejected'], $missing);
	$report['linked']   = count($items) + count($missing);
	if (! $dry_run) {
		update_option('raky_last_sync', ['at' => time(), 'report' => $report, 'error' => ''], false);
	}
	return $report;
}

add_filter('cron_schedules', static function (array $schedules) {
	$schedules['raky_sky'] = ['interval' => RAKY_SKY_INTERVAL, 'display' => 'Skyservice: кожні 10 хвилин'];
	return $schedules;
});

add_action(RAKY_SKY_CRON, static function () {
	if (raky_sky_ready()) {
		raky_sky_sync(false);
	}
});

// Розклад вмикається, щойно Skyservice підключено, і вимикається разом із ним.
add_action('init', static function () {
	$scheduled = wp_next_scheduled(RAKY_SKY_CRON);
	if (raky_sky_ready() && ! $scheduled) {
		wp_schedule_event(time() + MINUTE_IN_SECONDS, 'raky_sky', RAKY_SKY_CRON);
	} elseif (! raky_sky_ready() && $scheduled) {
		wp_clear_scheduled_hook(RAKY_SKY_CRON);
	}
});

/** Форма підключення: порожнє поле пароля лишає збережений. */
function raky_sky_save_settings(): ?string {
	if (! empty($_POST['sky_disconnect'])) {
		delete_option('raky_skyservice');
		delete_transient(RAKY_SKY_CACHE);
		return null;
	}
	$settings = raky_sky_settings();
	$changes  = ['stopped' => ''];
	$url      = sanitize_text_field(wp_unslash((string) ($_POST['sky_url'] ?? '')));
	$login    = raky_sky_phone(sanitize_text_field(wp_unslash((string) ($_POST['sky_login'] ?? ''))));
	$password = (string) wp_unslash($_POST['sky_password'] ?? ''); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- пароль зберігається як є
	if ($url !== '') {
		$company = raky_sky_parse_url($url);
		if (! $company) {
			return 'Не вдалося розпізнати адресу кабінету. Скопіюйте її з адресного рядка Skyservice після входу.';
		}
		$changes['company_id'] = $company;
	}
	if ($login !== '') {
		if (strlen($login) < 10) {
			return 'Перевірте телефон для входу в Skyservice.';
		}
		$changes['login'] = $login;
	}
	if ($password !== '') {
		$changes['password'] = raky_sky_encrypt($password);
	}
	// Нові дані входу потребують нової сесії. Без змін зберігаємо стару, щоб не входити зайвий раз.
	foreach (['company_id', 'login', 'password'] as $key) {
		if (isset($changes[$key]) && $changes[$key] !== $settings[$key]) {
			$changes['session'] = '';
		}
	}
	$tradepoint = sanitize_text_field(wp_unslash((string) ($_POST['sky_tradepoint'] ?? '')));
	if ($tradepoint !== $settings['tradepoint_id']) {
		$changes['tradepoint_id']   = $tradepoint;
		$changes['tradepoint_name'] = '';
	}
	$settings = raky_sky_update($changes);
	delete_transient(RAKY_SKY_CACHE);
	if (! raky_sky_ready()) {
		return null;
	}

	// Без вибору беремо Спаську: ціни застосунку йдуть за цією точкою.
	$tradepoint = raky_sky_tradepoint_id();
	if (is_wp_error($tradepoint)) {
		return 'Налаштування збережено. ' . $tradepoint->get_error_message();
	}
	return null;
}
