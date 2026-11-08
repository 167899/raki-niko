<?php
defined('ABSPATH') || exit;

/**
 * Telegram-бот для менеджерів: нове замовлення приходить у чат із кнопками наступного кроку.
 *
 * Одне повідомлення на замовлення. Воно оновлюється за будь-якої зміни статусу,
 * з Telegram чи з адмінки, і завжди показує поточний статус, суму й кнопки.
 * Натискання приймаються лише з підключеного чату.
 *
 * Сайт на https отримує натискання миттєво (webhook). Без https, як на локальному сервері,
 * плагін сам забирає їх щохвилини (getUpdates у WP-Cron).
 */

const RAKY_TG_OPTION = 'raky_telegram';
const RAKY_TG_CRON   = 'raky_telegram_poll';
const RAKY_TG_API    = 'https://api.telegram.org/bot';

function raky_tg_settings(): array {
	return array_merge(
		[
			'token'      => '', // зашифрований, див. raky_sky_encrypt
			'bot'        => '', // @username бота
			'chat_id'    => '',
			'chat_title' => '',
			'mode'       => '', // webhook | polling
			'secret'     => '', // перевірка запитів webhook
			'offset'     => 0,  // наступне оновлення для getUpdates
			'error'      => '',
		],
		(array) get_option(RAKY_TG_OPTION, [])
	);
}

function raky_tg_update(array $changes): array {
	$settings = array_merge(raky_tg_settings(), $changes);
	update_option(RAKY_TG_OPTION, $settings, false);
	return $settings;
}

function raky_tg_ready(): bool {
	$settings = raky_tg_settings();
	return $settings['token'] !== '' && $settings['chat_id'] !== '';
}

/** @return mixed|WP_Error result відповіді Telegram */
function raky_tg_api(string $method, array $params = [], ?string $token = null) {
	$token = $token ?? raky_sky_decrypt(raky_tg_settings()['token']);
	if ($token === '') {
		return new WP_Error('raky_tg_token', 'Не задано токен бота.');
	}
	$response = wp_remote_post(RAKY_TG_API . $token . '/' . $method, [
		'timeout' => 8,
		'headers' => ['Content-Type' => 'application/json'],
		'body'    => wp_json_encode($params ?: new stdClass()),
	]);
	if (is_wp_error($response)) {
		return new WP_Error('raky_tg_http', 'Telegram недоступний: ' . $response->get_error_message());
	}
	$body = json_decode(wp_remote_retrieve_body($response), true);
	if (! is_array($body) || empty($body['ok'])) {
		$code = (int) ($body['error_code'] ?? wp_remote_retrieve_response_code($response));
		if ($code === 401 || $code === 404) {
			return new WP_Error('raky_tg_token', 'Telegram не прийняв токен. Скопіюйте його з @BotFather ще раз.');
		}
		return new WP_Error('raky_tg_api', 'Telegram відповів помилкою: ' . (string) ($body['description'] ?? $code));
	}
	return $body['result'];
}

/** Текст для parse_mode HTML. */
function raky_tg_escape(string $text): string {
	return htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');
}

/** Нерозривні пробіли: «2 900 ₴» не розривається між рядками. */
function raky_tg_money(float $value): string {
	$decimals = abs($value - round($value)) < 0.005 ? 0 : 2;
	return number_format($value, $decimals, ',', "\u{00A0}") . "\u{00A0}₴";
}

/** «2 кг», «300 г» для трьох по 100 г, «2 порц.». */
function raky_quantity_label(float $quantity, string $unit): string {
	$number = static fn (float $value) => rtrim(rtrim(number_format($value, 3, ',', "\u{00A0}"), '0'), ',');
	if ($unit === '100g') {
		return $number($quantity * 100) . "\u{00A0}г";
	}
	if ($unit === 'por') {
		return $number($quantity) . "\u{00A0}порц.";
	}
	return $number($quantity) . "\u{00A0}" . (raky_units()[$unit] ?? 'шт');
}

/**
 * Наступні кроки для менеджера: [статус => підпис кнопки].
 * Готівка оминає «Очікує оплату», самовивіз оминає доставку.
 */
function raky_tg_next(WC_Order $order): array {
	$pickup = $order->get_meta('_raky_delivery_type') === 'pickup';
	switch ($order->get_status()) {
		case 'raky-new':
			return $order->get_payment_method() === 'cod'
				? ['processing' => '✅ Підтвердити']
				: ['raky-awaitpay' => '✅ Підтвердити й чекати оплату'];
		case 'raky-awaitpay':
			return ['processing' => '💰 Оплату отримано'];
		case 'processing':
			return $pickup ? ['completed' => '✅ Видано клієнту'] : ['raky-delivery' => '🚚 Передано в доставку'];
		case 'raky-delivery':
			return ['completed' => '✅ Доставлено'];
	}
	return [];
}

function raky_tg_can_cancel(WC_Order $order): bool {
	return in_array($order->get_status(), ['raky-new', 'raky-awaitpay', 'processing'], true);
}

function raky_tg_order_text(WC_Order $order): string {
	$status  = $order->get_status();
	$closed  = in_array($status, ['completed', 'cancelled', 'refunded', 'failed'], true);
	$icon    = $status === 'raky-new' ? '🆕' : ($closed ? ($status === 'completed' ? '✔️' : '✖️') : '🔸');
	$pickup  = $order->get_meta('_raky_delivery_type') === 'pickup';
	$ready   = (string) $order->get_meta('_raky_ready_at');
	$date    = $ready === '' ? false : DateTimeImmutable::createFromFormat('Y-m-d H:i', $ready, wp_timezone());
	$lines   = [];
	$lines[] = sprintf('%s <b>Замовлення №%d · %s</b>', $icon, $order->get_id(), raky_tg_money((float) $order->get_total()));
	$lines[] = 'Статус: <b>' . raky_tg_escape(wc_get_order_status_name($status)) . '</b>';
	$lines[] = '';
	$lines[] = '👤 ' . raky_tg_escape(trim($order->get_billing_first_name() . ', ' . $order->get_billing_phone(), ', '));
	$lines[] = ($pickup ? '🏪 Самовивіз: ' : '🚚 Доставка: ') . raky_tg_escape($order->get_billing_address_1());
	$lines[] = '🕒 Готуємо на: ' . ($date ? $date->format('d.m H:i') : 'якнайшвидше');
	$lines[] = '💳 ' . raky_tg_escape($order->get_payment_method_title());
	$lines[] = '';
	foreach ($order->get_items() as $item) {
		$product = $item->get_product();
		$unit    = $product ? raky_product_unit($product) : 'pcs';
		$flavor  = (string) $item->get_meta('Смак');
		$lines[] = sprintf(
			'• %s%s — %s, %s',
			raky_tg_escape($item->get_name()),
			$flavor !== '' ? ', ' . raky_tg_escape(mb_strtolower($flavor)) : '',
			raky_quantity_label((float) $item->get_quantity(), $unit),
			raky_tg_money((float) $item->get_total())
		);
	}
	foreach ($order->get_coupon_codes() as $code) {
		$lines[] = '🏷 Промокод ' . raky_tg_escape(strtoupper($code));
	}
	if ((float) $order->get_discount_total() > 0) {
		$lines[] = 'Знижка: −' . raky_tg_money((float) $order->get_discount_total());
	}
	if (! $pickup) {
		$known   = $order->get_meta('_raky_zone_id') !== '' || (float) $order->get_shipping_total() > 0;
		$lines[] = 'Доставка: ' . ($known ? raky_tg_money((float) $order->get_shipping_total()) : 'вартість уточніть в адмінці');
	}
	if ($order->get_customer_note() !== '') {
		$lines[] = '💬 ' . raky_tg_escape($order->get_customer_note());
	}
	$weighed = false;
	foreach ($order->get_items() as $item) {
		$product = $item->get_product();
		$weighed = $weighed || ($product && raky_unit_weighed(raky_product_unit($product)));
	}
	if ($weighed && in_array($status, ['raky-new', 'raky-awaitpay'], true)) {
		$lines[] = '';
		$lines[] = '⚖️ Точну вагу й суму виправте в адмінці до підтвердження.';
	}
	$last = (string) $order->get_meta('_raky_tg_last');
	if ($last !== '') {
		$lines[] = '';
		$lines[] = '<i>' . raky_tg_escape($last) . '</i>';
	}
	return implode("\n", $lines);
}

/** Кнопки повідомлення. $confirm_cancel — друге питання перед скасуванням. */
function raky_tg_keyboard(WC_Order $order, bool $confirm_cancel = false): array {
	$id   = $order->get_id();
	$rows = [];
	if ($confirm_cancel) {
		$rows[] = [
			['text' => 'Так, скасувати', 'callback_data' => "st:$id:cancelled"],
			['text' => 'Ні, назад', 'callback_data' => "back:$id"],
		];
	} else {
		foreach (raky_tg_next($order) as $status => $label) {
			$rows[] = [['text' => $label, 'callback_data' => "st:$id:$status"]];
		}
		if (raky_tg_can_cancel($order)) {
			$rows[] = [['text' => '✖️ Скасувати', 'callback_data' => "cx:$id"]];
		}
	}
	// Telegram приймає в кнопках лише публічні https-адреси.
	$url = $order->get_edit_order_url();
	if (str_starts_with($url, 'https://')) {
		$rows[] = [['text' => 'Відкрити в адмінці', 'url' => $url]];
	}
	return ['inline_keyboard' => $rows];
}

function raky_tg_send_order(WC_Order $order) {
	$settings = raky_tg_settings();
	$sent     = raky_tg_api('sendMessage', [
		'chat_id'      => $settings['chat_id'],
		'text'         => raky_tg_order_text($order),
		'parse_mode'   => 'HTML',
		'reply_markup' => raky_tg_keyboard($order),
	]);
	if (is_wp_error($sent)) {
		$order->add_order_note('Telegram: не вдалося надіслати замовлення. ' . $sent->get_error_message());
		return $sent;
	}
	$order->update_meta_data('_raky_tg_chat', (string) $settings['chat_id']);
	$order->update_meta_data('_raky_tg_message', (string) $sent['message_id']);
	$order->save_meta_data();
	return $sent;
}

function raky_tg_refresh(WC_Order $order, bool $confirm_cancel = false): void {
	$chat    = (string) $order->get_meta('_raky_tg_chat');
	$message = (int) $order->get_meta('_raky_tg_message');
	if ($chat === '' || ! $message) {
		return;
	}
	// «message is not modified» та інші дрібні відмови не заважають роботі, тому без звіту.
	raky_tg_api('editMessageText', [
		'chat_id'      => $chat,
		'message_id'   => $message,
		'text'         => raky_tg_order_text($order),
		'parse_mode'   => 'HTML',
		'reply_markup' => raky_tg_keyboard($order, $confirm_cancel),
	]);
}

add_action('raky_order_created', static function (WC_Order $order) {
	if (raky_tg_ready()) {
		raky_tg_send_order($order);
	}
});

// Статус змінили в адмінці чи в Telegram — повідомлення показує новий.
add_action('woocommerce_order_status_changed', static function (int $order_id, string $from, string $to, WC_Order $order) {
	if (! $order->get_meta('_raky_tg_message')) {
		return;
	}
	global $raky_tg_actor;
	$who = $raky_tg_actor ?: (wp_get_current_user()->display_name ?: 'адмінка');
	$order->update_meta_data('_raky_tg_last', sprintf('%s → «%s», %s', $who, wc_get_order_status_name($to), wp_date('d.m H:i')));
	$order->save_meta_data();
	raky_tg_refresh($order);
}, 10, 4);

/** Обробляє одне оновлення Telegram: натискання кнопок у підключеному чаті. */
function raky_tg_handle_update(array $update): void {
	$query = $update['callback_query'] ?? null;
	if (! is_array($query)) {
		return;
	}
	$answer = static fn (string $text) => raky_tg_api('answerCallbackQuery', ['callback_query_id' => $query['id'], 'text' => $text]);
	$chat   = (string) ($query['message']['chat']['id'] ?? '');
	if ($chat === '' || $chat !== (string) raky_tg_settings()['chat_id']) {
		$answer('Цей чат не підключено до магазину.');
		return;
	}
	$parts = explode(':', (string) ($query['data'] ?? ''));
	$order = wc_get_order((int) ($parts[1] ?? 0));
	if (! $order || (int) $order->get_meta('_raky_tg_message') !== (int) ($query['message']['message_id'] ?? 0)) {
		$answer('Замовлення не знайдено.');
		return;
	}
	$who = trim(($query['from']['first_name'] ?? '') . ' ' . ($query['from']['last_name'] ?? '')) ?: 'Менеджер';

	switch ($parts[0]) {
		case 'cx':
			$can = raky_tg_can_cancel($order);
			raky_tg_refresh($order, $can);
			$answer($can ? 'Підтвердіть скасування.' : 'Скасувати вже не можна.');
			return;
		case 'back':
			raky_tg_refresh($order);
			$answer('');
			return;
		case 'st':
			$to      = (string) ($parts[2] ?? '');
			$allowed = array_keys(raky_tg_next($order));
			if (raky_tg_can_cancel($order)) {
				$allowed[] = 'cancelled';
			}
			// Кнопка з застарілого стану: статус уже змінили в адмінці чи інший менеджер.
			if (! in_array($to, $allowed, true)) {
				raky_tg_refresh($order);
				$answer('Статус уже змінено, повідомлення оновлено.');
				return;
			}
			global $raky_tg_actor;
			$raky_tg_actor = $who;
			$order->update_status($to, sprintf('Telegram: %s.', $who));
			$raky_tg_actor = null;
			$answer('Готово: ' . wc_get_order_status_name($to));
			return;
	}
	$answer('');
}

/**
 * Режим отримання натискань: webhook для https, інакше щохвилинне опитування.
 *
 * @return string|WP_Error webhook | polling
 */
function raky_tg_connect() {
	$url = rest_url(RAKY_NS . '/telegram');
	if (str_starts_with($url, 'https://')) {
		$secret = wp_generate_password(32, false);
		$result = raky_tg_api('setWebhook', ['url' => $url, 'secret_token' => $secret, 'allowed_updates' => ['callback_query']]);
		if (is_wp_error($result)) {
			return $result;
		}
		raky_tg_update(['mode' => 'webhook', 'secret' => $secret]);
		return 'webhook';
	}
	$result = raky_tg_api('deleteWebhook');
	if (is_wp_error($result)) {
		return $result;
	}
	raky_tg_update(['mode' => 'polling', 'secret' => '']);
	return 'polling';
}

add_action('rest_api_init', static function () {
	register_rest_route(RAKY_NS, '/telegram', [
		'methods'             => 'POST',
		'permission_callback' => static function (WP_REST_Request $request) {
			$secret = raky_tg_settings()['secret'];
			return $secret !== '' && hash_equals($secret, (string) $request->get_header('X-Telegram-Bot-Api-Secret-Token'));
		},
		'callback'            => static function (WP_REST_Request $request) {
			raky_tg_handle_update((array) $request->get_json_params());
			return ['ok' => true];
		},
	]);
});

/** Опитування без https: забирає натискання, що накопичилися з минулого разу. */
function raky_tg_poll(): void {
	if (get_transient('raky_tg_polling')) {
		return;
	}
	set_transient('raky_tg_polling', 1, 50);
	$updates = raky_tg_api('getUpdates', ['offset' => (int) raky_tg_settings()['offset'], 'timeout' => 0, 'allowed_updates' => ['callback_query']]);
	if (is_array($updates)) {
		foreach ($updates as $update) {
			raky_tg_update(['offset' => (int) $update['update_id'] + 1]);
			raky_tg_handle_update((array) $update);
		}
	}
	delete_transient('raky_tg_polling');
}

add_filter('cron_schedules', static function (array $schedules) {
	$schedules['raky_minute'] = ['interval' => MINUTE_IN_SECONDS, 'display' => 'Telegram: щохвилини'];
	return $schedules;
});

add_action(RAKY_TG_CRON, 'raky_tg_poll');

add_action('init', static function () {
	$needed    = raky_tg_ready() && raky_tg_settings()['mode'] === 'polling';
	$scheduled = wp_next_scheduled(RAKY_TG_CRON);
	if ($needed && ! $scheduled) {
		wp_schedule_event(time() + MINUTE_IN_SECONDS, 'raky_minute', RAKY_TG_CRON);
	} elseif (! $needed && $scheduled) {
		wp_clear_scheduled_hook(RAKY_TG_CRON);
	}
});

/**
 * Чати, куди додали бота або де йому написали. Працює лише без webhook, тому webhook знімається.
 *
 * @return array|WP_Error [chat_id => назва]
 */
function raky_tg_find_chats() {
	$removed = raky_tg_api('deleteWebhook');
	if (is_wp_error($removed)) {
		return $removed;
	}
	$updates = raky_tg_api('getUpdates', ['timeout' => 0, 'allowed_updates' => ['message', 'my_chat_member']]);
	if (is_wp_error($updates)) {
		return $updates;
	}
	$chats = [];
	foreach ($updates as $update) {
		$chat = $update['my_chat_member']['chat'] ?? $update['message']['chat'] ?? null;
		if (is_array($chat) && isset($chat['id'])) {
			$name = $chat['title'] ?? trim(($chat['first_name'] ?? '') . ' ' . ($chat['last_name'] ?? ''));
			$chats[(string) $chat['id']] = ($chat['type'] ?? '') === 'private' ? 'Особисто: ' . $name : $name;
		}
	}
	return $chats;
}

/** Форма в WooCommerce → Застосунок. @return array{0: ?string, 1: ?string, 2: array} [помилка, успіх, знайдені чати] */
function raky_tg_handle_form(string $action): array {
	$settings = raky_tg_settings();
	switch ($action) {
		case 'disconnect':
			if ($settings['token'] !== '') {
				raky_tg_api('deleteWebhook');
			}
			delete_option(RAKY_TG_OPTION);
			return [null, 'Telegram відключено.', []];

		case 'save':
			$token = trim((string) wp_unslash($_POST['tg_token'] ?? '')); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- токен зберігається як є
			if ($token !== '') {
				if (! preg_match('/^\d+:[\w-]{30,}$/', $token)) {
					return ['Це не схоже на токен бота. Він має вигляд 123456789:AA… і видається в @BotFather.', null, []];
				}
				$bot = raky_tg_api('getMe', [], $token);
				if (is_wp_error($bot)) {
					return [$bot->get_error_message(), null, []];
				}
				$settings = raky_tg_update(['token' => raky_sky_encrypt($token), 'bot' => (string) $bot['username'], 'chat_id' => '', 'chat_title' => '', 'mode' => '', 'offset' => 0]);
			}
			$chat = sanitize_text_field(wp_unslash((string) ($_POST['tg_chat'] ?? '')));
			if ($chat !== '' && $chat !== $settings['chat_id']) {
				$titles = (array) get_transient('raky_tg_chats');
				raky_tg_update(['chat_id' => $chat, 'chat_title' => (string) ($titles[$chat] ?? $chat)]);
				$mode = raky_tg_connect();
				if (is_wp_error($mode)) {
					return [$mode->get_error_message(), null, []];
				}
				$hello = raky_tg_api('sendMessage', ['chat_id' => $chat, 'text' => '✅ Raki Niko: сюди надходитимуть нові замовлення.']);
				if (is_wp_error($hello)) {
					return ['Чат збережено, але бот не зміг у нього написати: ' . $hello->get_error_message(), null, []];
				}
				return [null, 'Чат підключено. Бот надіслав у нього перевірочне повідомлення.', []];
			}
			return [null, $token !== '' ? 'Бот @' . $settings['bot'] . ' підключено. Тепер оберіть чат.' : 'Збережено.', []];

		case 'find':
			$chats = raky_tg_find_chats();
			if (is_wp_error($chats)) {
				return [$chats->get_error_message(), null, []];
			}
			if (! $chats) {
				return ['Бот ще не бачить жодного чату. Додайте його в групу менеджерів або напишіть йому /start і натисніть «Знайти чат» знову.', null, []];
			}
			set_transient('raky_tg_chats', $chats, HOUR_IN_SECONDS);
			// Пошук чатів зняв webhook: повертаємо його, якщо чат уже був.
			if ($settings['chat_id'] !== '') {
				raky_tg_connect();
			}
			return [null, null, $chats];

		case 'test':
			$orders = wc_get_orders(['limit' => 1, 'created_via' => 'mobile-app', 'orderby' => 'date', 'order' => 'DESC']);
			if (! $orders) {
				$sent = raky_tg_api('sendMessage', ['chat_id' => $settings['chat_id'], 'text' => '✅ Raki Niko: зв\'язок із ботом працює. Замовлень ще немає.']);
			} else {
				$sent = raky_tg_send_order($orders[0]);
			}
			return is_wp_error($sent) ? [$sent->get_error_message(), null, []] : [null, $orders ? 'Останнє замовлення надіслано в чат, кнопки в ньому робочі.' : 'Тестове повідомлення надіслано.', []];
	}
	return [null, null, []];
}

function raky_render_tg_section(?array $chats): void {
	$settings = raky_tg_settings();
	$chats    = $chats ?: [];
	?>
	<h2>Telegram-бот для замовлень</h2>
	<p>Нові замовлення приходять у чат менеджерів. Статус змінюється кнопками під замовленням, а повідомлення саме показує, хто й коли його змінив.</p>
	<?php if ($settings['token'] === '' || $settings['chat_id'] === '') : ?>
		<ol>
			<li>У Telegram відкрийте <strong>@BotFather</strong>, надішліть <code>/newbot</code>, придумайте назву й скопіюйте токен.</li>
			<li>Вставте токен нижче й натисніть «Зберегти».</li>
			<li>Додайте бота в групу менеджерів. Для особистого чату просто напишіть боту <code>/start</code>.</li>
			<li>Натисніть «Знайти чат», оберіть його й збережіть.</li>
		</ol>
	<?php endif; ?>
	<form method="post">
		<?php wp_nonce_field('raky_telegram'); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="tg_token">Токен бота</label></th>
				<td>
					<input id="tg_token" name="tg_token" type="password" class="regular-text" autocomplete="new-password" placeholder="<?php echo esc_attr($settings['bot'] !== '' ? 'Збережено: @' . $settings['bot'] . '. Порожнє поле лишає його' : '123456789:AA…'); ?>">
					<p class="description">Токен зберігається зашифрованим.</p>
				</td>
			</tr>
			<?php if ($settings['bot'] !== '') : ?>
				<tr>
					<th scope="row"><label for="tg_chat">Чат менеджерів</label></th>
					<td>
						<?php if ($chats) : ?>
							<select id="tg_chat" name="tg_chat">
								<?php foreach ($chats as $id => $title) : ?>
									<option value="<?php echo esc_attr((string) $id); ?>" <?php selected((string) $id, $settings['chat_id']); ?>><?php echo esc_html($title); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description">Оберіть чат і натисніть «Зберегти».</p>
						<?php elseif ($settings['chat_id'] !== '') : ?>
							<strong><?php echo esc_html($settings['chat_title']); ?></strong>
							<p class="description">
								<?php echo esc_html($settings['mode'] === 'webhook' ? 'Натискання кнопок приходять миттєво.' : 'Сайт без https, тому натискання кнопок обробляються раз на хвилину. На хостингу з https — миттєво.'); ?>
							</p>
						<?php else : ?>
							Ще не вибрано. Додайте бота в чат і натисніть «Знайти чат».
						<?php endif; ?>
					</td>
				</tr>
			<?php endif; ?>
		</table>
		<p>
			<button class="button button-primary" name="raky_tg_action" value="save">Зберегти</button>
			<?php if ($settings['bot'] !== '') : ?>
				<button class="button" name="raky_tg_action" value="find">Знайти чат</button>
			<?php endif; ?>
			<?php if (raky_tg_ready()) : ?>
				<button class="button" name="raky_tg_action" value="test">Надіслати перевірку</button>
			<?php endif; ?>
			<?php if ($settings['token'] !== '') : ?>
				<button class="button-link button-link-delete" name="raky_tg_action" value="disconnect" style="margin-left:10px" onclick="return confirm('Відключити Telegram? Замовлення перестануть надходити в чат.')">Відключити</button>
			<?php endif; ?>
		</p>
	</form>
	<?php
}
