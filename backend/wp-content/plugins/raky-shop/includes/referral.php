<?php
defined('ABSPATH') || exit;

/**
 * Запрошення друзів.
 * Кожен клієнт (за телефоном) має особистий код — купон WooCommerce лише на перше замовлення, один раз на телефон.
 * Друг вводить код і отримує знижку. Коли замовлення друга стає «Виконано», власник коду отримує
 * свій бонус: окремий одноразовий купон, який спрацює лише з його телефону. Скасоване замовлення бонусу не дає.
 * Відсоток і перемикач — у «WooCommerce → Застосунок».
 */

const RAKY_REF_OWNER      = '_raky_ref_owner';      // купон-запрошення: телефон власника 380XXXXXXXXX
const RAKY_REF_REWARD_FOR = '_raky_ref_reward_for'; // бонусний купон: телефон, якому він належить
const RAKY_REF_FROM_ORDER = '_raky_ref_order';      // бонусний купон: замовлення друга, за яке його видано
const RAKY_REF_REWARDED   = '_raky_ref_rewarded';   // замовлення: код уже виданого бонусу
const RAKY_REF_RATE_LIMIT = 30;

function raky_ref_on(): bool {
	return (bool) raky_get_settings()['referral_on'];
}

function raky_ref_percent(): float {
	return max(0.0, min(100.0, (float) raky_get_settings()['referral_pct']));
}

/** Код без схожих символів (0/O, 1/I), щоб його легко було продиктувати. */
function raky_ref_new_code(string $prefix): string {
	$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
	do {
		$code = $prefix;
		for ($i = 0; $i < 5; ++$i) {
			$code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
		}
	} while (wc_get_coupon_id_by_code($code));
	return $code;
}

/** @return WC_Coupon[] Купони з цією позначкою для телефону. */
function raky_ref_coupons(string $meta, string $phone): array {
	$ids = get_posts([
		'post_type'   => 'shop_coupon',
		'post_status' => 'publish',
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_key'    => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery
		'meta_value'  => $phone, // phpcs:ignore WordPress.DB.SlowDBQuery
		'orderby'     => 'ID',
		'order'       => 'ASC',
	]);
	return array_map(static fn (int $id) => new WC_Coupon($id), $ids);
}

/** Відсоток у купоні йде за налаштуванням: змінили в адмінці — змінилося й у вже виданих кодах. */
function raky_ref_sync_amount(WC_Coupon $coupon): void {
	if ((float) $coupon->get_amount() !== raky_ref_percent()) {
		$coupon->set_amount(raky_ref_percent());
		$coupon->save();
	}
}

/** Особистий код клієнта; створюється під час першого звернення. */
function raky_ref_code_for(string $phone): WC_Coupon {
	$existing = raky_ref_coupons(RAKY_REF_OWNER, $phone);
	if ($existing) {
		raky_ref_sync_amount($existing[0]);
		return $existing[0];
	}
	$coupon = new WC_Coupon();
	$coupon->set_code(raky_ref_new_code('RN'));
	$coupon->set_discount_type('percent');
	$coupon->set_amount(raky_ref_percent());
	$coupon->set_usage_limit_per_user(1);
	$coupon->set_description('Запрошення друзів: особистий код клієнта ' . $phone . '. Створено застосунком.');
	$coupon->update_meta_data(RAKY_FIRST_ORDER_META, 'yes');
	$coupon->update_meta_data(RAKY_REF_OWNER, $phone);
	$coupon->save();
	return $coupon;
}

/** Бонус ще можна використати: не було нескасованого замовлення з ним. */
function raky_ref_bonus_open(WC_Coupon $coupon, string $phone): bool {
	return raky_coupon_uses_by_phone($coupon->get_code(), $phone) === 0
		&& ($coupon->get_usage_limit() === 0 || $coupon->get_usage_count() < $coupon->get_usage_limit());
}

/**
 * Додаткові правила для кодів запрошення й бонусів. Викликається з raky_coupon_discount.
 * Без телефону (гість у кошику) перевірка відбудеться під час оформлення, там телефон обов'язковий.
 */
function raky_ref_check(WC_Coupon $coupon, string $phone): ?WP_Error {
	$owner = (string) $coupon->get_meta(RAKY_REF_OWNER);
	$for   = (string) $coupon->get_meta(RAKY_REF_REWARD_FOR);
	if ($owner === '' && $for === '') {
		return null;
	}
	if (! raky_ref_on()) {
		return raky_error('raky_bad_promo', 'Запрошення друзів зараз не діє.', 422);
	}
	raky_ref_sync_amount($coupon);
	$client = raky_phone_380($phone);
	if ($client === '') {
		return null;
	}
	if ($owner !== '' && $owner === $client) {
		return raky_error('raky_bad_promo', 'Це ваш особистий код. Надішліть його другу — знижку отримає він, а потім і ви.', 422);
	}
	if ($for !== '' && $for !== $client) {
		return raky_error('raky_bad_promo', 'Цей бонус належить іншому номеру телефону.', 422);
	}
	return null;
}

/** Замовлення друга виконано — власник коду отримує бонус. Один раз на замовлення. */
add_action('woocommerce_order_status_completed', static function (int $order_id) {
	$order = wc_get_order($order_id);
	if (! $order || $order->get_meta(RAKY_REF_REWARDED) !== '' || ! raky_ref_on()) {
		return;
	}
	foreach ($order->get_coupon_codes() as $code) {
		$invite = new WC_Coupon($code);
		$owner  = (string) $invite->get_meta(RAKY_REF_OWNER);
		if ($owner === '' || $owner === raky_phone_380($order->get_billing_phone())) {
			continue;
		}
		$bonus = new WC_Coupon();
		$bonus->set_code(raky_ref_new_code('BONUS'));
		$bonus->set_discount_type('percent');
		$bonus->set_amount(raky_ref_percent());
		$bonus->set_usage_limit(1);
		$bonus->set_usage_limit_per_user(1);
		$bonus->set_description(sprintf('Запрошення друзів: бонус для %s за замовлення №%d. Спрацює лише з цього телефону.', $owner, $order_id));
		$bonus->update_meta_data(RAKY_REF_REWARD_FOR, $owner);
		$bonus->update_meta_data(RAKY_REF_FROM_ORDER, $order_id);
		$bonus->save();
		$order->update_meta_data(RAKY_REF_REWARDED, $bonus->get_code());
		$order->add_order_note(sprintf('Запрошення друзів: власник коду %s отримав бонус %s (−%s%%).', strtoupper($code), $bonus->get_code(), raky_ref_percent()));
		$order->save();
		return;
	}
});

/** Особистий код і бонуси клієнта. Телефон у тілі запиту, не в адресі. */
function raky_rest_referral(WP_REST_Request $request) {
	if (! raky_ref_on()) {
		return ['enabled' => false, 'percent' => raky_ref_percent(), 'code' => null, 'bonuses' => [], 'friends' => 0];
	}
	if (raky_rate_limited('referral', RAKY_REF_RATE_LIMIT)) {
		return raky_error('raky_rate_limited', 'Забагато спроб. Спробуйте за кілька хвилин.', 429);
	}
	$phone = raky_phone_380((string) $request->get_param('phone'));
	if ($phone === '') {
		return raky_error('raky_bad_phone', 'Вкажіть український номер телефону.');
	}
	$rewards = raky_ref_coupons(RAKY_REF_REWARD_FOR, $phone);
	$bonuses = [];
	foreach ($rewards as $bonus) {
		if (raky_ref_bonus_open($bonus, $phone)) {
			raky_ref_sync_amount($bonus);
			$bonuses[] = ['code' => strtoupper($bonus->get_code()), 'percent' => (float) $bonus->get_amount()];
		}
	}
	return [
		'enabled' => true,
		'percent' => raky_ref_percent(),
		'code'    => strtoupper(raky_ref_code_for($phone)->get_code()),
		'bonuses' => $bonuses,
		'friends' => count($rewards),
	];
}
