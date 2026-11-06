<?php
defined('ABSPATH') || exit;

add_action('admin_menu', static function () {
	add_submenu_page('woocommerce', 'Застосунок', 'Застосунок', 'manage_woocommerce', 'raky-settings', 'raky_render_settings_page');
});

/** Поля застосунку на вкладці «Основне» товару: [ключ мети, підпис, підказка]. */
function raky_product_fields(): array {
	return [
		[RAKY_META_GROUP, 'Група калібрів', 'Однакова назва об\'єднує товари на одній картці, наприклад «Раки варені».'],
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
		'id'      => RAKY_META_UNIT,
		'label'   => 'Одиниця в застосунку',
		'options' => ['pcs' => 'шт', 'kg' => 'кг'],
	]);
	foreach (raky_product_fields() as [$key, $label, $hint]) {
		woocommerce_wp_text_input(['id' => $key, 'label' => $label, 'description' => $hint, 'desc_tip' => true]);
	}
	raky_render_flavor_rows();
	echo '</div>';
});

function raky_flavor_row(string $name = '', string $extra = ''): string {
	return sprintf(
		'<div class="raky-flavor" style="display:flex;gap:6px;margin-bottom:6px">'
		. '<input name="raky_flavor_name[]" placeholder="Назва смаку" value="%s" style="flex:2;float:none;width:auto;min-width:0">'
		. '<input name="raky_flavor_extra[]" type="number" min="0" step="0.01" placeholder="Доплата, ₴" value="%s" style="flex:1;float:none;width:auto;min-width:0">'
		. '<button type="button" class="button raky-flavor-remove" style="flex:none" aria-label="Видалити смак">×</button></div>',
		esc_attr($name),
		esc_attr($extra)
	);
}

/** Смаки: окремий рядок на кожен, доплата за кілограм або штуку. */
function raky_render_flavor_rows(): void {
	$product = wc_get_product(get_the_ID());
	$rows    = $product ? raky_product_flavors($product) : [];
	echo '<div class="form-field" style="padding:9px 12px"><label style="float:none;margin:0 0 6px;display:block;width:auto">Смаки й доплата за кг (шт)</label><div id="raky-flavors">';
	foreach ($rows as $row) {
		echo raky_flavor_row($row['name'], $row['extra'] > 0 ? (string) $row['extra'] : ''); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</div><button type="button" class="button" id="raky-flavor-add">Додати смак</button></div>';
	?>
	<template id="raky-flavor-template"><?php echo raky_flavor_row(); // phpcs:ignore WordPress.Security.EscapeOutput ?></template>
	<script>
	(function () {
		var list = document.getElementById('raky-flavors');
		document.getElementById('raky-flavor-add').addEventListener('click', function () {
			list.appendChild(document.getElementById('raky-flavor-template').content.cloneNode(true));
		});
		list.addEventListener('click', function (event) {
			if (event.target.classList.contains('raky-flavor-remove')) event.target.closest('.raky-flavor').remove();
		});
	})();
	</script>
	<?php
}

add_action('woocommerce_admin_process_product_object', static function (WC_Product $product) {
	$product->update_meta_data(RAKY_META_UNIT, ($_POST[RAKY_META_UNIT] ?? '') === 'kg' ? 'kg' : 'pcs');
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
	$settings = raky_get_settings();
	$sync     = get_option('raky_last_sync');
	$json     = JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT;
	?>
	<div class="wrap">
		<h1>Налаштування застосунку</h1>
		<?php if ($error) : ?><div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div><?php endif; ?>
		<?php if ($saved) : ?><div class="notice notice-success"><p>Збережено.</p></div><?php endif; ?>
		<?php if ($sync) : ?>
			<p>
				Остання синхронізація цін: <?php echo esc_html(wp_date('d.m.Y H:i', $sync['at'])); ?>.
				Оновлено: <?php echo count($sync['report']['updated']); ?>,
				відхилено: <?php echo count($sync['report']['rejected']); ?>,
				невідомих артикулів: <?php echo count($sync['report']['unknown_sku']); ?>.
			</p>
		<?php endif; ?>
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
