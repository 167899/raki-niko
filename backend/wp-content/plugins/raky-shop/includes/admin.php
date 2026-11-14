<?php
defined('ABSPATH') || exit;

add_action('admin_menu', static function () {
	add_submenu_page('woocommerce', 'Застосунок', 'Застосунок', 'manage_woocommerce', 'raky-settings', 'raky_render_settings_page');
});

/** Поля застосунку на вкладці «Основне» товару: [ключ мети, підпис, підказка]. */
function raky_product_fields(): array {
	return [
		[RAKY_META_CALIBER, 'Калібр', 'Наприклад «середні».'],
		[RAKY_META_CALIBER_HINT, 'Розмір', 'Наприклад «30–40 г».'],
		[RAKY_META_PER_KG, 'Штук у кілограмі', 'Приблизна кількість для підказки клієнту.'],
		[RAKY_META_STEP, 'Крок кількості', 'Для раків 0.5.'],
		[RAKY_META_MIN, 'Мінімальне замовлення', 'Для раків 1.'],
	];
}

add_action('woocommerce_product_options_general_product_data', static function () {
	echo '<div class="options_group">';
	woocommerce_wp_select([
		'id'          => RAKY_META_UNIT,
		'label'       => 'Одиниця застосунку',
		'options'     => raky_units(),
		'value'       => ($product = wc_get_product(get_the_ID())) ? raky_product_unit($product) : 'pcs',
		'description' => 'За цю одиницю клієнт бачить ціну, у ній рахується кількість. Для «100 г» крок 1 означає 100 г.',
		'desc_tip'    => true,
	]);
	raky_render_sky_field();
	woocommerce_wp_text_input([
		'id'          => RAKY_META_SKY_FACTOR,
		'label'       => 'Множник Skyservice',
		'placeholder' => '1',
		'description' => 'Ціна з каси множиться на це число. Наприклад 0.1, якщо в застосунку ціна за 100 г, а в касі за 1 кг.',
		'desc_tip'    => true,
	]);
	foreach (raky_product_fields() as [$key, $label, $hint]) {
		woocommerce_wp_text_input(['id' => $key, 'label' => $label, 'description' => $hint, 'desc_tip' => true]);
	}
	raky_render_flavor_rows();
	echo '</div>';
});

// «Зняти з продажу» — у блоці «Опублікувати», поруч із кнопкою «Оновити».
add_action('post_submitbox_misc_actions', static function (WP_Post $post) {
	if ($post->post_type !== 'product') {
		return;
	}
	$product = wc_get_product($post->ID);
	printf(
		'<div class="misc-pub-section"><label><input type="checkbox" name="raky_hidden" value="1"%s> <strong>Зняти з продажу</strong></label>'
		. '<p class="description" style="margin:4px 0 0">Товар зникне із застосунку, але не видалиться. Зніміть позначку, щоб повернути його.</p></div>',
		checked($product && raky_product_hidden($product), true, false)
	);
});

// Позначка в списку товарів: «Назва — Знято з продажу».
add_filter('display_post_states', static function (array $states, WP_Post $post): array {
	if ($post->post_type === 'product' && ($product = wc_get_product($post)) && raky_product_hidden($product)) {
		$states['raky_hidden'] = 'Знято з продажу';
	}
	return $states;
}, 10, 2);

/**
 * Позиції Skyservice товару: основна дає ціну й наявність, додаткові (рецепт) лише додають ціну.
 * Без підключення або при помилці каси поля стають прихованими й зберігають поточну прив'язку.
 */
function raky_render_sky_field(): void {
	$product_id = (int) get_the_ID();
	$main       = raky_sky_ids(get_post_meta($product_id, RAKY_META_SKY_ID, true))[0] ?? '';
	$extra      = raky_sky_ids(get_post_meta($product_id, RAKY_META_SKY_EXTRA, true));
	$label      = '<label for="raky_sky_id">Товар у Skyservice</label>';
	$hidden     = sprintf(
		'<input type="hidden" name="raky_sky_id" value="%s"><input type="hidden" name="raky_sky_extra" value="%s">',
		esc_attr($main),
		esc_attr(implode(',', $extra))
	);
	$products = raky_sky_ready() ? raky_sky_products() : null;
	if (! is_array($products)) {
		$message = $products === null
			? sprintf('Підключіть Skyservice у <a href="%s">WooCommerce → Застосунок</a>, щоб брати ціну з каси.', esc_url(admin_url('admin.php?page=raky-settings')))
			: esc_html($products->get_error_message() . ' Прив\'язку не змінено.');
		printf('<p class="form-field">%s<span>%s</span>%s</p>', $label, $message, $hidden); // phpcs:ignore WordPress.Security.EscapeOutput
		return;
	}
	echo '<p class="form-field">' . $label . '<select id="raky_sky_id" name="raky_sky_id" class="wc-enhanced-select" style="width:50%"><option value="">Не брати ціну з каси</option>'; // phpcs:ignore WordPress.Security.EscapeOutput
	raky_render_sky_options($products, $main === '' ? [] : [$main]);
	echo '</select>' . wc_help_tip('Ціна й наявність оновлюються з каси кожні 10 хвилин, тож «Звичайну ціну» вручну не змінюйте. Якщо позицію вимкнено в касі, товару в застосунку немає в наявності.') . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput

	// Порожнє значення надсилається завжди: так зняття всіх додаткових позицій теж зберігається.
	echo '<p class="form-field"><label for="raky_sky_extra">Додати до ціни</label><input type="hidden" name="raky_sky_extra[]" value="">'
		. '<select id="raky_sky_extra" name="raky_sky_extra[]" multiple class="wc-enhanced-select" style="width:50%" data-placeholder="Наприклад, рецепт">'; // phpcs:ignore WordPress.Security.EscapeOutput
	raky_render_sky_options($products, $extra);
	echo '</select>' . wc_help_tip('Ціни цих позицій додаються до ціни товару, наприклад раки + рецепт. Їхня наявність у касі не враховується.') . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput

	$ids = raky_sky_product_ids($product_id);
	if ($ids) {
		raky_render_sky_price($ids, raky_sky_factor(get_post_meta($product_id, RAKY_META_SKY_FACTOR, true)), $products);
	}
}

/** Позиції каси за категоріями; вибрані, яких у касі вже немає, показуються першими. */
function raky_render_sky_options(array $products, array $selected): void {
	foreach ($selected as $id) {
		if (! isset($products[$id])) {
			printf('<option value="%s" selected>Товар №%s (немає в касі)</option>', esc_attr($id), esc_html($id));
		}
	}
	$groups = [];
	foreach ($products as $product) {
		$groups[$product['category'] ?: 'Без категорії'][] = $product;
	}
	ksort($groups);
	foreach ($groups as $category => $rows) {
		printf('<optgroup label="%s">', esc_attr($category));
		foreach ($rows as $row) {
			printf(
				'<option value="%s"%s>%s — %s ₴%s%s</option>',
				esc_attr($row['id']),
				selected(in_array($row['id'], $selected, true), true, false),
				esc_html($row['name']),
				esc_html((string) $row['price']),
				esc_html($row['unit'] !== '' ? ' / ' . $row['unit'] : ''),
				$row['active'] ? '' : esc_html(' (вимкнено в касі)')
			);
		}
		echo '</optgroup>';
	}
}

/** Як складається ціна: «(1200 + 200) × 1 = 1400 ₴». Показує збережену прив'язку. */
function raky_render_sky_price(array $ids, float $factor, array $products): void {
	$parts = [];
	foreach ($ids as $id) {
		$parts[] = isset($products[$id]) && is_numeric($products[$id]['price']) ? wc_format_localized_price((string) $products[$id]['price']) : '?';
	}
	$sky     = raky_sky_combine($ids, $factor, $products);
	$formula = count($parts) > 1 ? '(' . implode(' + ', $parts) . ')' : $parts[0];
	if ($factor !== 1.0) {
		$formula .= ' × ' . wc_format_localized_decimal((string) $factor);
	}
	$result = $sky['price'] === null || $sky['missing'] ? 'ціну не визначено' : wc_format_localized_price((string) $sky['price']) . ' ₴';
	printf('<p class="form-field"><label>Ціна з каси</label><span>%s = %s</span></p>', esc_html($formula), esc_html($result));
}

/** Рядок списку «назва + сума»: $kind flavor (смак, доплата) або addon (додатково, ціна за штуку). */
function raky_option_row(string $kind, string $name = '', string $amount = ''): string {
	$addon = $kind === 'addon';
	return sprintf(
		'<div class="raky-option" style="display:flex;gap:6px;margin-bottom:6px">'
		. '<input name="raky_%1$s_name[]" placeholder="%2$s" value="%3$s" style="flex:2;float:none;width:auto;min-width:0">'
		. '<input name="raky_%1$s_extra[]" type="number" min="0" step="0.01" placeholder="%4$s" value="%5$s" style="flex:1;float:none;width:auto;min-width:0">'
		. '<button type="button" class="button raky-option-remove" style="flex:none" aria-label="Видалити">×</button></div>',
		$kind,
		$addon ? 'Назва, напр. Рукавички' : 'Назва смаку',
		esc_attr($name),
		$addon ? 'Ціна за шт., ₴' : 'Доплата, ₴',
		esc_attr($amount)
	);
}

/** Смаки (доплата за кілограм або штуку) і «Додатково» (ціна за штуку): окремий рядок на кожен. */
function raky_render_flavor_rows(): void {
	$product = wc_get_product(get_the_ID());
	$lists   = [
		'flavor' => ['Смаки й доплата за одиницю', 'Додати смак', $product ? array_map(static fn ($row) => [$row['name'], $row['extra']], raky_product_flavors($product)) : []],
		'addon'  => ['Додатково: рукавички, пакети, набори (ціна за штуку)', 'Додати', $product ? array_map(static fn ($row) => [$row['name'], $row['price']], raky_product_addons($product)) : []],
	];
	foreach ($lists as $kind => [$title, $button, $rows]) {
		printf('<div class="form-field" style="padding:9px 12px"><label style="float:none;margin:0 0 6px;display:block;width:auto">%s</label><div id="raky-%s-list">', esc_html($title), esc_attr($kind));
		foreach ($rows as [$name, $amount]) {
			echo raky_option_row($kind, $name, $amount > 0 ? (string) $amount : ''); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		printf('</div><button type="button" class="button raky-option-add" data-kind="%s">%s</button></div>', esc_attr($kind), esc_html($button));
		printf('<template id="raky-%s-template">%s</template>', esc_attr($kind), raky_option_row($kind)); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	?>
	<script>
	(function () {
		document.querySelectorAll('.raky-option-add').forEach(function (button) {
			var kind = button.dataset.kind;
			button.addEventListener('click', function () {
				document.getElementById('raky-' + kind + '-list').appendChild(document.getElementById('raky-' + kind + '-template').content.cloneNode(true));
			});
			document.getElementById('raky-' + kind + '-list').addEventListener('click', function (event) {
				if (event.target.classList.contains('raky-option-remove')) event.target.closest('.raky-option').remove();
			});
		});
	})();
	</script>
	<?php
}

add_action('woocommerce_admin_process_product_object', static function (WC_Product $product) {
	$product->update_meta_data(RAKY_META_HIDDEN, empty($_POST['raky_hidden']) ? 'no' : 'yes');
	$unit = sanitize_key(wp_unslash((string) ($_POST[RAKY_META_UNIT] ?? '')));
	$product->update_meta_data(RAKY_META_UNIT, isset(raky_units()[$unit]) ? $unit : 'pcs');
	// Лише цифри; основна позиція одна, додаткові без повтору основної.
	$main = isset($_POST['raky_sky_id']) ? (raky_sky_ids(wp_unslash($_POST['raky_sky_id']))[0] ?? '') : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ($main !== null) {
		$extra = array_values(array_diff(raky_sky_ids(wp_unslash($_POST['raky_sky_extra'] ?? '')), [$main])); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		foreach ([RAKY_META_SKY_ID => $main, RAKY_META_SKY_EXTRA => implode(',', $extra)] as $key => $value) {
			if ($value === '') {
				$product->delete_meta_data($key);
			} else {
				$product->update_meta_data($key, $value);
			}
		}
	}
	$factor = trim((string) wp_unslash($_POST[RAKY_META_SKY_FACTOR] ?? ''));
	if ($factor === '' || raky_sky_factor($factor) === 1.0) {
		$product->delete_meta_data(RAKY_META_SKY_FACTOR);
	} else {
		$product->update_meta_data(RAKY_META_SKY_FACTOR, (string) raky_sky_factor($factor));
	}
	foreach (raky_product_fields() as [$key]) {
		$product->update_meta_data($key, sanitize_text_field(wp_unslash((string) ($_POST[$key] ?? ''))));
	}
	$names   = array_map('wp_unslash', (array) ($_POST['raky_flavor_name'] ?? []));
	$extras  = (array) ($_POST['raky_flavor_extra'] ?? []);
	$flavors = [];
	foreach ($names as $index => $name) {
		$name = sanitize_text_field((string) $name);
		if ($name !== '') {
			$flavors[] = ['name' => $name, 'extra' => max(0, (float) ($extras[$index] ?? 0))];
		}
	}
	$product->update_meta_data(RAKY_META_FLAVORS, $flavors);
	$names  = array_map('wp_unslash', (array) ($_POST['raky_addon_name'] ?? []));
	$prices = (array) ($_POST['raky_addon_extra'] ?? []);
	$addons = [];
	foreach ($names as $index => $name) {
		$name = sanitize_text_field((string) $name);
		if ($name !== '') {
			$addons[] = ['name' => $name, 'price' => max(0, (float) ($prices[$index] ?? 0))];
		}
	}
	$product->update_meta_data(RAKY_META_ADDONS, $addons);
});

/**
 * Кнопки в дереві категорій товару. «Додати підкатегорію» відкриває стандартну форму WordPress
 * із вибраною батьківською категорією, «Видалити» викликає raky_delete_category.
 */
add_action('wp_ajax_raky_delete_category', static function () {
	check_ajax_referer('raky_delete_category');
	$term_id = absint($_POST['term_id'] ?? 0);
	if (! current_user_can('delete_product_terms')) {
		wp_send_json_error('Недостатньо прав.');
	}
	if ($term_id === raky_default_category()) {
		wp_send_json_error('Розділ «Без категорії» видалити не можна.');
	}
	$result = wp_delete_term($term_id, 'product_cat');
	if (is_wp_error($result) || ! $result) {
		wp_send_json_error('Не вдалося видалити категорію.');
	}
	wp_send_json_success();
});

/** Перейменування з олівця в дереві категорій товару. */
add_action('wp_ajax_raky_rename_category', static function () {
	check_ajax_referer('raky_edit_categories');
	if (! current_user_can('edit_product_terms')) {
		wp_send_json_error('Недостатньо прав.');
	}
	$term_id = absint($_POST['term_id'] ?? 0);
	$name    = sanitize_text_field(wp_unslash((string) ($_POST['name'] ?? '')));
	if ($name === '') {
		wp_send_json_error('Назва не може бути порожньою.');
	}
	$result = wp_update_term($term_id, 'product_cat', ['name' => $name]);
	if (is_wp_error($result)) {
		wp_send_json_error($result->get_error_code() === 'duplicate_term_slug' ? 'Категорія з такою назвою вже є.' : 'Не вдалося перейменувати категорію.');
	}
	wp_send_json_success(['name' => get_term($term_id, 'product_cat')->name]);
});

/**
 * Новий порядок категорій одного рівня після перетягування. Порядок зберігається там же, де його тримає WooCommerce
 * (поле order), тож він той самий на сторінці «Категорії» і в застосунку.
 */
add_action('wp_ajax_raky_order_categories', static function () {
	check_ajax_referer('raky_edit_categories');
	if (! current_user_can('manage_product_terms')) {
		wp_send_json_error('Недостатньо прав.');
	}
	$ids = array_values(array_filter(array_map('absint', (array) ($_POST['ids'] ?? []))));
	foreach ($ids as $index => $term_id) {
		if (term_exists($term_id, 'product_cat')) {
			update_term_meta($term_id, 'order', $index + 1);
		}
	}
	wp_send_json_success();
});

// У товарі дерево категорій лишається в справжньому порядку: позначені не підстрибують нагору.
add_filter('wp_terms_checklist_args', static function (array $args): array {
	if (($args['taxonomy'] ?? '') === 'product_cat') {
		$args['checked_ontop'] = false;
	}
	return $args;
});

add_action('admin_enqueue_scripts', static function () {
	$screen = get_current_screen();
	if (! $screen || $screen->id !== 'product') {
		return;
	}
	wp_enqueue_script('raky-admin-categories', plugins_url('assets/admin-categories.js', __DIR__), ['jquery', 'jquery-ui-sortable'], '0.3.1', true);
	wp_localize_script('raky-admin-categories', 'rakyCategories', [
		'defaultCategory' => (string) raky_default_category(),
		'deleteNonce'     => wp_create_nonce('raky_delete_category'),
		'editNonce'       => wp_create_nonce('raky_edit_categories'),
	]);
});

/** Час приготування на сторінці замовлення. Коментар клієнта WooCommerce показує сам. */
add_action('woocommerce_admin_order_data_after_order_details', static function (WC_Order $order) {
	$ready = (string) $order->get_meta('_raky_ready_at');
	$date  = $ready === '' ? false : DateTimeImmutable::createFromFormat('Y-m-d H:i', $ready, wp_timezone());
	printf(
		'<p class="form-field form-field-wide"><strong>Готуємо на:</strong> %s</p>',
		esc_html($date ? $date->format('d.m.Y H:i') : 'якнайшвидше')
	);
});

function raky_save_settings(): ?string {
	$outlets = json_decode(wp_unslash((string) ($_POST['outlets'] ?? '')), true);
	$zones   = json_decode(wp_unslash((string) ($_POST['zones'] ?? '')), true);
	if (! is_array($outlets) || ! is_array($zones)) {
		return 'Точки або зони містять некоректний JSON. Зміни не збережено.';
	}
	foreach ($zones as $zone) {
		if (count($zone['polygon'] ?? []) < 3 || ! isset($zone['id'], $zone['name'], $zone['outlet_id'], $zone['fee'], $zone['min_order'])) {
			return 'Кожна зона потребує id, name, outlet_id, fee, min_order і щонайменше 3 точки polygon.';
		}
	}
	update_option('raky_settings', [
		'requisites'    => sanitize_textarea_field(wp_unslash((string) ($_POST['requisites'] ?? ''))),
		'support_phone' => sanitize_text_field(wp_unslash((string) ($_POST['support_phone'] ?? ''))),
		'referral_on'   => ! empty($_POST['referral_on']),
		'referral_pct'  => max(0, min(100, (float) ($_POST['referral_pct'] ?? 0))),
		'outlets'       => $outlets,
		'zones'         => $zones,
	]);
	return null;
}

function raky_render_settings_page(): void {
	$error = null;
	$saved = false;
	if (isset($_POST['raky_save'])) {
		check_admin_referer('raky_settings');
		$error = raky_save_settings();
		$saved = $error === null;
	}
	$report = null;
	if (isset($_POST['raky_sky_action'])) {
		check_admin_referer('raky_skyservice');
		$action = sanitize_key(wp_unslash((string) $_POST['raky_sky_action']));
		if ($action === 'save' || $action === 'disconnect') {
			$_POST['sky_disconnect'] = $action === 'disconnect' ? '1' : '';
			$error = raky_sky_save_settings();
			$saved = $error === null;
		} else {
			$report = raky_sky_sync($action === 'preview');
			if (is_wp_error($report)) {
				$error  = $report->get_error_message();
				$report = null;
			} else {
				$report['preview'] = $action === 'preview';
			}
		}
	}
	$tg_chats = null;
	$notice   = null;
	if (isset($_POST['raky_tg_action'])) {
		check_admin_referer('raky_telegram');
		[$error, $notice, $tg_chats] = raky_tg_handle_form(sanitize_key(wp_unslash((string) $_POST['raky_tg_action'])));
	}
	$settings = raky_get_settings();
	$json     = JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT;
	?>
	<div class="wrap">
		<h1>Налаштування застосунку</h1>
		<?php // Зупинку входу в Skyservice показує блок Skyservice, тут її не дублюємо. ?>
		<?php if ($error && $error !== raky_sky_settings()['stopped']) : ?><div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div><?php endif; ?>
		<?php if ($saved) : ?><div class="notice notice-success"><p>Збережено.</p></div><?php endif; ?>
		<?php if ($notice) : ?><div class="notice notice-success"><p><?php echo esc_html($notice); ?></p></div><?php endif; ?>
		<?php raky_render_tg_section($tg_chats); ?>
		<?php raky_render_sky_section($report); ?>
		<form method="post">
			<?php wp_nonce_field('raky_settings'); ?>
			<h2>Реквізити для оплати</h2>
			<p>Клієнт бачить їх лише після підтвердження замовлення.</p>
			<textarea name="requisites" rows="6" class="large-text"><?php echo esc_textarea($settings['requisites']); ?></textarea>
			<h2>Телефон підтримки</h2>
			<input name="support_phone" class="regular-text" value="<?php echo esc_attr($settings['support_phone']); ?>">
			<h2>Реферальна програма</h2>
			<p><label><input type="checkbox" name="referral_on" value="1" <?php checked($settings['referral_on']); ?>> Увімкнути запрошення друзів</label></p>
			<p><label>Разова знижка кожному учаснику, %: <input type="number" name="referral_pct" min="0" max="100" step="0.5" value="<?php echo esc_attr((string) $settings['referral_pct']); ?>"></label></p>
			<p class="description">Кожен клієнт бачить у застосунку свій код. Друг вводить його в кошику на першому замовленні й отримує знижку.
				Коли замовлення друга стає «Виконано», власник коду отримує одноразовий бонус на наступне замовлення: застосунок сам підставить його в кошик.
				Скасоване замовлення бонусу не дає. Коди й бонуси видно в «Маркетинг → Купони» (починаються з RN і BONUS).</p>
			<h2>Точки (JSON)</h2>
			<textarea name="outlets" rows="12" class="large-text code"><?php echo esc_textarea(wp_json_encode($settings['outlets'], $json)); ?></textarea>
			<h2>Зони доставки (JSON)</h2>
			<p>Координати polygon — пари [широта, довгота].</p>
			<textarea name="zones" rows="16" class="large-text code"><?php echo esc_textarea(wp_json_encode($settings['zones'], $json)); ?></textarea>
			<?php submit_button('Зберегти', 'primary', 'raky_save'); ?>
		</form>
	</div>
	<?php
}

/** Блок Skyservice: підключення, точка, звіт про останню синхронізацію. */
function raky_render_sky_section(?array $report): void {
	$sky   = raky_sky_settings();
	$ready = raky_sky_ready();
	$last  = get_option('raky_last_sync');
	$url   = $sky['company_id'] !== '' ? sprintf('https://dashboard.skyservice.online/%s/', $sky['company_id']) : '';
	?>
	<h2>Ціни зі Skyservice</h2>
	<p>
		Ціни й наявність прив'язаних товарів оновлюються з каси кожні 10 хвилин.
		Товар прив'язується в його картці, полем «Товар у Skyservice».
	</p>
	<?php if ($sky['stopped'] !== '') : ?>
		<div class="notice notice-error inline"><p><?php echo esc_html($sky['stopped']); ?></p></div>
	<?php endif; ?>
	<form method="post">
		<?php wp_nonce_field('raky_skyservice'); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="sky_url">Адреса кабінету</label></th>
				<td>
					<input id="sky_url" name="sky_url" class="large-text" value="<?php echo esc_attr($url); ?>" placeholder="https://….dashboard.skyservice.online/…/">
					<p class="description">Скопіюйте з адресного рядка після входу в Skyservice.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="sky_login">Телефон для входу</label></th>
				<td>
					<input id="sky_login" name="sky_login" class="regular-text" inputmode="tel" autocomplete="off" value="<?php echo esc_attr($sky['login'] !== '' ? '+' . $sky['login'] : ''); ?>" placeholder="+380…">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="sky_password">Пароль Skyservice</label></th>
				<td>
					<input id="sky_password" name="sky_password" type="password" class="regular-text" autocomplete="new-password" placeholder="<?php echo esc_attr($sky['password'] !== '' ? 'Збережено. Порожнє поле лишає пароль' : ''); ?>">
					<p class="description">Пароль зберігається зашифрованим. Після невдалого входу синхронізація зупиняється й не повторює спроб, щоб Skyservice не заблокував вхід.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="sky_tradepoint">Точка цін</label></th>
				<td><?php raky_render_sky_tradepoints($sky, $ready); ?></td>
			</tr>
		</table>
		<p>
			<button class="button button-primary" name="raky_sky_action" value="save">Зберегти підключення</button>
			<?php if ($ready) : ?>
				<button class="button" name="raky_sky_action" value="preview">Перевірити зміни</button>
				<button class="button" name="raky_sky_action" value="sync">Синхронізувати зараз</button>
				<button class="button-link button-link-delete" name="raky_sky_action" value="disconnect" style="margin-left:10px" onclick="return confirm('Відключити Skyservice? Ціни перестануть оновлюватися з каси.')">Відключити</button>
			<?php endif; ?>
		</p>
	</form>
	<?php
	if ($report) {
		raky_render_sync_report($report, $report['preview'] ? 'Перевірка без змін' : 'Синхронізацію виконано');
	} elseif ($last) {
		if (! empty($last['error'])) {
			printf('<div class="notice notice-error inline"><p>Остання спроба %s: %s</p></div>', esc_html(wp_date('d.m.Y H:i', $last['error_at'] ?? $last['at'])), esc_html($last['error']));
		}
		if (! empty($last['report'])) {
			raky_render_sync_report($last['report'], 'Остання синхронізація ' . wp_date('d.m.Y H:i', $last['at']));
		}
	}
}

function raky_render_sky_tradepoints(array $sky, bool $ready): void {
	$points = $ready ? raky_sky_tradepoints() : [];
	if (is_wp_error($points) || ! $points) {
		printf('<input type="hidden" name="sky_tradepoint" value="%s">', esc_attr($sky['tradepoint_id']));
		echo esc_html($sky['tradepoint_name'] ?: ($ready ? 'Не вдалося отримати список точок.' : 'Спаська 47/3, обереться після підключення.'));
		return;
	}
	echo '<select id="sky_tradepoint" name="sky_tradepoint">';
	if ($sky['tradepoint_id'] === '') {
		echo '<option value="">Не вибрано</option>';
	}
	foreach ($points as $point) {
		printf('<option value="%s"%s>%s</option>', esc_attr($point['id']), selected($point['id'], $sky['tradepoint_id'], false), esc_html($point['name']));
	}
	echo '</select>';
}

function raky_render_sync_report(array $report, string $title): void {
	$updated  = $report['updated'] ?? [];
	$rejected = $report['rejected'] ?? [];
	printf(
		'<p><strong>%s.</strong> Прив\'язано товарів: %d. Змінено: %d, без змін: %d, пропущено: %d.</p>',
		esc_html($title),
		(int) ($report['linked'] ?? count($updated) + count($rejected) + (int) ($report['unchanged'] ?? 0)),
		count($updated),
		(int) ($report['unchanged'] ?? 0),
		count($rejected)
	);
	if ($updated) {
		echo '<ul style="list-style:disc;margin-left:20px">';
		foreach ($updated as $row) {
			printf(
				'<li>%s: %s → %s ₴%s</li>',
				esc_html($row['name']),
				esc_html((string) $row['old']),
				esc_html((string) $row['new']),
				$row['stock'] === 'outofstock' ? esc_html(', немає в наявності') : ''
			);
		}
		echo '</ul>';
	}
	if ($rejected) {
		echo '<p>Пропущено, перевірте вручну:</p><ul style="list-style:disc;margin-left:20px">';
		foreach ($rejected as $row) {
			printf('<li>%s: %s</li>', esc_html($row['name']), esc_html(raky_sync_reason($row)));
		}
		echo '</ul>';
	}
}
