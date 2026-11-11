<?php
defined('ABSPATH') || exit;

/**
 * Клієнти магазину — користувачі WordPress з роллю «Клієнт» (customer).
 * Логін — телефон у вигляді 380XXXXXXXXX. Телефон лежить і в стандартному полі WooCommerce billing_phone.
 * Свої поля: день народження, номер картки зі старого застосунку, позначка «подарунок на перше замовлення використано».
 */

const RAKY_CLIENT_BIRTHDAY   = 'raky_birthday';
const RAKY_CLIENT_CARD       = 'raky_card_number';
const RAKY_CLIENT_FIRST_GIFT = 'raky_first_gift_used';

/** Телефон у вигляді 380XXXXXXXXX або '' для неукраїнського номера. */
function raky_phone_380(string $phone): string {
	$digits = (string) preg_replace('/\D/', '', $phone);
	if (strlen($digits) === 12 && str_starts_with($digits, '380')) {
		return $digits;
	}
	if (strlen($digits) === 10 && $digits[0] === '0') {
		return '38' . $digits;
	}
	return strlen($digits) === 9 ? '380' . $digits : '';
}

/** Клієнт із цим телефоном, хоч би як його записали. */
function raky_client_by_phone(string $phone): ?WP_User {
	$variants = raky_phone_variants($phone);
	if (! $variants) {
		return null;
	}
	$user = get_user_by('login', $variants[1]);
	if ($user) {
		return $user;
	}
	$users = get_users(['meta_key' => 'billing_phone', 'meta_value' => $variants, 'meta_compare' => 'IN', 'number' => 1]);
	return $users[0] ?? null;
}

/** Чи вже був у клієнта перший заказ: замовлення в застосунку або позначка в профілі (зі старого застосунку чи вручну). */
function raky_client_had_first_order(string $phone): bool {
	if (raky_orders_by_phone($phone)) {
		return true;
	}
	$user = raky_client_by_phone($phone);
	return $user && get_user_meta($user->ID, RAKY_CLIENT_FIRST_GIFT, true) === 'yes';
}

/** День народження лише правдоподібний: старий застосунок підставляв поточну дату. */
function raky_clean_birthday(string $date): string {
	if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
		return '';
	}
	$year = (int) $m[1];
	return $year >= 1920 && $year <= (int) wp_date('Y') - 5 ? $date : '';
}

/**
 * Імпорт клієнтів зі старого застосунку (експорт Firebase: {телефон: {name, surname, email, birthday, city, card_number, isFirstOrderGiftUsed}}).
 * Повторний запуск оновлює наявних клієнтів і не знімає позначку подарунка, якщо її поставили раніше.
 *
 * @return array{created: int, updated: int, skipped: string[]}
 */
function raky_import_clients(array $records): array {
	$stats = ['created' => 0, 'updated' => 0, 'skipped' => []];
	foreach ($records as $key => $record) {
		if (! is_array($record)) {
			continue;
		}
		$raw   = (string) ($record['phoneNumber'] ?? $key);
		$phone = raky_phone_380($raw);
		if ($phone === '') {
			$stats['skipped'][] = 'не український номер: ' . substr($raw, 0, 5) . '…';
			continue;
		}
		$first = trim((string) ($record['name'] ?? ''));
		$last  = trim((string) ($record['surname'] ?? ''));
		$email = sanitize_email((string) ($record['email'] ?? ''));
		if ($email !== '' && ($owner = email_exists($email)) && get_userdata($owner)->user_login !== $phone) {
			$email = ''; // така пошта вже в іншого користувача
		}

		$user = raky_client_by_phone($phone);
		if ($user) {
			$id = $user->ID;
			++$stats['updated'];
		} else {
			$id = wp_insert_user([
				'user_login'   => $phone,
				'user_pass'    => wp_generate_password(24),
				'user_email'   => $email,
				'first_name'   => $first,
				'last_name'    => $last,
				'display_name' => trim($first . ' ' . $last) ?: $phone,
				'role'         => 'customer',
			]);
			if (is_wp_error($id)) {
				$stats['skipped'][] = substr($phone, 0, 5) . '…: ' . $id->get_error_message();
				continue;
			}
			++$stats['created'];
		}

		update_user_meta($id, 'billing_phone', $phone);
		update_user_meta($id, 'billing_country', 'UA');
		foreach (['billing_first_name' => $first, 'billing_last_name' => $last, 'billing_email' => $email, 'billing_city' => trim((string) ($record['city'] ?? ''))] as $meta => $value) {
			if ($value !== '' && get_user_meta($id, $meta, true) === '') {
				update_user_meta($id, $meta, $value);
			}
		}
		$birthday = raky_clean_birthday((string) ($record['birthday'] ?? ''));
		if ($birthday !== '') {
			update_user_meta($id, RAKY_CLIENT_BIRTHDAY, $birthday);
		}
		$card = preg_replace('/\D/', '', (string) ($record['card_number'] ?? ''));
		if ($card !== '') {
			update_user_meta($id, RAKY_CLIENT_CARD, $card);
		}
		// Картку старий застосунок видавав тим, хто вже купував, тож і вони — не нові клієнти.
		if (! empty($record['isFirstOrderGiftUsed']) || $card !== '') {
			update_user_meta($id, RAKY_CLIENT_FIRST_GIFT, 'yes');
		}
		update_user_meta($id, 'raky_imported', wp_date('Y-m-d'));
	}
	return $stats;
}

// Поля клієнта в профілі: «Користувачі → Редагувати».
$raky_client_fields = static function (WP_User $user): void {
	if (! in_array('customer', (array) $user->roles, true)) {
		return;
	}
	wp_nonce_field('raky_client_' . $user->ID, 'raky_client_nonce');
	?>
	<h2>Raki Niko</h2>
	<table class="form-table">
		<tr><th>Телефон</th><td><?php echo esc_html((string) get_user_meta($user->ID, 'billing_phone', true)); ?> <span class="description">— змінюється в блоці «Платіжна адреса» нижче.</span></td></tr>
		<tr><th><label for="raky_birthday">День народження</label></th><td><input type="date" id="raky_birthday" name="raky_birthday" value="<?php echo esc_attr((string) get_user_meta($user->ID, RAKY_CLIENT_BIRTHDAY, true)); ?>"></td></tr>
		<tr><th><label for="raky_card_number">Картка зі старого застосунку</label></th><td><input type="text" id="raky_card_number" name="raky_card_number" class="regular-text" value="<?php echo esc_attr((string) get_user_meta($user->ID, RAKY_CLIENT_CARD, true)); ?>"></td></tr>
		<tr><th>Перше замовлення</th><td><label><input type="checkbox" name="raky_first_gift_used" value="1" <?php checked(get_user_meta($user->ID, RAKY_CLIENT_FIRST_GIFT, true), 'yes'); ?>> Подарунок на перше замовлення вже використано</label>
			<p class="description">З позначкою промокоди «Лише на перше замовлення» для цього телефону не спрацюють, навіть якщо замовлень у застосунку ще немає.</p></td></tr>
	</table>
	<?php
};
add_action('show_user_profile', $raky_client_fields);
add_action('edit_user_profile', $raky_client_fields);

$raky_save_client = static function (int $user_id): void {
	$nonce = $_POST['raky_client_nonce'] ?? '';
	if (! is_string($nonce) || ! wp_verify_nonce($nonce, 'raky_client_' . $user_id) || ! current_user_can('edit_user', $user_id)) {
		return;
	}
	update_user_meta($user_id, RAKY_CLIENT_BIRTHDAY, raky_clean_birthday(sanitize_text_field(wp_unslash((string) ($_POST['raky_birthday'] ?? '')))));
	update_user_meta($user_id, RAKY_CLIENT_CARD, preg_replace('/\D/', '', (string) wp_unslash($_POST['raky_card_number'] ?? '')));
	update_user_meta($user_id, RAKY_CLIENT_FIRST_GIFT, empty($_POST['raky_first_gift_used']) ? 'no' : 'yes');
};
add_action('personal_options_update', $raky_save_client);
add_action('edit_user_profile_update', $raky_save_client);

// Колонки в списку «Користувачі».
add_filter('manage_users_columns', static function (array $columns): array {
	$columns['raky_phone']    = 'Телефон';
	$columns['raky_birthday'] = 'День народження';
	$columns['raky_first']    = 'Перше замовлення';
	return $columns;
});

add_filter('manage_users_custom_column', static function ($output, string $column, int $user_id) {
	switch ($column) {
		case 'raky_phone':
			return esc_html((string) get_user_meta($user_id, 'billing_phone', true));
		case 'raky_birthday':
			$date = (string) get_user_meta($user_id, RAKY_CLIENT_BIRTHDAY, true);
			return $date === '' ? '—' : esc_html(wp_date('d.m.Y', strtotime($date)));
		case 'raky_first':
			$phone = (string) get_user_meta($user_id, 'billing_phone', true);
			return $phone !== '' && raky_client_had_first_order($phone) ? 'вже було' : '—';
	}
	return $output;
}, 10, 3);
