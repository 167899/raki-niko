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

/** Текстові поля банера: підпис, короткий і повний опис, колір, промокод, період показу. */
function raky_banner_fields(): array {
	return [
		'label'    => ['Підпис над заголовком', 'text'],
		'subtitle' => ['Короткий опис', 'text', 'Один рядок на самому банері на Головній.'],
		'details'  => ['Повний опис', 'textarea', 'Умови акції в розділі «Акції». Якщо не заповнити, там буде короткий опис.'],
		'color'    => ['Колір фону', 'color'],
		'coupon'   => ['Промокод (необов\'язково)', 'text'],
		'starts'   => ['Показувати з', 'date'],
		'ends'     => ['Показувати до', 'date'],
	];
}

/** Які написи видно на банері: [ключ => підпис]. Без збереженого значення напис показується. */
function raky_banner_texts(): array {
	return ['title' => 'Назву', 'label' => 'Підпис над заголовком', 'subtitle' => 'Короткий опис'];
}

function raky_banner_shows(int $post_id, string $key): bool {
	return get_post_meta($post_id, '_raky_show_' . $key, true) !== '0';
}

/** Фон банера: 'color' або 'image'. Старі банери з картинкою лишаються з картинкою. */
function raky_banner_background(int $post_id): string {
	$background = (string) get_post_meta($post_id, '_raky_background', true);
	if ($background === '') {
		return has_post_thumbnail($post_id) ? 'image' : 'color';
	}
	return $background === 'image' ? 'image' : 'color';
}

/**
 * Умови промокоду зберігаються в самому купоні WooCommerce, тож діють і без банера.
 * «Одноразово» — стандартний «Ліміт використання на користувача» = 1, рахується за телефоном.
 */
const RAKY_FIRST_ORDER_META = '_raky_first_order';

function raky_coupon_first_order_only(WC_Coupon $coupon): bool {
	return $coupon->get_meta(RAKY_FIRST_ORDER_META) === 'yes';
}

/** @return WC_Coupon|null Купон за кодом із банера. */
function raky_banner_coupon(string $code): ?WC_Coupon {
	if ($code === '') {
		return null;
	}
	$coupon = new WC_Coupon($code);
	return $coupon->get_id() ? $coupon : null;
}

/** Рядок «Умови промокоду» в параметрах банера. */
function raky_banner_rules_row(string $code): void {
	echo '<tr><th>Умови промокоду</th><td><fieldset>';
	$coupon = raky_banner_coupon($code);
	if ($code === '') {
		echo '<p class="description">Умови з\'являться, коли ви вкажете промокод.</p>';
	} elseif (! $coupon) {
		printf(
			'<p style="color:#b32d2e"><strong>Купона %s немає в WooCommerce.</strong> Створіть його в «Маркетинг → Купони» з розміром знижки, інакше застосунок відповість «Такого промокоду немає». Після цього тут можна буде задати умови.</p>',
			esc_html(strtoupper($code))
		);
	} else {
		printf('<input type="hidden" name="raky_rules_code" value="%s">', esc_attr($code));
		printf(
			'<label style="display:block;margin-bottom:6px"><input type="checkbox" name="raky_once" value="1"%s> Застосовується одноразово — один раз для кожного клієнта</label>',
			checked($coupon->get_usage_limit_per_user() === 1, true, false)
		);
		printf(
			'<label style="display:block;margin-bottom:6px"><input type="checkbox" name="raky_first_order" value="1"%s> Лише на перше замовлення</label>',
			checked(raky_coupon_first_order_only($coupon), true, false)
		);
		echo '<p class="description">Клієнта впізнаємо за номером телефону. Якщо з цього номера вже є замовлення (крім скасованих) або в профілі клієнта позначено «Подарунок на перше замовлення вже використано», промокод не спрацює. '
			. 'Покупки на касі без застосунку не враховуються. Ці ж умови є в самому купоні («Маркетинг → Купони»).</p>';
	}
	echo '</fieldset></td></tr>';
}

/** Зберігає умови з банера в купон. Лише якщо форма показувала умови саме цього коду. */
function raky_banner_save_rules(string $code): void {
	$shown = sanitize_text_field(wp_unslash((string) ($_POST['raky_rules_code'] ?? '')));
	$coupon = raky_banner_coupon($code);
	if (! $coupon || $shown === '' || strtolower($shown) !== strtolower($code)) {
		return;
	}
	if (! empty($_POST['raky_once'])) {
		$coupon->set_usage_limit_per_user(1);
	} elseif ($coupon->get_usage_limit_per_user() === 1) {
		$coupon->set_usage_limit_per_user(0);
	}
	$coupon->update_meta_data(RAKY_FIRST_ORDER_META, empty($_POST['raky_first_order']) ? 'no' : 'yes');
	$coupon->save();
}

// Та сама позначка в купоні WooCommerce, вкладка «Обмеження використання».
add_action('woocommerce_coupon_options_usage_restriction', static function ($coupon_id, $coupon) {
	woocommerce_wp_checkbox([
		'id'          => 'raky_first_order',
		'label'       => 'Лише на перше замовлення',
		'description' => 'Не спрацює, якщо з цього телефону вже є замовлення в застосунку (крім скасованих) або в профілі клієнта позначено, що подарунок на перше замовлення використано.',
		'value'       => $coupon instanceof WC_Coupon && raky_coupon_first_order_only($coupon) ? 'yes' : 'no',
	]);
}, 10, 2);

add_action('woocommerce_coupon_options_save', static function ($post_id, $coupon) {
	if ($coupon instanceof WC_Coupon) {
		$coupon->update_meta_data(RAKY_FIRST_ORDER_META, empty($_POST['raky_first_order']) ? 'no' : 'yes'); // phpcs:ignore WordPress.Security.NonceVerification -- nonce перевіряє WooCommerce
		$coupon->save();
	}
}, 10, 2);

/** Рекомендований розмір картинки банера; підказка в адмінці бере його звідси. */
const RAKY_BANNER_IMAGE = '1200×340 пікселів';

add_action('add_meta_boxes_raky_banner', static function () {
	add_meta_box('raky_banner_box', 'Параметри банера', static function (WP_Post $post) {
		wp_nonce_field('raky_banner', 'raky_banner_nonce');
		echo '<table class="form-table">';
		foreach (raky_banner_fields() as $key => $field) {
			[$label, $type] = $field;
			$value = (string) get_post_meta($post->ID, '_raky_' . $key, true);
			if ($key === 'color' && $value === '') {
				$value = '#D92B20';
			}
			$input = $type === 'textarea'
				? sprintf('<textarea id="raky_%1$s" name="raky_%1$s" rows="5" class="large-text">%2$s</textarea>', esc_attr($key), esc_textarea($value))
				: sprintf('<input id="raky_%1$s" name="raky_%1$s" type="%2$s" value="%3$s" class="regular-text">', esc_attr($key), esc_attr($type), esc_attr($value));
			printf(
				'<tr><th><label for="raky_%1$s">%2$s</label></th><td>%3$s%4$s</td></tr>',
				esc_attr($key),
				esc_html($label),
				$input, // phpcs:ignore WordPress.Security.EscapeOutput -- зібрано з екранованих частин вище
				isset($field[2]) ? '<p class="description">' . esc_html($field[2]) . '</p>' : ''
			);
			if ($key === 'coupon') {
				raky_banner_rules_row($value);
			}
		}

		echo '<tr><th>Показувати на банері</th><td><fieldset>';
		foreach (raky_banner_texts() as $key => $label) {
			printf(
				'<label style="display:block;margin-bottom:6px"><input type="checkbox" name="raky_show_%1$s" value="1"%2$s> %3$s</label>',
				esc_attr($key),
				checked(raky_banner_shows($post->ID, $key), true, false),
				esc_html($label)
			);
		}
		echo '<p class="description">Зніміть позначки, якщо текст уже є на картинці. Діє на Головній і в розділі «Акції».</p></fieldset></td></tr>';

		$background = raky_banner_background($post->ID);
		echo '<tr><th>Фон банера</th><td><fieldset>';
		foreach (['color' => 'Колір фону', 'image' => 'Картинка'] as $value => $label) {
			printf(
				'<label style="display:block;margin-bottom:6px"><input type="radio" name="raky_background" value="%1$s"%2$s> %3$s</label>',
				esc_attr($value),
				checked($background, $value, false),
				esc_html($label)
			);
		}
		printf(
			'<p class="description">Картинку завантажте в блоці «Зображення запису» праворуч. Розмір %s, JPG або PNG до 500 КБ. '
			. 'На різних телефонах краї до 50 пікселів можуть обрізатися, тож текст і головне на картинці тримайте ближче до центру. '
			. 'Поки картинку не завантажено, банер показується з кольором фону.</p>',
			esc_html(RAKY_BANNER_IMAGE)
		);
		echo '</fieldset></td></tr>';
		echo '</table><p>Банер показується на Головній і в розділі Акції.</p>';
	});
});

add_action('save_post_raky_banner', static function (int $post_id) {
	$nonce = $_POST['raky_banner_nonce'] ?? '';
	if (! is_string($nonce) || ! wp_verify_nonce($nonce, 'raky_banner') || ! current_user_can('edit_post', $post_id)) {
		return;
	}
	foreach (raky_banner_fields() as $key => $field) {
		$raw   = wp_unslash((string) ($_POST['raky_' . $key] ?? ''));
		$value = $field[1] === 'textarea' ? sanitize_textarea_field($raw) : sanitize_text_field($raw);
		update_post_meta($post_id, '_raky_' . $key, $value);
	}
	foreach (array_keys(raky_banner_texts()) as $key) {
		update_post_meta($post_id, '_raky_show_' . $key, empty($_POST['raky_show_' . $key]) ? '0' : '1');
	}
	update_post_meta($post_id, '_raky_background', ($_POST['raky_background'] ?? '') === 'image' ? 'image' : 'color');
	raky_banner_save_rules((string) get_post_meta($post_id, '_raky_coupon', true));
});

/** Блок банерів на Головній можна вимкнути, не видаляючи самі банери. */
function raky_banners_on(): bool {
	return get_option('raky_banners_on', '1') !== '0';
}

// Перемикач над списком банерів.
add_action('admin_notices', static function () {
	$screen = get_current_screen();
	if (! $screen || $screen->id !== 'edit-raky_banner') {
		return;
	}
	?>
	<div class="notice notice-info">
		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
			<input type="hidden" name="action" value="raky_banners_toggle">
			<?php wp_nonce_field('raky_banners_toggle'); ?>
			<p>
				<label style="margin-right:10px"><input type="checkbox" name="raky_banners_on" value="1" <?php checked(raky_banners_on()); ?>> <strong>Показувати блок банерів на Головній</strong></label>
				<button class="button button-small">Зберегти</button>
			</p>
			<p class="description">Вимкнений блок не видаляє банери. Розділ «Акції» показує їх і далі.</p>
		</form>
	</div>
	<?php
});

add_action('admin_post_raky_banners_toggle', static function () {
	check_admin_referer('raky_banners_toggle');
	if (! current_user_can('edit_posts')) {
		wp_die('Недостатньо прав.');
	}
	update_option('raky_banners_on', empty($_POST['raky_banners_on']) ? '0' : '1');
	wp_safe_redirect(admin_url('edit.php?post_type=raky_banner'));
	exit;
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
		$image  = raky_banner_background($post->ID) === 'image' ? get_the_post_thumbnail_url($post, 'full') : null;
		$out[]  = [
			'id'       => $post->ID,
			'title'    => $post->post_title,
			'label'    => (string) get_post_meta($post->ID, '_raky_label', true),
			'subtitle' => (string) get_post_meta($post->ID, '_raky_subtitle', true),
			'details'  => (string) get_post_meta($post->ID, '_raky_details', true),
			'color'    => (string) get_post_meta($post->ID, '_raky_color', true),
			'image'    => raky_public_url($image ?: null),
			'code'     => $coupon === '' ? null : strtoupper($coupon),
			// Написи на самому банері, на Головній і в «Акціях».
			'show'     => array_map(static fn (string $key) => raky_banner_shows($post->ID, $key), array_combine(array_keys(raky_banner_texts()), array_keys(raky_banner_texts()))),
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

/** Записи одного номера, які трапляються в замовленнях: 0…, 380…, +380…. */
function raky_phone_variants(string $phone): array {
	$digits = (string) preg_replace('/\D/', '', $phone);
	if (strlen($digits) < 9) {
		return [];
	}
	$local = substr($digits, -9);
	return ['0' . $local, '380' . $local, '+380' . $local];
}

/** Нескасовані замовлення з цим телефоном, хоч би як його записали. @return WC_Order[] */
function raky_orders_by_phone(string $phone): array {
	$statuses = array_diff(array_keys(wc_get_order_statuses()), ['wc-cancelled', 'wc-failed', 'wc-refunded']);
	$orders   = [];
	foreach (raky_phone_variants($phone) as $variant) {
		foreach (wc_get_orders(['limit' => -1, 'billing_phone' => $variant, 'status' => $statuses, 'return' => 'objects']) as $order) {
			$orders[$order->get_id()] = $order;
		}
	}
	return array_values($orders);
}

/** Скільки разів цей телефон уже використав купон у нескасованих замовленнях. */
function raky_coupon_uses_by_phone(string $code, string $phone): int {
	$uses = 0;
	foreach (raky_orders_by_phone($phone) as $order) {
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
	$referral = raky_ref_check($coupon, $phone);
	if ($referral) {
		return $referral;
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
	// Без телефону (гість у кошику) перевірка відбудеться під час оформлення, там телефон обов'язковий.
	if (raky_coupon_first_order_only($coupon) && $phone !== '' && raky_client_had_first_order($phone)) {
		return raky_error('raky_bad_promo', 'Промокод діє лише на перше замовлення. Приберіть його в кошику.', 422);
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
