<?php
/**
 * Сторінка «Сайт» в адмінці: контакти, тексти, колір акценту, коди реклами й аналітики.
 * Усе зберігається в одній опції raky_site. Порожнє поле — береться типове значення з raky_site_defaults().
 * Застосунок ці налаштування не читає: його тексти задані в mobile/src/content.ts.
 * Телефони й соцмережі (розділ «Контакти») спільні із застосунком: зберігаються в плагіні (raky_save_contacts), застосунок бере їх з /config.
 */
defined('ABSPATH') || exit;

const RAKY_SITE_ACCENT = '#D92B20';

function raky_site_defaults(): array {
	return [
		'phones'    => "+380936473902\n+380633001953\n+380633418905",
		'hours'     => '10:00–21:00',
		'city'      => 'Миколаїв',
		'telegram'  => 'https://t.me/rakiniko',
		'viber'     => '',
		'instagram' => '',
		'facebook'  => '',
		'tiktok'    => '',
		'tagline'   => 'Раки в Миколаєві: живі й варені, з доставкою',
		'home_desc' => 'Живі та варені раки в Миколаєві. Оберіть калібр і смак, оформіть доставку по місту або самовивіз.',
		'pay_steps' => "Ви надсилаєте замовлення на сайті або в застосунку.\nМенеджер перевіряє наявність і уточнює вагу.\nУ замовленні з'являються реквізити й точна сума.\nПісля оплати готуємо й передаємо замовлення.",
		'cash_note' => 'Також можна оплатити готівкою при отриманні.',
		'privacy'   => "Які дані ми збираємо\nІм'я, телефон, email за бажанням, адреса доставки й історія замовлень.\n\nНавіщо вони потрібні\nЩоб прийняти, підтвердити й доставити замовлення, а також зв'язатися з вами.\n\nКому ми їх передаємо\nКур'єрській службі лише дані, потрібні для доставки.\n\nВаші права\nВи можете змінити дані або попросити їх видалити.\n\nКонтакти\nГаряча лінія: +380 93 647 39 02.",
		'offer'     => "Продавець\nДані ФОП буде додано.\n\nЗамовлення\nЗамовлення діє після підтвердження менеджером. Вага й сума можуть бути уточнені до оплати.\n\nЦіна й оплата\nОплата за реквізитами після підтвердження замовлення або готівкою при отриманні.\n\nДоставка й самовивіз\nДоставка в зонах обслуговування або самовивіз із точок продажу.\n\nПовернення\nУмови буде додано.",
		'legal_ok'  => '',
		'accent'    => RAKY_SITE_ACCENT,
		'gtm'       => '',
		'pixel'     => '',
		'gsc'       => '',
	];
}

/** Значення налаштування: збережене або типове. */
function raky_site_opt(string $key): string {
	static $saved = null;
	$saved ??= (array) get_option('raky_site', []);
	$value = trim((string) ($saved[$key] ?? ''));
	return $value !== '' ? $value : (string) (raky_site_defaults()[$key] ?? '');
}

/** Рядки поля «по одному в рядку». */
function raky_site_lines(string $key): array {
	return array_values(array_filter(array_map('trim', preg_split('/\R/u', raky_site_opt($key)))));
}

/** Юридичний текст: блоки через порожній рядок, перший рядок блоку — заголовок. */
function raky_site_sections(string $key): array {
	$sections = [];
	foreach (preg_split('/\R\s*\R/u', raky_site_opt($key)) as $block) {
		$lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $block))));
		if ($lines) {
			$sections[] = [array_shift($lines), implode(' ', $lines)];
		}
	}
	return $sections;
}

function raky_site_fields(): array {
	$lines = 'по одному в рядку';
	return [
		'Контакти' => [
			'hours'     => ['Години роботи', 'text', 'Наприклад, 10:00–21:00.'],
			'city'      => ['Місто', 'text', ''],
		],
		'Головна сторінка й Google' => [
			'tagline'   => ['Заголовок головної', 'text', 'Великий заголовок на головній і назва сайту в Google.'],
			'home_desc' => ['Опис для Google', 'textarea', 'Текст під назвою сайту в пошуку, до 160 знаків.'],
		],
		'Оплата' => [
			'pay_steps' => ['Як проходить оплата', 'textarea', "Кроки на сторінці «Оплата і доставка», $lines."],
			'cash_note' => ['Примітка про готівку', 'text', ''],
		],
		'Юридичні тексти' => [
			'privacy'  => ['Політика конфіденційності', 'legal', ''],
			'offer'    => ['Публічна оферта', 'legal', ''],
			'legal_ok' => ['Тексти перевірив юрист', 'checkbox', 'Прибирає позначку «Макет» і дозволяє Google показувати ці сторінки.'],
		],
		'Вигляд' => [
			'accent' => ['Колір акценту', 'color', 'Кнопки, ціни, позначки. Фірмовий — ' . RAKY_SITE_ACCENT . ', як у застосунку.'],
		],
		'Реклама й аналітика' => [
			'gtm'   => ['Google Tag Manager', 'text', 'Ідентифікатор контейнера, наприклад GTM-ABC1234. Через нього підключаються GA4 і Google Ads.'],
			'pixel' => ['Meta Pixel', 'text', 'Ідентифікатор пікселя (лише цифри) для реклами у Facebook та Instagram.'],
			'gsc'   => ['Google Search Console', 'text', 'Код підтвердження: значення content із тегу google-site-verification.'],
		],
	];
}

function raky_site_sanitize($input): array {
	$input = is_array($input) ? wp_unslash($input) : [];
	$out   = [];
	foreach (raky_site_fields() as $fields) {
		foreach ($fields as $key => [, $type]) {
			$value = (string) ($input[$key] ?? '');
			switch ($type) {
				case 'url':
					$value = esc_url_raw(trim($value), ['https', 'http', 'viber', 'tg']);
					break;
				case 'checkbox':
					$value = $value !== '' ? '1' : '';
					break;
				case 'color':
					$value = preg_match('/^#[0-9a-f]{6}$/i', trim($value)) ? strtoupper(trim($value)) : RAKY_SITE_ACCENT;
					break;
				case 'textarea':
				case 'legal':
					$value = sanitize_textarea_field($value);
					break;
				default:
					$value = sanitize_text_field($value);
			}
			$out[$key] = $value;
		}
	}
	// Телефони й соцмережі спільні із застосунком: зберігає плагін, у raky_site їх немає.
	if (isset($input['contacts']) && function_exists('raky_save_contacts')) {
		$contacts = (array) $input['contacts'];
		$error    = raky_save_contacts((array) ($contacts['phones'] ?? []), (array) ($contacts['network'] ?? []), (array) ($contacts['url'] ?? []));
		if ($error !== null) {
			add_settings_error('raky_site', 'raky-contacts', $error);
		}
	}
	$checks = [
		'gtm'   => ['/^GTM-[A-Z0-9]+$/', 'Google Tag Manager: ідентифікатор має бути у вигляді GTM-ABC1234.'],
		'pixel' => ['/^\d{6,20}$/', 'Meta Pixel: ідентифікатор складається лише з цифр.'],
		'gsc'   => ['/^[\w-]{10,100}$/', 'Google Search Console: вставте лише значення content, без тегу.'],
	];
	$out['gtm'] = strtoupper($out['gtm']);
	foreach ($checks as $key => [$pattern, $error]) {
		if ($out[$key] !== '' && ! preg_match($pattern, $out[$key])) {
			add_settings_error('raky_site', 'raky-' . $key, $error);
			$out[$key] = '';
		}
	}
	return $out;
}

// Налаштування можуть змінювати й менеджери магазину, не лише адміністратор.
add_filter('option_page_capability_raky_site', static fn () => 'manage_woocommerce');

add_action('admin_init', static function () {
	register_setting('raky_site', 'raky_site', ['type' => 'array', 'sanitize_callback' => 'raky_site_sanitize', 'default' => []]);
});

add_action('admin_menu', static function () {
	add_menu_page('Сайт', 'Сайт', 'manage_woocommerce', 'raky-site', 'raky_site_settings_page', 'dashicons-admin-site-alt3', 57);
});

add_action('admin_enqueue_scripts', static function (string $hook) {
	if ($hook === 'toplevel_page_raky-site') {
		wp_enqueue_style('wp-color-picker');
		wp_enqueue_script('wp-color-picker');
		wp_add_inline_script('wp-color-picker', 'jQuery(function($){$(".raky-color").wpColorPicker();});');
	}
});

function raky_site_settings_page(): void {
	$saved = (array) get_option('raky_site', []);
	?>
	<div class="wrap">
		<h1>Сайт</h1>
		<?php settings_errors(); ?>
		<p>Ці дані бачить лише сайт <a href="<?php echo esc_url(home_url('/')); ?>" target="_blank"><?php echo esc_html(wp_parse_url(home_url(), PHP_URL_HOST)); ?></a>. Товари, ціни, акції, банери й новини редагуються як і раніше — у «Товари», «Застосунок», «Банери», «Новини». Телефони й соцмережі в розділі «Контакти» спільні із застосунком. Юридичні тексти застосунку задані окремо.</p>
		<p>Після збереження сайт оновлюється протягом кількох хвилин.</p>
		<form method="post" action="options.php">
			<?php settings_fields('raky_site'); ?>
			<?php foreach (raky_site_fields() as $section => $fields) : ?>
				<h2 class="title"><?php echo esc_html($section); ?></h2>
				<?php if ($section === 'Контакти' && function_exists('raky_render_contacts_fields')) {
					raky_render_contacts_fields('raky_site[contacts]');
				} ?>
				<?php if ($section === 'Юридичні тексти') : ?>
					<p>Кожен розділ — окремий абзац через порожній рядок. Перший рядок абзацу — заголовок розділу.</p>
				<?php endif; ?>
				<table class="form-table" role="presentation">
					<?php foreach ($fields as $key => [$label, $type, $help]) :
						$id    = 'raky-site-' . $key;
						$name  = 'raky_site[' . $key . ']';
						$value = (string) ($saved[$key] ?? raky_site_defaults()[$key]);
						?>
						<tr>
							<th scope="row"><label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></label></th>
							<td>
								<?php if ($type === 'textarea' || $type === 'legal') : ?>
									<textarea id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" rows="<?php echo $type === 'legal' ? 14 : 4; ?>" class="large-text"><?php echo esc_textarea($value); ?></textarea>
								<?php elseif ($type === 'checkbox') : ?>
									<label><input id="<?php echo esc_attr($id); ?>" type="checkbox" name="<?php echo esc_attr($name); ?>" value="1" <?php checked($value, '1'); ?>> Так</label>
								<?php elseif ($type === 'color') : ?>
									<input id="<?php echo esc_attr($id); ?>" type="text" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>" class="raky-color" data-default-color="<?php echo esc_attr(RAKY_SITE_ACCENT); ?>">
								<?php else : ?>
									<input id="<?php echo esc_attr($id); ?>" type="<?php echo $type === 'url' ? 'url' : 'text'; ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>" class="regular-text">
								<?php endif; ?>
								<?php if ($help !== '') : ?>
									<p class="description"><?php echo esc_html($help); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
			<?php endforeach; ?>
			<?php submit_button('Зберегти'); ?>
		</form>
	</div>
	<?php
}

// Сторінки кешує LiteSpeed: після збереження очищаємо кеш, щоб зміни було видно одразу.
add_action('update_option_raky_site', static function () {
	do_action('litespeed_purge_all');
});

/** Темніший відтінок акценту для наведення (як --red-dark до --red). */
function raky_site_darker(string $hex, float $amount = 0.15): string {
	[$r, $g, $b] = array_map('hexdec', str_split(ltrim($hex, '#'), 2));
	return sprintf('#%02X%02X%02X', $r * (1 - $amount), $g * (1 - $amount), $b * (1 - $amount));
}

// Після стилів теми (вони друкуються на пріоритеті 8), інакше колір перекриється.
add_action('wp_head', static function () {
	$accent = raky_site_opt('accent');
	if (strcasecmp($accent, RAKY_SITE_ACCENT) !== 0) {
		// Світлі фони й тіні — відтінки того самого кольору, як у фірмовій палітрі.
		printf(
			"<style>:root{--red:%1\$s;--red-dark:%2\$s;--soft-ink:%3\$s;--soft:color-mix(in srgb,%1\$s 10%%,#fff);--focus:color-mix(in srgb,%1\$s 25%%,#fff)}</style>\n",
			esc_html($accent),
			esc_html(raky_site_darker($accent)),
			esc_html(raky_site_darker($accent, 0.45))
		);
	}
}, 20);

add_action('wp_head', static function () {
	if ($gsc = raky_site_opt('gsc')) {
		printf("<meta name=\"google-site-verification\" content=\"%s\">\n", esc_attr($gsc));
	}
	if ($gtm = raky_site_opt('gtm')) {
		printf(
			"<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','%s');</script>\n",
			esc_js($gtm)
		);
	}
	if ($pixel = raky_site_opt('pixel')) {
		printf(
			"<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','%s');fbq('track','PageView');</script>\n",
			esc_js($pixel)
		);
	}
}, 1);

add_action('wp_body_open', static function () {
	if ($gtm = raky_site_opt('gtm')) {
		printf('<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=%s" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>', esc_attr($gtm));
	}
});
