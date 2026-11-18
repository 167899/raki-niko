<?php
defined('ABSPATH') || exit;

/**
 * Push-сповіщення в застосунок через Expo Push Service: приходять, навіть коли застосунок закрито.
 *
 * - POST /push/register {token, orders: [{id, key}]} — застосунок повідомляє токен телефону.
 *   Із заголовком X-Raky-Session токен прив'язується до підтвердженого номера, а за ключами — до замовлень цього пристрою.
 * - Статус замовлення змінився (адмінка чи Telegram) — сповіщення на телефони замовлення.
 * - Опублікована «Новина застосунку» — сповіщення всім (галочку можна зняти в новині).
 */

const RAKY_PUSH_DEVICES   = 'raky_push_devices';   // [токен => ['phone' => 380… або '', 'seen' => час]]
const RAKY_PUSH_ORDER_META = '_raky_push_tokens';  // токени телефонів, з яких стежать за замовленням
const RAKY_PUSH_DEVICE_TTL = 180 * DAY_IN_SECONDS; // телефон, що давно не відкривав застосунок, прибираємо
const RAKY_PUSH_URL        = 'https://exp.host/--/api/v2/push/send';
const RAKY_PUSH_CHUNK      = 100;                  // ліміт Expo на один запит

function raky_push_valid_token(string $token): bool {
	return (bool) preg_match('/^Expo(nent)?PushToken\[[A-Za-z0-9_-]{10,}\]$/', $token);
}

/** @return array<string, array{phone: string, seen: int}> */
function raky_push_devices(): array {
	return (array) get_option(RAKY_PUSH_DEVICES, []);
}

function raky_push_save_devices(array $devices): void {
	$cutoff  = time() - RAKY_PUSH_DEVICE_TTL;
	$devices = array_filter($devices, static fn (array $device) => (int) $device['seen'] > $cutoff);
	update_option(RAKY_PUSH_DEVICES, $devices, false);
}

function raky_rest_push_register(WP_REST_Request $request) {
	if (raky_rate_limited('push', 60)) {
		return raky_error('raky_rate_limited', 'Забагато спроб. Спробуйте за кілька хвилин.', 429);
	}
	$token = (string) $request->get_param('token');
	if (! raky_push_valid_token($token)) {
		return raky_error('raky_bad_push_token', 'Невідомий токен сповіщень.');
	}
	$phone   = raky_auth_phone($request);
	$devices = raky_push_devices();
	$before  = $devices[$token] ?? null;
	// Номер не підтверджено (вийшли з акаунта) — телефон більше не отримує сповіщень про чужі замовлення.
	// Позначка «бачили» оновлюється не частіше раза на день, щоб не писати в базу на кожен запуск.
	if (! $before || $before['phone'] !== $phone || time() - (int) $before['seen'] > DAY_IN_SECONDS) {
		$devices[$token] = ['phone' => $phone, 'seen' => time()];
		raky_push_save_devices($devices);
	}

	$orders = (array) $request->get_param('orders');
	foreach (array_slice($orders, 0, 50) as $item) {
		$order = is_array($item) ? wc_get_order((int) ($item['id'] ?? 0)) : null;
		if (! $order || ! hash_equals($order->get_order_key(), (string) ($item['key'] ?? ''))) {
			continue;
		}
		$tokens = (array) $order->get_meta(RAKY_PUSH_ORDER_META);
		if (! in_array($token, $tokens, true)) {
			$tokens[] = $token;
			$order->update_meta_data(RAKY_PUSH_ORDER_META, array_slice($tokens, -5));
			$order->save_meta_data();
		}
	}
	return ['ok' => true];
}

add_action('rest_api_init', static function () {
	register_rest_route(RAKY_NS, '/push/register', [
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'raky_rest_push_register',
	]);
});

/**
 * Надсилає одне сповіщення на телефони. Телефони, з яких застосунок видалили, прибираємо зі списку.
 * @return int скільки телефонів Expo прийняв
 */
function raky_push_send(array $tokens, string $title, string $body, array $data = []): int {
	$tokens = array_values(array_unique(array_filter($tokens, 'raky_push_valid_token')));
	$sent   = 0;
	$gone   = [];
	foreach (array_chunk($tokens, RAKY_PUSH_CHUNK) as $chunk) {
		$messages = array_map(static fn (string $to) => [
			'to'        => $to,
			'title'     => $title,
			'body'      => $body,
			'data'      => (object) $data,
			'sound'     => 'default',
			'priority'  => 'high',
			'channelId' => 'default',
		], $chunk);
		$response = wp_remote_post(RAKY_PUSH_URL, [
			'timeout' => 10,
			'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
			'body'    => wp_json_encode($messages),
		]);
		if (is_wp_error($response)) {
			error_log('Raky push: ' . $response->get_error_message());
			continue;
		}
		$tickets = json_decode((string) wp_remote_retrieve_body($response), true)['data'] ?? [];
		foreach ((array) $tickets as $i => $ticket) {
			if (($ticket['status'] ?? '') === 'ok') {
				$sent++;
			} elseif (($ticket['details']['error'] ?? '') === 'DeviceNotRegistered' && isset($chunk[$i])) {
				$gone[] = $chunk[$i];
			}
		}
	}
	if ($gone) {
		$devices = raky_push_devices();
		foreach ($gone as $token) {
			unset($devices[$token]);
		}
		raky_push_save_devices($devices);
	}
	return $sent;
}

/** Телефони, що стежать за замовленням: з цього пристрою та всі, де увійшли з тим самим номером. */
function raky_push_order_tokens(WC_Order $order): array {
	$tokens = (array) $order->get_meta(RAKY_PUSH_ORDER_META);
	$phone  = preg_replace('/\D/', '', $order->get_billing_phone());
	if (strlen($phone) === 10) {
		$phone = '38' . $phone;
	}
	if ($phone !== '') {
		foreach (raky_push_devices() as $token => $device) {
			if ($device['phone'] === $phone) {
				$tokens[] = $token;
			}
		}
	}
	return $tokens;
}

/** Текст сповіщення про статус — той самий, що застосунок показує в «Повідомленнях». */
function raky_push_status_text(WC_Order $order, string $status): ?array {
	$id = $order->get_id();
	switch ($status) {
		case 'raky-awaitpay':
			return ["Замовлення № $id підтверджено", 'Реквізити для оплати вже в замовленні'];
		case 'processing':
			return $order->get_payment_method() === 'cod'
				? ["Замовлення № $id підтверджено", 'Готуємо ваше замовлення']
				: ["Оплату за № $id отримано", 'Готуємо ваше замовлення'];
		case 'raky-delivery':
			return ["Замовлення № $id передано в доставку", "Кур'єр уже в дорозі"];
		case 'cancelled':
		case 'failed':
			return ["Замовлення № $id скасовано", 'Зателефонуйте нам, якщо є запитання'];
	}
	return null;
}

add_action('woocommerce_order_status_changed', static function (int $order_id, string $from, string $to, WC_Order $order) {
	$text = raky_push_status_text($order, $to);
	if (! $text) {
		return;
	}
	$tokens = raky_push_order_tokens($order);
	if ($tokens) {
		raky_push_send($tokens, $text[0], $text[1], ['type' => 'order', 'id' => $order_id]);
	}
}, 20, 4);

/* ---------- Новини: сповіщення всім під час публікації ---------- */

const RAKY_PUSH_NEWS_SKIP = '_raky_push_skip'; // 'yes' — не надсилати
const RAKY_PUSH_NEWS_SENT = '_raky_push_sent'; // ['count', 'time']

add_action('add_meta_boxes_raky_news', static function () {
	add_meta_box('raky_push', 'Push-сповіщення', static function (WP_Post $post) {
		wp_nonce_field('raky_push_news', 'raky_push_nonce');
		$sent = get_post_meta($post->ID, RAKY_PUSH_NEWS_SENT, true);
		if (is_array($sent)) {
			printf('<p>Надіслано %s, %s.</p>', esc_html(sprintf('на %d тел.', (int) $sent['count'])), esc_html(wp_date('d.m.Y H:i', (int) $sent['time'])));
			return;
		}
		$skip = get_post_meta($post->ID, RAKY_PUSH_NEWS_SKIP, true) === 'yes';
		printf(
			'<label><input type="checkbox" name="raky_push_send" value="1" %s> Надіслати клієнтам під час публікації</label><p class="description">Заголовок і текст новини прийдуть на телефони, де встановлено застосунок і дозволено сповіщення (зараз: %d).</p>',
			checked(! $skip, true, false),
			count(raky_push_devices())
		);
	}, 'raky_news', 'side');
});

add_action('save_post_raky_news', static function (int $post_id) {
	if (! isset($_POST['raky_push_nonce']) || ! wp_verify_nonce(sanitize_key(wp_unslash($_POST['raky_push_nonce'])), 'raky_push_news') || ! current_user_can('edit_post', $post_id)) {
		return;
	}
	if (empty($_POST['raky_push_send'])) {
		update_post_meta($post_id, RAKY_PUSH_NEWS_SKIP, 'yes');
	} else {
		delete_post_meta($post_id, RAKY_PUSH_NEWS_SKIP);
	}
});

// Після збереження галочки: і з адмінки, і з Telegram (/news → «Опублікувати»), і для запланованих новин.
add_action('wp_after_insert_post', static function (int $post_id, WP_Post $post, bool $update, ?WP_Post $before) {
	if ($post->post_type !== 'raky_news' || $post->post_status !== 'publish' || ($before && $before->post_status === 'publish')) {
		return;
	}
	if (get_post_meta($post_id, RAKY_PUSH_NEWS_SKIP, true) === 'yes' || get_post_meta($post_id, RAKY_PUSH_NEWS_SENT, true)) {
		return;
	}
	// Позначку ставимо до відправлення, щоб повторне збереження не надіслало вдруге.
	update_post_meta($post_id, RAKY_PUSH_NEWS_SENT, ['count' => 0, 'time' => time()]);
	$count = raky_push_send(array_keys(raky_push_devices()), $post->post_title, wp_trim_words($post->post_excerpt, 30), ['type' => 'news', 'id' => $post_id]);
	update_post_meta($post_id, RAKY_PUSH_NEWS_SENT, ['count' => $count, 'time' => time()]);
}, 10, 4);
