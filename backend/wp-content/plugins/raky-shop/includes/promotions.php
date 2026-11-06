<?php
defined('ABSPATH') || exit;

/**
 * Банери, новини застосунку та перевірка промокодів.
 * Умови знижок задаються у стандартних купонах WooCommerce:
 * сума або відсоток, термін, ліміти, «Лише окреме використання» = не підсумовувати.
 */

add_action('init', static function () {
	register_post_type('raky_banner', [
		'labels'       => ['name' => 'Банери', 'singular_name' => 'Банер', 'add_new' => 'Додати банер', 'add_new_item' => 'Новий банер', 'edit_item' => 'Редагувати банер', 'menu_name' => 'Банери'],
		'public'       => false,
		'show_ui'      => true,
		'show_in_menu' => 'woocommerce',
		'supports'     => ['title', 'thumbnail', 'page-attributes'],
	]);
	register_post_type('raky_news', [
		'labels'       => ['name' => 'Новини застосунку', 'singular_name' => 'Новина', 'add_new' => 'Додати новину', 'add_new_item' => 'Нова новина', 'edit_item' => 'Редагувати новину', 'menu_name' => 'Новини застосунку'],
		'public'       => false,
		'show_ui'      => true,
		'show_in_menu' => 'woocommerce',
		'supports'     => ['title', 'excerpt'],
	]);
});

/** Поля банера: підпис, опис, колір, промокод, період показу. */
function raky_banner_fields(): array {
	return [
		'label'    => ['Підпис над заголовком', 'text'],
		'subtitle' => ['Опис', 'text'],
		'color'    => ['Колір фону', 'color'],
		'coupon'   => ['Промокод (необов\'язково)', 'text'],
		'starts'   => ['Показувати з', 'date'],
		'ends'     => ['Показувати до', 'date'],
	];
}

add_action('add_meta_boxes_raky_banner', static function () {
	add_meta_box('raky_banner_box', 'Параметри банера', static function (WP_Post $post) {
		wp_nonce_field('raky_banner', 'raky_banner_nonce');
		echo '<table class="form-table">';
		foreach (raky_banner_fields() as $key => [$label, $type]) {
			$value = (string) get_post_meta($post->ID, '_raky_' . $key, true);
			if ($key === 'color' && $value === '') {
				$value = '#D92B20';
			}
			printf(
				'<tr><th><label for="raky_%1$s">%2$s</label></th><td><input id="raky_%1$s" name="raky_%1$s" type="%3$s" value="%4$s" class="regular-text"></td></tr>',
				esc_attr($key),
				esc_html($label),
				esc_attr($type),
				esc_attr($value)
			);
		}
		echo '</table><p>Банер показується на Головній і в розділі Акції. Зображення задається як «Зображення запису».</p>';
	});
});

add_action('save_post_raky_banner', static function (int $post_id) {
	$nonce = $_POST['raky_banner_nonce'] ?? '';
	if (! is_string($nonce) || ! wp_verify_nonce($nonce, 'raky_banner') || ! current_user_can('edit_post', $post_id)) {
		return;
	}
	foreach (array_keys(raky_banner_fields()) as $key) {
		$value = sanitize_text_field(wp_unslash((string) ($_POST['raky_' . $key] ?? '')));
		update_post_meta($post_id, '_raky_' . $key, $value);
	}
});

function raky_banner_is_live(int $post_id, string $today): bool {
	$starts = (string) get_post_meta($post_id, '_raky_starts', true);
	$ends   = (string) get_post_meta($post_id, '_raky_ends', true);
	return ($starts === '' || $starts <= $today) && ($ends === '' || $ends >= $today);
}

function raky_rest_banners(): array {
	$today = wp_date('Y-m-d');
	$posts = get_posts(['post_type' => 'raky_banner', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'menu_order date', 'order' => 'ASC']);
	$out   = [];
	foreach ($posts as $post) {
		if (! raky_banner_is_live($post->ID, $today)) {
			continue;
		}
		$coupon = (string) get_post_meta($post->ID, '_raky_coupon', true);
		$out[]  = [
			'id'       => $post->ID,
			'title'    => $post->post_title,
			'label'    => (string) get_post_meta($post->ID, '_raky_label', true),
			'subtitle' => (string) get_post_meta($post->ID, '_raky_subtitle', true),
			'color'    => (string) get_post_meta($post->ID, '_raky_color', true),
			'image'    => raky_public_url(get_the_post_thumbnail_url($post, 'large') ?: null),
			'code'     => $coupon === '' ? null : strtoupper($coupon),
		];
	}
	return $out;
}

function raky_rest_news(): array {
	$posts = get_posts(['post_type' => 'raky_news', 'post_status' => 'publish', 'numberposts' => 50, 'orderby' => 'date', 'order' => 'DESC']);
	return array_map(static fn (WP_Post $post) => [
		'id'    => $post->ID,
		'title' => $post->post_title,
		'text'  => $post->post_excerpt,
		'date'  => get_post_time('c', true, $post),
	], $posts);
}

/** Скільки разів цей телефон уже використав купон у нескасованих замовленнях. */
function raky_coupon_uses_by_phone(string $code, string $phone): int {
	$orders = wc_get_orders([
		'limit'         => -1,
		'billing_phone' => $phone,
		'status'        => array_diff(array_keys(wc_get_order_statuses()), ['wc-cancelled', 'wc-failed', 'wc-refunded']),
		'return'        => 'objects',
	]);
	$uses = 0;
	foreach ($orders as $order) {
		if (in_array(strtolower($code), array_map('strtolower', $order->get_coupon_codes()), true)) {
			++$uses;
		}
	}
	return $uses;
}

/** @return float|WP_Error Розмір знижки в гривнях. */
function raky_coupon_discount(string $code, float $subtotal, string $phone) {
	$coupon = new WC_Coupon($code);
	if (! $coupon->get_id()) {
		return raky_error('raky_bad_promo', 'Такого промокоду немає.', 422);
	}
	$expires = $coupon->get_date_expires();
	if ($expires && $expires->getTimestamp() < time()) {
		return raky_error('raky_bad_promo', 'Термін дії промокоду минув.', 422);
	}
	if ($coupon->get_usage_limit() > 0 && $coupon->get_usage_count() >= $coupon->get_usage_limit()) {
		return raky_error('raky_bad_promo', 'Цей промокод уже використано.', 422);
	}
	if ($subtotal < (float) $coupon->get_minimum_amount()) {
		return raky_error('raky_bad_promo', sprintf('Промокод діє від %s грн.', (float) $coupon->get_minimum_amount()), 422);
	}
	$per_person = $coupon->get_usage_limit_per_user();
	if ($per_person > 0 && $phone !== '' && raky_coupon_uses_by_phone($code, $phone) >= $per_person) {
		return raky_error('raky_bad_promo', 'Ви вже використали цей промокод.', 422);
	}
	$amount = (float) $coupon->get_amount();
	return $coupon->get_discount_type() === 'percent'
		? round($subtotal * $amount / 100, 2)
		: min($amount, $subtotal);
}

function raky_rest_promo_check(WP_REST_Request $request) {
	$lines = raky_resolve_lines($request->get_param('items'));
	if (is_wp_error($lines)) {
		return $lines;
	}
	$code     = sanitize_text_field((string) $request->get_param('code'));
	$phone    = raky_clean_phone((string) $request->get_param('phone'));
	$discount = raky_coupon_discount($code, raky_lines_subtotal($lines), $phone);
	if (is_wp_error($discount)) {
		return $discount;
	}
	return ['code' => strtoupper($code), 'discount' => $discount];
}
