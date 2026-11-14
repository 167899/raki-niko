<?php
/** Вхід і реєстрація — як LoginScreen застосунку: ім'я й телефон, акаунт зберігається в цьому браузері (src/account.ts). */
defined('ABSPATH') || exit;

raky_site_part('header');
?>
<div class="wrap wrap--auth">
	<form class="auth" data-login novalidate>
		<img class="auth__logo" src="<?php echo esc_url(get_theme_file_uri('assets/logo.png')); ?>" width="78" height="93" alt="">
		<h1 class="page-title auth__title" data-login-title>Вхід</h1>
		<p class="auth__lead" data-login-lead>Увійдіть у свій акаунт.</p>
		<label class="field" for="l-name" data-login-name hidden><span class="field__label">Ім'я *</span><input id="l-name" name="name" autocomplete="name" maxlength="100"><span class="field__error" data-error="name"></span></label>
		<label class="field" for="l-phone"><span class="field__label" data-login-phone-label>Телефон</span><input id="l-phone" name="phone" type="tel" autocomplete="tel" placeholder="050 123 45 67" maxlength="20"><span class="field__error" data-error="phone"></span></label>
		<button class="btn auth__submit" type="submit" data-login-submit>Увійти</button>
		<div class="auth__or"><span>або</span></div>
		<div data-tg-login></div>
		<p class="auth__tg-hint">Через Telegram ви побачите всі свої замовлення — і з сайту, і з застосунку.</p>
		<button class="auth__social" type="button" data-social="Google"><?php echo raky_site_icon('brand-google', 18); ?>Увійти через Google</button>
		<button class="auth__social auth__social--dark" type="button" data-social="Apple"><?php echo raky_site_icon('brand-apple', 18); ?>Увійти через Apple</button>
		<p class="auth__switch"><span data-login-switch-text>Немає акаунта?</span> <button type="button" class="link" data-login-switch>Зареєструватися</button></p>
		<p class="auth__fine">Продовжуючи, ви приймаєте <a href="<?php echo esc_url(raky_site_url('oferta')); ?>">Публічну оферту</a> і <a href="<?php echo esc_url(raky_site_url('pryvatnist')); ?>">Політику конфіденційності</a>.</p>
	</form>
</div>
<?php
raky_site_part('footer');
