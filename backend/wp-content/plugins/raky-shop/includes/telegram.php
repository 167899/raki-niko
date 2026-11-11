<?php
defined('ABSPATH') || exit;

/**
 * Telegram-бот для менеджерів: нове замовлення приходить у чат із кнопками наступного кроку.
 *
 * Одне повідомлення на замовлення. Воно оновлюється за будь-якої зміни статусу,
 * з Telegram чи з адмінки, і завжди показує поточний статус, суму й кнопки.
 * Натискання приймаються лише з підключеного чату.
 *
 * З того ж чату менеджери додають новини в застосунок: кнопка «Меню» → /news.
 *
 * Сайт на https отримує натискання миттєво (webhook). Без https, як на локальному сервері,
 * плагін сам забирає їх щохвилини (getUpdates у WP-Cron).
 */

const RAKY_TG_OPTION = 'raky_telegram';
const RAKY_TG_CRON   = 'raky_telegram_poll';
const RAKY_TG_HEALTH = 'raky_telegram_health';
const RAKY_TG_API    = 'https://api.telegram.org/bot';
const RAKY_TG_SETUP  = 2; // піднімається, коли змінюються команди меню чи типи оновлень
const RAKY_TG_UPDATES = ['callback_query', 'message'];
const RAKY_TG_NEWS_PROMPT = '📰 Нова новина для застосунку.';

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
			'setup'      => 0,  // версія налаштувань бота (команди меню, типи оновлень)
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

/** Обробляє одне оновлення Telegram: повідомлення й натискання кнопок у підключеному чаті. */
function raky_tg_handle_update(array $update): void {
	if (isset($update['message']) && is_array($update['message'])) {
		raky_tg_handle_message($update['message']);
		return;
	}
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
	if ($parts[0] === 'nw') {
		$answer(raky_tg_news_button($query, $parts));
		return;
	}
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

/** Повідомлення в підключеному чаті: команда /news і відповіді на запит бота. */
function raky_tg_handle_message(array $message): void {
	$settings = raky_tg_settings();
	$chat     = (string) ($message['chat']['id'] ?? '');
	if ($chat === '' || $chat !== (string) $settings['chat_id']) {
		return;
	}
	$text = trim((string) ($message['text'] ?? $message['caption'] ?? ''));
	if (preg_match('~^/news(?:@(\w+))?(?:\s+(.*))?$~su', $text, $m)) {
		if (($m[1] ?? '') !== '' && strcasecmp($m[1], $settings['bot']) !== 0) {
			return; // команда іншому боту
		}
		if (trim($m[2] ?? '') !== '') {
			raky_tg_news_draft($message, $m[2]);
			return;
		}
		raky_tg_api('sendMessage', [
			'chat_id'          => $chat,
			'text'             => RAKY_TG_NEWS_PROMPT . "\n\nНапишіть її у відповідь на це повідомлення: перший рядок — заголовок, далі — текст. Перед публікацією бот покаже, як вона виглядатиме.",
			'reply_parameters' => ['message_id' => (int) $message['message_id'], 'allow_sending_without_reply' => true],
			'reply_markup'     => ['force_reply' => true, 'selective' => true, 'input_field_placeholder' => 'Заголовок, з нового рядка — текст'],
		]);
		return;
	}
	$asked = $message['reply_to_message'] ?? null;
	if (! is_array($asked) || ! str_starts_with((string) ($asked['text'] ?? ''), RAKY_TG_NEWS_PROMPT)
		|| strcasecmp((string) ($asked['from']['username'] ?? ''), $settings['bot']) !== 0) {
		return;
	}
	if ($text === '') {
		raky_tg_api('sendMessage', [
			'chat_id'          => $chat,
			'text'             => 'Новини в застосунку поки лише текстові. Надішліть текст командою /news ще раз.',
			'reply_parameters' => ['message_id' => (int) $message['message_id'], 'allow_sending_without_reply' => true],
		]);
		return;
	}
	raky_tg_news_draft($message, $text);
}

/** Чернетка новини з тексту менеджера і повідомлення з кнопками «Опублікувати» / «Скасувати». */
function raky_tg_news_draft(array $message, string $text): void {
	$lines = preg_split('/\R/u', trim($text));
	$title = mb_substr(trim((string) array_shift($lines)), 0, 120);
	$body  = trim(implode("\n", $lines));
	$who   = raky_tg_who($message['from'] ?? []);
	$id    = wp_insert_post([
		'post_type'    => 'raky_news',
		'post_status'  => 'draft',
		'post_title'   => $title,
		'post_excerpt' => $body,
	], true);
	if (is_wp_error($id)) {
		raky_tg_api('sendMessage', ['chat_id' => $message['chat']['id'], 'text' => 'Не вдалося зберегти новину: ' . $id->get_error_message()]);
		return;
	}
	update_post_meta($id, '_raky_tg_last', 'Чернетка від ' . $who . '. Так новина виглядатиме в застосунку.');
	$post = get_post($id);
	$sent = raky_tg_api('sendMessage', [
		'chat_id'          => $message['chat']['id'],
		'text'             => raky_tg_news_text($post),
		'parse_mode'       => 'HTML',
		'reply_parameters' => ['message_id' => (int) $message['message_id'], 'allow_sending_without_reply' => true],
		'reply_markup'     => raky_tg_news_keyboard($post),
	]);
	if (! is_wp_error($sent)) {
		update_post_meta($id, '_raky_tg_message', (int) $sent['message_id']);
	}
}

function raky_tg_who(array $from): string {
	return trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? '')) ?: 'Менеджер';
}

function raky_tg_news_text(WP_Post $post): string {
	$icon  = ['draft' => '📝', 'publish' => '✅', 'trash' => '✖️'][$post->post_status] ?? '📰';
	$lines = [$icon . ' <b>' . raky_tg_escape($post->post_title) . '</b>'];
	if ($post->post_excerpt !== '') {
		$lines[] = '';
		$lines[] = raky_tg_escape($post->post_excerpt);
	}
	$last = (string) get_post_meta($post->ID, '_raky_tg_last', true);
	if ($last !== '') {
		$lines[] = '';
		$lines[] = '<i>' . raky_tg_escape($last) . '</i>';
	}
	return implode("\n", $lines);
}

function raky_tg_news_keyboard(WP_Post $post): array {
	$id   = $post->ID;
	$rows = [];
	if ($post->post_status === 'draft') {
		$rows[] = [['text' => '✅ Опублікувати', 'callback_data' => "nw:$id:publish"], ['text' => '✖️ Скасувати', 'callback_data' => "nw:$id:cancel"]];
	} elseif ($post->post_status === 'publish') {
		$rows[] = [['text' => '🗑 Прибрати із застосунку', 'callback_data' => "nw:$id:remove"]];
	}
	$url = admin_url('post.php?post=' . $id . '&action=edit');
	if ($post->post_status !== 'trash' && str_starts_with($url, 'https://')) {
		$rows[] = [['text' => 'Відкрити в адмінці', 'url' => $url]];
	}
	return ['inline_keyboard' => $rows];
}

/** Кнопки під новиною. @return string відповідь на натискання */
function raky_tg_news_button(array $query, array $parts): string {
	$post = get_post((int) ($parts[1] ?? 0));
	if (! $post || $post->post_type !== 'raky_news' || (int) get_post_meta($post->ID, '_raky_tg_message', true) !== (int) ($query['message']['message_id'] ?? 0)) {
		return 'Новину не знайдено.';
	}
	$action = (string) ($parts[2] ?? '');
	$from   = ['publish' => 'draft', 'cancel' => 'draft', 'remove' => 'publish'][$action] ?? '';
	$refresh = static function (WP_Post $post) use ($query): void {
		raky_tg_api('editMessageText', [
			'chat_id'      => $query['message']['chat']['id'],
			'message_id'   => $query['message']['message_id'],
			'text'         => raky_tg_news_text($post),
			'parse_mode'   => 'HTML',
			'reply_markup' => raky_tg_news_keyboard($post),
		]);
	};
	// Кнопка з застарілого стану: новину вже змінили в адмінці чи інший менеджер.
	if ($from === '' || $post->post_status !== $from) {
		$refresh($post);
		return 'Новину вже змінено, повідомлення оновлено.';
	}
	$when = sprintf('%s, %s', raky_tg_who($query['from'] ?? []), wp_date('d.m H:i'));
	if ($action === 'publish') {
		wp_update_post(['ID' => $post->ID, 'post_status' => 'publish', 'post_date' => current_time('mysql'), 'post_date_gmt' => current_time('mysql', true)]);
		update_post_meta($post->ID, '_raky_tg_last', 'Опубліковано в застосунку: ' . $when);
		$answer = 'Новину опубліковано.';
	} else {
		wp_trash_post($post->ID);
		update_post_meta($post->ID, '_raky_tg_last', ($action === 'cancel' ? 'Скасовано: ' : 'Прибрано із застосунку: ') . $when);
		$answer = $action === 'cancel' ? 'Скасовано.' : 'Новину прибрано.';
	}
	$refresh(get_post($post->ID));
	return $answer;
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
		$result = raky_tg_api('setWebhook', ['url' => $url, 'secret_token' => $secret, 'allowed_updates' => RAKY_TG_UPDATES]);
		if (is_wp_error($result)) {
			return $result;
		}
		raky_tg_update(['mode' => 'webhook', 'secret' => $secret]);
		raky_tg_set_commands();
		return 'webhook';
	}
	$result = raky_tg_api('deleteWebhook');
	if (is_wp_error($result)) {
		return $result;
	}
	raky_tg_update(['mode' => 'polling', 'secret' => '']);
	raky_tg_set_commands();
	return 'polling';
}

/** Кнопка «Меню» в чаті менеджерів. Команди видно лише в цьому чаті. */
function raky_tg_set_commands(): void {
	$chat = raky_tg_settings()['chat_id'];
	if ($chat === '') {
		return;
	}
	$result = raky_tg_api('setMyCommands', [
		'commands' => [['command' => 'news', 'description' => 'Додати новину в застосунок']],
		'scope'    => ['type' => 'chat', 'chat_id' => $chat],
	]);
	if (! is_wp_error($result)) {
		raky_tg_update(['setup' => RAKY_TG_SETUP]);
	}
}

// Бот, підключений до появи новин, оновлюється сам при першому відкритті адмінки.
add_action('admin_init', static function () {
	$settings = raky_tg_settings();
	if (raky_tg_ready() && (int) $settings['setup'] < RAKY_TG_SETUP && ! get_transient('raky_tg_setup_try')) {
		set_transient('raky_tg_setup_try', 1, 10 * MINUTE_IN_SECONDS);
		raky_tg_connect();
	}
});

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
	$updates = raky_tg_api('getUpdates', ['offset' => (int) raky_tg_settings()['offset'], 'timeout' => 0, 'allowed_updates' => RAKY_TG_UPDATES]);
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
	$schedules['raky_5min']   = ['interval' => 5 * MINUTE_IN_SECONDS, 'display' => 'Telegram: раз на 5 хвилин'];
	return $schedules;
});

add_action(RAKY_TG_CRON, 'raky_tg_poll');

/**
 * Раз на 5 хвилин: чи Telegram досі шле натискання на сайт. Webhook могли зняти
 * (пошук чатів, інший сервер з тим самим ботом) — тоді він ставиться знову.
 */
function raky_tg_check_webhook(): void {
	if (! raky_tg_ready() || raky_tg_settings()['mode'] !== 'webhook') {
		return;
	}
	$info = raky_tg_api('getWebhookInfo');
	if (is_wp_error($info)) {
		return;
	}
	$broken = ($info['url'] ?? '') !== rest_url(RAKY_NS . '/telegram') || array_diff(RAKY_TG_UPDATES, (array) ($info['allowed_updates'] ?? []));
	if ($broken) {
		$result = raky_tg_connect();
		raky_tg_update(['error' => is_wp_error($result) ? 'Не вдалося відновити зв\'язок із Telegram: ' . $result->get_error_message() : '']);
	}
}
add_action(RAKY_TG_HEALTH, 'raky_tg_check_webhook');

add_action('init', static function () {
	$needed    = raky_tg_ready() && raky_tg_settings()['mode'] === 'polling';
	$scheduled = wp_next_scheduled(RAKY_TG_CRON);
	if ($needed && ! $scheduled) {
		wp_schedule_event(time() + MINUTE_IN_SECONDS, 'raky_minute', RAKY_TG_CRON);
	} elseif (! $needed && $scheduled) {
		wp_clear_scheduled_hook(RAKY_TG_CRON);
	}
	$watch = raky_tg_ready() && raky_tg_settings()['mode'] === 'webhook';
	$checking = wp_next_scheduled(RAKY_TG_HEALTH);
	if ($watch && ! $checking) {
		wp_schedule_event(time() + 5 * MINUTE_IN_SECONDS, 'raky_5min', RAKY_TG_HEALTH);
	} elseif (! $watch && $checking) {
		wp_clear_scheduled_hook(RAKY_TG_HEALTH);
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
			// Пошук чатів знімає webhook: без нього кнопки під замовленнями перестають працювати.
			if ($settings['chat_id'] !== '') {
				raky_tg_connect();
			}
			if (is_wp_error($chats)) {
				return [$chats->get_error_message(), null, []];
			}
			if (! $chats) {
				// Telegram пам'ятає події лише добу, а звичайні повідомлення групи бот не бачить, лише команди.
				$command = '/start' . ($settings['bot'] !== '' ? '@' . $settings['bot'] : '');
				return ['Бот ще не бачить жодного чату. Напишіть у групі менеджерів ' . $command . ' (або видаліть бота з групи й додайте знову) і натисніть «Знайти чат» ще раз.', null, []];
			}
			set_transient('raky_tg_chats', $chats, HOUR_IN_SECONDS);
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
	<p>З того ж чату можна додавати новини в застосунок: кнопка «Меню» → <code>/news</code>, далі бот підкаже.</p>
	<?php if ($settings['error'] !== '') : ?>
		<div class="notice notice-error inline"><p><?php echo esc_html($settings['error']); ?></p></div>
	<?php endif; ?>
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
