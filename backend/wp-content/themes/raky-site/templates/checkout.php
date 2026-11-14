<?php
/** Оформлення: поля форми тут, перевірку, вартість доставки й надсилання робить src/checkout.ts. */
defined('ABSPATH') || exit;

$config = raky_site_config();
raky_site_part('header');
?>
<div class="wrap wrap--mid">
	<?php raky_site_part('crumbs'); ?>
	<h1 class="page-title">Оформлення</h1>
	<form class="checkout-grid" data-checkout novalidate>
		<div class="checkout-form">
			<fieldset class="panel">
				<legend class="panel__title">Контакти</legend>
				<label class="field" for="f-name"><span class="field__label">Ім'я *</span><input id="f-name" name="name" autocomplete="name" required maxlength="100"><span class="field__error" data-error="name"></span></label>
				<label class="field" for="f-phone"><span class="field__label">Телефон *</span><input id="f-phone" name="phone" type="tel" autocomplete="tel" placeholder="050 123 45 67" required maxlength="20"><span class="field__error" data-error="phone"></span></label>
				<label class="field" for="f-email"><span class="field__label">Email (за бажанням)</span><input id="f-email" name="email" type="email" autocomplete="email" maxlength="100"><span class="field__error" data-error="email"></span></label>
			</fieldset>

			<fieldset class="panel">
				<legend class="panel__title">Як отримати</legend>
				<div class="toggles" role="radiogroup" aria-label="Як отримати">
					<label class="toggle"><input type="radio" name="delivery_type" value="delivery" checked><span>Доставка</span></label>
					<label class="toggle"><input type="radio" name="delivery_type" value="pickup"><span>Самовивіз</span></label>
				</div>
				<div data-when="delivery">
					<label class="field" for="f-address"><span class="field__label">Адреса доставки *</span><input id="f-address" name="address" autocomplete="street-address" placeholder="Вулиця, будинок, квартира" maxlength="200"><span class="field__error" data-error="address"></span></label>
					<p class="muted note" data-quote-note hidden>Не вдалося точно знайти адресу. Вартість доставки уточнить менеджер.</p>
					<p class="muted note"><a href="<?php echo esc_url(raky_site_url('dostavka')); ?>" target="_blank">Зони й вартість доставки на карті</a></p>
				</div>
				<div data-when="pickup" hidden>
					<p class="field__label">Точка самовивозу</p>
					<?php foreach ($config['outlets'] as $index => $outlet) : ?>
						<label class="row-btn row-btn--radio"><input type="radio" name="outlet_id" value="<?php echo esc_attr($outlet['id']); ?>"<?php echo $index === 0 ? ' checked' : ''; ?>><?php echo raky_site_icon('map-pin', 18); ?><span><?php echo esc_html($outlet['name']); ?> <span class="muted">· <?php echo esc_html(mb_strtolower($outlet['hours'])); ?></span></span></label>
					<?php endforeach; ?>
				</div>
			</fieldset>

			<fieldset class="panel">
				<legend class="panel__title">Готуємо на</legend>
				<div class="toggles" role="radiogroup" aria-label="Готуємо на">
					<label class="toggle"><input type="radio" name="when" value="asap" checked><span>Якнайшвидше</span></label>
					<label class="toggle"><input type="radio" name="when" value="scheduled"><span>Дата й час</span></label>
				</div>
				<div class="ready" data-ready hidden>
					<label class="field" for="f-ready_date"><span class="field__label">День</span><select id="f-ready_date" name="ready_date"></select></label>
					<label class="field" for="f-ready_hour"><span class="field__label">Година</span><select id="f-ready_hour" name="ready_hour"></select></label>
					<label class="field" for="f-ready_minute"><span class="field__label">Хвилини</span><select id="f-ready_minute" name="ready_minute"></select></label>
				</div>
				<span class="field__error" data-error="ready"></span>
			</fieldset>

			<fieldset class="panel">
				<legend class="panel__title">Коментар</legend>
				<label class="field" for="f-comment"><span class="sr-only">Коментар</span><textarea id="f-comment" name="comment" rows="3" maxlength="500" placeholder="Побажання до замовлення"></textarea></label>
			</fieldset>

			<fieldset class="panel">
				<legend class="panel__title">Спосіб оплати</legend>
				<div class="toggles" role="radiogroup" aria-label="Спосіб оплати">
					<label class="toggle"><input type="radio" name="payment_method" value="requisites" checked><span>На реквізити</span></label>
					<label class="toggle"><input type="radio" name="payment_method" value="cash"><span>Готівкою</span></label>
				</div>
				<p class="muted note" data-pay-note>Реквізити з'являться в замовленні після підтвердження менеджером.</p>
			</fieldset>
		</div>
		<aside class="summary panel" data-summary aria-live="polite">
			<div class="loading" aria-label="Завантаження"></div>
		</aside>
	</form>
	<noscript><p>Щоб оформити замовлення, увімкніть JavaScript або зателефонуйте: <a href="tel:<?php echo esc_attr(RAKY_SITE_PHONES[0]); ?>"><?php echo esc_html(raky_site_pretty_phone(RAKY_SITE_PHONES[0])); ?></a></p></noscript>
</div>
<?php
raky_site_part('footer');
