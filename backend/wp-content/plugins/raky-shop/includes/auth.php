<?php
defined('ABSPATH') || exit;

/**
 * Вхід через Telegram: клієнт підтверджує номер телефону в боті магазину,
 * після цього застосунок і сайт бачать усі замовлення з цим номером.
 *
 * 1. POST /auth/telegram — застосунок чи сайт отримує одноразовий токен і посилання t.me/<бот>?start=login_<токен>.
 * 2. Клієнт відкриває бота, натискає «Поділитися номером». Telegram сам підставляє номер акаунта,
 *    тож чужий номер підтвердити не можна (перевіряємо, що контакт належить тому, хто його надіслав).
 * 3. GET /auth/telegram/<токен> — поки клієнт у боті, відповідь pending; після підтвердження — сесія (видається один раз).
 * 4. GET /me/orders із заголовком X-Raky-Session — замовлення за підтвердженим номером.
 * 5. GET/POST /me/favorites — «Улюблене», спільне для сайту й застосунку.
 *
 * Хто не може чи не хоче входити через Telegram, працює як раніше: замовлення зберігаються лише на своєму пристрої.
 */

const RAKY_AUTH_TTL        = 10 * MINUTE_IN_SECONDS; // скільки чекаємо підтвердження в боті
const RAKY_AUTH_SESSIONS   = 'raky_auth_sessions';   // [sha256(сесії) => ['phone', 'created', 'seen']]
const RAKY_AUTH_SESSION_TTL = YEAR_IN_SECONDS;
const RAKY_AUTH_HEADER     = 'X-Raky-Session';
const RAKY_AUTH_ORDERS_MAX = 50;
const RAKY_FAVORITES       = 'raky_favorites';       // [380XXXXXXXXX => [id товару, …]]
const RAKY_FAVORITES_MAX   = 200;

function raky_auth_login_key(string $token): string {
	return 'raky_tglogin_' . $token;
}

function raky_auth_valid_token(string $token): bool {
	return (bool) preg_match('/^[A-Za-z0-9]{32}$/', $token);
}

/** @return array<string, array{phone: string, created: int, seen: int}> */
function raky_auth_sessions(): array {
	return (array) get_option(RAKY_AUTH_SESSIONS, []);
}

function raky_auth_save_sessions(array $sessions): void {
	$cutoff   = time() - RAKY_AUTH_SESSION_TTL;
	$sessions = array_filter($sessions, static fn (array $session) => (int) $session['seen'] > $cutoff);
	update_option(RAKY_AUTH_SESSIONS, $sessions, false);
}

/** Нова сесія для підтвердженого номера. Зберігаємо лише хеш: з бази сесію не вкрасти. */
function raky_auth_new_session(string $phone): string {
	$session  = wp_generate_password(48, false);
	$sessions = raky_auth_sessions();
	$sessions[hash('sha256', $session)] = ['phone' => $phone, 'created' => time(), 'seen' => time()];
	raky_auth_save_sessions($sessions);
	return $session;
}

/** Підтверджений номер (380XXXXXXXXX) для сесії із запиту або ''. */
function raky_auth_phone(WP_REST_Request $request): string {
	$session = (string) $request->get_header(RAKY_AUTH_HEADER);
	if ($session === '') {
		return '';
	}
	$hash     = hash('sha256', $session);
	$sessions = raky_auth_sessions();
	if (! isset($sessions[$hash])) {
		return '';
	}
	// Позначка «бачили» оновлюється не частіше раза на день, щоб не писати в базу на кожен запит.
	if (time() - (int) $sessions[$hash]['seen'] > DAY_IN_SECONDS) {
		$sessions[$hash]['seen'] = time();
		raky_auth_save_sessions($sessions);
	}
	return (string) $sessions[$hash]['phone'];
}

function raky_rest_auth_start(WP_REST_Request $request) {
	if (raky_rate_limited('auth', 20)) {
		return raky_error('raky_rate_limited', 'Забагато спроб. Спробуйте за кілька хвилин.', 429);
	}
	$bot = (string) raky_tg_settings()['bot'];
	if (! raky_tg_ready() || $bot === '') {
		return raky_error('raky_auth_unavailable', 'Вхід через Telegram зараз недоступний.', 503);
	}
	$token = wp_generate_password(32, false);
	set_transient(raky_auth_login_key($token), ['status' => 'pending'], RAKY_AUTH_TTL);
	return [
		'token'      => $token,
		'url'        => 'https://t.me/' . rawurlencode($bot) . '?start=login_' . $token,
		'expires_in' => RAKY_AUTH_TTL,
	];
}

function raky_rest_auth_status(WP_REST_Request $request) {
	$token = (string) $request['token'];
	$login = raky_auth_valid_token($token) ? get_transient(raky_auth_login_key($token)) : false;
	if (! is_array($login)) {
		return raky_error('raky_auth_expired', 'Час на підтвердження минув. Спробуйте ще раз.', 404);
	}
	if ($login['status'] !== 'confirmed') {
		return ['status' => 'pending'];
	}
	// Сесію видаємо один раз: повторний запит з тим самим токеном нічого не отримає.
	delete_transient(raky_auth_login_key($token));
	return [
		'status'  => 'confirmed',
		'session' => raky_auth_new_session($login['phone']),
		'phone'   => '+' . $login['phone'],
		'name'    => (string) $login['name'],
	];
}

function raky_rest_auth_logout(WP_REST_Request $request) {
	$session = (string) $request->get_header(RAKY_AUTH_HEADER);
	if ($session !== '') {
		$sessions = raky_auth_sessions();
		unset($sessions[hash('sha256', $session)]);
		raky_auth_save_sessions($sessions);
	}
	return ['ok' => true];
}

/** Замовлення за підтвердженим номером, найновіші першими, у тому ж вигляді, що GET /orders/<id>. */
function raky_rest_my_orders(WP_REST_Request $request) {
	$phone = raky_auth_phone($request);
	if ($phone === '') {
		return raky_error('raky_auth_required', 'Увійдіть через Telegram ще раз.', 401);
	}
	$orders = [];
	foreach (raky_phone_variants($phone) as $variant) {
		foreach (wc_get_orders(['limit' => RAKY_AUTH_ORDERS_MAX, 'billing_phone' => $variant, 'orderby' => 'date', 'order' => 'DESC', 'return' => 'objects']) as $order) {
			$orders[$order->get_id()] = $order;
		}
	}
	krsort($orders);
	return [
		'phone'  => '+' . $phone,
		'orders' => array_map('raky_order_payload', array_slice(array_values($orders), 0, RAKY_AUTH_ORDERS_MAX)),
	];
}

/** @return int[] */
function raky_favorites_of(string $phone): array {
	$all = (array) get_option(RAKY_FAVORITES, []);
	return array_values(array_map('intval', (array) ($all[$phone] ?? [])));
}

function raky_rest_my_favorites(WP_REST_Request $request) {
	$phone = raky_auth_phone($request);
	if ($phone === '') {
		return raky_error('raky_auth_required', 'Увійдіть через Telegram ще раз.', 401);
	}
	if ($request->get_method() === 'POST') {
		$ids = array_values(array_unique(array_filter(array_map('intval', (array) $request->get_param('ids')), static fn (int $id) => $id > 0)));
		$ids = array_slice($ids, 0, RAKY_FAVORITES_MAX);
		$all = (array) get_option(RAKY_FAVORITES, []);
		if ($ids) {
			$all[$phone] = $ids;
		} else {
			unset($all[$phone]);
		}
		update_option(RAKY_FAVORITES, $all, false);
	}
	return ['ids' => raky_favorites_of($phone)];
}

add_action('rest_api_init', static function () {
	register_rest_route(RAKY_NS, '/auth/telegram', [
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'raky_rest_auth_start',
	]);
	register_rest_route(RAKY_NS, '/auth/telegram/(?P<token>[A-Za-z0-9]+)', [
		'methods'             => 'GET',
		'permission_callback' => '__return_true', // доступ — за одноразовим токеном
		'callback'            => 'raky_rest_auth_status',
	]);
	register_rest_route(RAKY_NS, '/auth/logout', [
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'raky_rest_auth_logout',
	]);
	register_rest_route(RAKY_NS, '/me/orders', [
		'methods'             => 'GET',
		'permission_callback' => '__return_true', // доступ — за сесією в заголовку X-Raky-Session
		'callback'            => 'raky_rest_my_orders',
	]);
	register_rest_route(RAKY_NS, '/me/favorites', [
		'methods'             => 'GET, POST',
		'permission_callback' => '__return_true', // доступ — за сесією в заголовку X-Raky-Session
		'callback'            => 'raky_rest_my_favorites',
	]);
});

/* Бот: особисті повідомлення клієнтів. */

const RAKY_AUTH_SHARE = '📱 Поділитися номером';

function raky_auth_tg_reply(int $chat, string $text, ?array $markup = null): void {
	$params = ['chat_id' => $chat, 'text' => $text];
	if ($markup) {
		$params['reply_markup'] = $markup;
	}
	raky_tg_api('sendMessage', $params);
}

/**
 * Кнопка «Поділитися номером». Telegram дозволяє її лише в клавіатурі під полем вводу (не під повідомленням),
 * тому робимо її постійно розгорнутою: клієнту не треба шукати її за значком клавіатури.
 */
function raky_auth_share_keyboard(): array {
	return [
		'keyboard'                => [[['text' => RAKY_AUTH_SHARE, 'request_contact' => true]]],
		'is_persistent'           => true,
		'resize_keyboard'         => true,
		'one_time_keyboard'       => true,
		'input_field_placeholder' => 'Натисніть «Поділитися номером»',
	];
}

/** Особистий чат клієнта з ботом: /start login_<токен> і надісланий контакт. */
function raky_auth_tg_private(array $message): void {
	$chat = (int) ($message['chat']['id'] ?? 0);
	$from = (int) ($message['from']['id'] ?? 0);
	if (! $chat || ! $from) {
		return;
	}
	$text = trim((string) ($message['text'] ?? ''));

	if (preg_match('/^\/start(?:\s+login_([A-Za-z0-9]{32}))?$/', $text, $m)) {
		$token = $m[1] ?? '';
		$login = $token !== '' ? get_transient(raky_auth_login_key($token)) : false;
		if (! is_array($login) || $login['status'] !== 'pending') {
			raky_auth_tg_reply($chat, $token === ''
				? 'Це бот магазину Raki Niko. Щоб увійти, натисніть «Увійти через Telegram» на сайті rakiniko.com.ua або в застосунку.'
				: 'Посилання для входу застаріло. Натисніть «Увійти через Telegram» ще раз.');
			return;
		}
		set_transient('raky_tglogin_user_' . $from, $token, RAKY_AUTH_TTL);
		raky_auth_tg_reply($chat, "Щоб увійти в Raki Niko, натисніть велику кнопку «Поділитися номером» внизу екрана 👇\n\nТак ми переконаємося, що номер ваш, і покажемо всі ваші замовлення — на сайті й у застосунку.", raky_auth_share_keyboard());
		return;
	}

	$contact = $message['contact'] ?? null;
	if (! is_array($contact)) {
		return;
	}
	$token = (string) get_transient('raky_tglogin_user_' . $from);
	$login = raky_auth_valid_token($token) ? get_transient(raky_auth_login_key($token)) : false;
	$done  = ['remove_keyboard' => true];
	if (! is_array($login) || $login['status'] !== 'pending') {
		raky_auth_tg_reply($chat, 'Посилання для входу застаріло. Натисніть «Увійти через Telegram» на сайті чи в застосунку ще раз.', $done);
		return;
	}
	// Лише власний номер: контакт, переданий кнопкою, має user_id того, хто його надіслав.
	if ((int) ($contact['user_id'] ?? 0) !== $from) {
		raky_auth_tg_reply($chat, 'Надішліть, будь ласка, свій номер кнопкою «Поділитися номером» внизу екрана.', raky_auth_share_keyboard());
		return;
	}
	$phone = raky_phone_380((string) ($contact['phone_number'] ?? ''));
	if ($phone === '') {
		raky_auth_tg_reply($chat, 'Вхід поки працює лише з українськими номерами. Ви можете й далі замовляти без входу.', $done);
		return;
	}
	$client = raky_client_by_phone($phone);
	$name   = $client ? trim((string) ($client->first_name ?: $client->display_name)) : '';
	if ($name === '' || $name === $phone) {
		$name = trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? ''));
	}
	set_transient(raky_auth_login_key($token), ['status' => 'confirmed', 'phone' => $phone, 'name' => $name], RAKY_AUTH_TTL);
	delete_transient('raky_tglogin_user_' . $from);
	raky_auth_tg_reply($chat, '✅ Готово! Номер підтверджено. Поверніться на сайт або в застосунок Raki Niko.', $done);
}
