<?php
/** Профіль — як ProfileScreen застосунку: ім'я, телефон, email, вихід і видалення акаунта (src/account.ts). */
defined('ABSPATH') || exit;

raky_site_part('header');
?>
<div class="wrap wrap--auth">
	<form class="auth" data-profile novalidate>
		<span class="auth__avatar"><?php echo raky_site_icon('user-circle', 44, 'red'); ?></span>
		<h1 class="page-title auth__title">Профіль</h1>
		<label class="field" for="p-name"><span class="field__label">Ім'я *</span><input id="p-name" name="name" autocomplete="name" maxlength="100"><span class="field__error" data-error="name"></span></label>
		<label class="field" for="p-phone"><span class="field__label">Телефон</span><input id="p-phone" name="phone" type="tel" autocomplete="tel" placeholder="050 123 45 67" maxlength="20"><span class="field__error" data-error="phone"></span></label>
		<label class="field" for="p-email"><span class="field__label">Email (за бажанням)</span><input id="p-email" name="email" type="email" autocomplete="email" maxlength="100"><span class="field__error" data-error="email"></span></label>
		<div class="auth__verify" data-verify></div>
		<button class="btn auth__submit" type="submit">Зберегти</button>
		<button class="btn btn--outline auth__out" type="button" data-signout>Вийти</button>
		<div class="auth__delete" data-delete-box>
			<button type="button" class="link" data-delete>Видалити акаунт</button>
		</div>
		<div data-delete-confirm hidden>
			<div class="warn">Акаунт і список замовлень на цьому пристрої буде видалено. Цю дію не можна скасувати.</div>
			<div class="auth__confirm">
				<button class="btn btn--sm btn--outline" type="button" data-delete-cancel>Скасувати</button>
				<button class="btn btn--sm" type="button" data-delete-yes>Видалити</button>
			</div>
		</div>
	</form>
</div>
<?php
raky_site_part('footer');
