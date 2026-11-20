<?php
/**
 * Сторінка «Сайт» в адмінці: контакти, тексти, колір акценту, фон сайту, коди реклами й аналітики.
 * Усе зберігається в одній опції raky_site. Порожнє поле — береться типове значення з raky_site_defaults().
 * Застосунок бере звідси лише години роботи й текст «Про компанію» (/config), решта його текстів — у mobile/src/content.ts.
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
		'about'     => 'Raki Niko продає раків у Миколаєві.',
		'privacy'   => "Які дані ми збираємо\nІм'я, телефон, email за бажанням, адреса доставки й історія замовлень.\n\nНавіщо вони потрібні\nЩоб прийняти, підтвердити й доставити замовлення, а також зв'язатися з вами.\n\nКому ми їх передаємо\nКур'єрській службі лише дані, потрібні для доставки.\n\nВаші права\nВи можете змінити дані або попросити їх видалити.\n\nКонтакти\nГаряча лінія: +380 93 647 39 02.",
		'offer'     => "Продавець\nДані ФОП буде додано.\n\nЗамовлення\nЗамовлення діє після підтвердження менеджером. Вага й сума можуть бути уточнені до оплати.\n\nЦіна й оплата\nОплата за реквізитами після підтвердження замовлення або готівкою при отриманні.\n\nДоставка й самовивіз\nДоставка в зонах обслуговування або самовивіз із точок продажу.\n\nПовернення\nУмови буде додано.",
		'legal_ok'  => '',
		'accent'    => RAKY_SITE_ACCENT,
		'bg_mode'   => 'none',
		'bg_color'  => '#F4F1EE',
		'bg_image'  => '',
		'bg_scroll' => 'fixed',
		'bg_layer'  => 'none',
		'bg_layer_color'    => '#FFFFFF',
		'bg_layer_opacity'  => '60',
		'bg_layer_color2'   => '#FFFFFF',
		'bg_layer_opacity2' => '95',
		'bg_layer_dir'      => 'to bottom',
		'bg_text'           => 'auto',
		'bg_text_color'     => '#111111',
		'header_bg'         => '#FFFFFF',
		'header_text'       => 'auto',
		'header_text_color' => '#111111',
		'footer_bg'         => '#FAFAFA',
		'footer_text'       => 'auto',
		'footer_text_color' => '#111111',
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
			'hours'     => ['Години роботи', 'text', 'Наприклад, 10:00–21:00. Показуються на сайті й у застосунку.'],
			'city'      => ['Місто', 'text', ''],
		],
		'Про компанію' => [
			'about' => ['Хто ми', 'about', 'Текст на сторінці «Про компанію» на сайті й у застосунку. Абзаци розділяйте порожнім рядком.'],
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
		// 4-й елемент — варіанти списку; 5-й — коли показувати поле: [інше поле => 'значення,значення'].
		'Фон сайту' => [
			'bg_mode'           => ['Фон', 'select', 'Фон усього сайту під шапкою, картками й низом сторінки.', ['none' => 'Звичайний білий', 'color' => 'Колір', 'image' => 'Картинка']],
			'bg_color'          => ['Колір фону', 'color', 'Для картинки — колір, який видно, поки вона завантажується.', null, ['bg_mode' => 'color,image']],
			'bg_image'          => ['Картинка', 'image', 'Краще горизонтальна, від 1920 пікселів завширшки, до 500 КБ. На телефоні показується середина картинки.', null, ['bg_mode' => 'image']],
			'bg_scroll'         => ['Картинка під час прокручування', 'select', '', ['fixed' => 'Стоїть на місці', 'scroll' => 'Прокручується разом зі сторінкою'], ['bg_mode' => 'image']],
			'bg_layer'          => ['Накладання поверх картинки', 'select', 'Щоб текст на фоні добре читався.', ['none' => 'Без накладання', 'color' => 'Колір із прозорістю', 'gradient' => 'Градієнт'], ['bg_mode' => 'image']],
			'bg_layer_color'    => ['Колір накладання', 'color', 'Для градієнта — його перший колір.', null, ['bg_mode' => 'image', 'bg_layer' => 'color,gradient']],
			'bg_layer_opacity'  => ['Непрозорість, %', 'percent', '0 — картинку видно повністю, 100 — суцільний колір.', null, ['bg_mode' => 'image', 'bg_layer' => 'color,gradient']],
			'bg_layer_color2'   => ['Другий колір градієнта', 'color', '', null, ['bg_mode' => 'image', 'bg_layer' => 'gradient']],
			'bg_layer_opacity2' => ['Непрозорість другого кольору, %', 'percent', '', null, ['bg_mode' => 'image', 'bg_layer' => 'gradient']],
			'bg_layer_dir'      => ['Напрямок градієнта', 'select', '', ['to bottom' => 'Згори вниз', 'to top' => 'Знизу вгору', 'to right' => 'Зліва направо', '135deg' => 'По діагоналі'], ['bg_mode' => 'image', 'bg_layer' => 'gradient']],
			'bg_text'           => ['Колір тексту на фоні', 'select', 'Заголовки й підписи просто на фоні. Текст у білих картках, полях і кнопках лишається темним.', ['auto' => 'Автоматично: світлий на темному, темний на світлому', 'custom' => 'Свій колір'], ['bg_mode' => 'color,image']],
			'bg_text_color'     => ['Свій колір тексту', 'color', '', null, ['bg_mode' => 'color,image', 'bg_text' => 'custom']],
		],
		'Шапка й низ сайту' => [
			'header_bg'         => ['Колір шапки', 'color', 'Верхня смуга з логотипом, меню й кошиком.'],
			'header_text'       => ['Текст і значки шапки', 'select', '', ['auto' => 'Автоматично: світлий на темному, темний на світлому', 'custom' => 'Свій колір']],
			'header_text_color' => ['Свій колір тексту шапки', 'color', '', null, ['header_text' => 'custom']],
			'footer_bg'         => ['Колір низу сайту', 'color', 'Блок з меню, контактами й соцмережами внизу сторінки.'],
			'footer_text'       => ['Текст і значки низу', 'select', '', ['auto' => 'Автоматично: світлий на темному, темний на світлому', 'custom' => 'Свій колір']],
			'footer_text_color' => ['Свій колір тексту низу', 'color', '', null, ['footer_text' => 'custom']],
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
		foreach ($fields as $key => $field) {
			$type  = $field[1];
			$value = (string) ($input[$key] ?? '');
			switch ($type) {
				case 'select':
					$value = array_key_exists($value, $field[3]) ? $value : (string) raky_site_defaults()[$key];
					break;
				case 'image':
					$value = esc_url_raw(trim($value), ['https', 'http']);
					break;
				case 'percent':
					$value = is_numeric(trim($value)) ? (string) max(0, min(100, (int) $value)) : (string) raky_site_defaults()[$key];
					break;
				case 'url':
					$value = esc_url_raw(trim($value), ['https', 'http', 'viber', 'tg']);
					break;
				case 'checkbox':
					$value = $value !== '' ? '1' : '';
					break;
				case 'color':
					$value = preg_match('/^#[0-9a-f]{6}$/i', trim($value)) ? strtoupper(trim($value)) : (string) (raky_site_defaults()[$key] ?? RAKY_SITE_ACCENT);
					break;
				case 'textarea':
				case 'about':
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
	if ($out['bg_mode'] === 'image' && $out['bg_image'] === '') {
		add_settings_error('raky_site', 'raky-bg', 'Фон сайту: оберіть картинку. Поки її немає, фон лишається звичайним білим.', 'warning');
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
		wp_enqueue_media();
		wp_add_inline_script('wp-color-picker', <<<'JS'
jQuery(function ($) {
	$('.raky-color').wpColorPicker();
	// Поля фону показуємо лише тоді, коли вони потрібні (data-when: {"поле": "значення,значення"}).
	const sync = () => $('tr[data-when]').each(function () {
		const when = $(this).data('when');
		$(this).toggle(Object.keys(when).every((key) => String(when[key]).split(',').includes($('[name="raky_site[' + key + ']"]').val())));
	});
	$(document).on('change', 'select[name^="raky_site["]', sync);
	sync();
	// Вибір картинки з медіабібліотеки.
	$('.raky-image').each(function () {
		const box = $(this), input = box.find('input'), preview = box.find('img');
		const paint = () => { preview.attr('src', input.val()).toggle(!!input.val()); box.find('.raky-image-clear').toggle(!!input.val()); };
		box.find('.raky-image-pick').on('click', (event) => {
			event.preventDefault();
			const frame = wp.media({ title: 'Картинка для фону сайту', button: { text: 'Обрати' }, library: { type: 'image' }, multiple: false });
			frame.on('select', () => { input.val(frame.state().get('selection').first().toJSON().url); paint(); });
			frame.open();
		});
		box.find('.raky-image-clear').on('click', (event) => { event.preventDefault(); input.val(''); paint(); });
		paint();
	});
});
JS
		);
	}
});

function raky_site_settings_page(): void {
	$saved = (array) get_option('raky_site', []);
	?>
	<div class="wrap">
		<h1>Сайт</h1>
		<?php settings_errors(); ?>
		<p>Ці дані бачить лише сайт <a href="<?php echo esc_url(home_url('/')); ?>" target="_blank"><?php echo esc_html(wp_parse_url(home_url(), PHP_URL_HOST)); ?></a>. Товари, ціни, акції, банери й новини редагуються як і раніше — у «Товари», «Застосунок», «Банери», «Новини». Телефони, соцмережі, години роботи й текст «Про компанію» спільні із застосунком. Юридичні тексти застосунку задані окремо.</p>
		<p>Після збереження сайт оновлюється протягом кількох хвилин.</p>
		<form method="post" action="options.php">
			<?php settings_fields('raky_site'); ?>
			<?php foreach (raky_site_fields() as $section => $fields) : ?>
				<h2 class="title"><?php echo esc_html($section); ?></h2>
				<?php if ($section === 'Контакти' && function_exists('raky_render_contacts_fields')) {
					raky_render_contacts_fields('raky_site[contacts]');
				} ?>
				<?php if ($section === 'Про компанію') : ?>
					<p>Телефони, соцмережі й години роботи на цій сторінці беруться з розділу «Контакти» вище, адреси — з <a href="<?php echo esc_url(admin_url('admin.php?page=raky-settings')); ?>">«WooCommerce» → «Застосунок» → «Точки»</a> (поле name).</p>
				<?php endif; ?>
				<?php if ($section === 'Юридичні тексти') : ?>
					<p>Кожен розділ — окремий абзац через порожній рядок. Перший рядок абзацу — заголовок розділу.</p>
				<?php endif; ?>
				<table class="form-table" role="presentation">
					<?php foreach ($fields as $key => $field) :
						[$label, $type, $help] = $field;
						$id    = 'raky-site-' . $key;
						$name  = 'raky_site[' . $key . ']';
						$value = (string) ($saved[$key] ?? raky_site_defaults()[$key]);
						?>
						<tr<?php echo ! empty($field[4]) ? ' data-when="' . esc_attr(wp_json_encode($field[4])) . '"' : ''; ?>>
							<th scope="row"><label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></label></th>
							<td>
								<?php if ($type === 'textarea' || $type === 'about' || $type === 'legal') : ?>
									<textarea id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" rows="<?php echo $type === 'legal' ? 14 : ($type === 'about' ? 8 : 4); ?>" class="large-text"><?php echo esc_textarea($value); ?></textarea>
								<?php elseif ($type === 'checkbox') : ?>
									<label><input id="<?php echo esc_attr($id); ?>" type="checkbox" name="<?php echo esc_attr($name); ?>" value="1" <?php checked($value, '1'); ?>> Так</label>
								<?php elseif ($type === 'color') : ?>
									<input id="<?php echo esc_attr($id); ?>" type="text" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>" class="raky-color" data-default-color="<?php echo esc_attr(raky_site_defaults()[$key]); ?>">
								<?php elseif ($type === 'select') : ?>
									<select id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>">
										<?php foreach ($field[3] as $option => $text) : ?>
											<option value="<?php echo esc_attr($option); ?>" <?php selected($value, (string) $option); ?>><?php echo esc_html($text); ?></option>
										<?php endforeach; ?>
									</select>
								<?php elseif ($type === 'percent') : ?>
									<input id="<?php echo esc_attr($id); ?>" type="number" min="0" max="100" step="5" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>" class="small-text"> %
								<?php elseif ($type === 'image') : ?>
									<div class="raky-image">
										<input id="<?php echo esc_attr($id); ?>" type="hidden" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>">
										<img src="" alt="" style="display:none;max-width:320px;max-height:180px;border-radius:6px;margin-bottom:8px">
										<p><button type="button" class="button raky-image-pick">Обрати картинку</button> <button type="button" class="button-link raky-image-clear" style="display:none">Прибрати</button></p>
									</div>
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

/** rgba() з #RRGGBB і непрозорості у відсотках. */
function raky_site_rgba(string $hex, string $percent): string {
	[$r, $g, $b] = array_map('hexdec', str_split(ltrim($hex, '#'), 2));
	return sprintf('rgba(%d,%d,%d,%s)', $r, $g, $b, round(max(0, min(100, (int) $percent)) / 100, 2));
}

/** Відносна яскравість кольору за WCAG (0 — чорний, 1 — білий). */
function raky_site_luminance(array $rgb): float {
	$channel = static function (float $value): float {
		$value /= 255;
		return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
	};
	return 0.2126 * $channel($rgb[0]) + 0.7152 * $channel($rgb[1]) + 0.0722 * $channel($rgb[2]);
}

/** @return int[] [r, g, b] */
function raky_site_rgb(string $hex): array {
	return array_map('hexdec', str_split(ltrim($hex, '#'), 2));
}

function raky_site_hex(array $rgb): string {
	return vsprintf('#%02X%02X%02X', array_map(static fn ($value) => max(0, min(255, (int) round($value))), $rgb));
}

/** Колір a поверх b з непрозорістю alpha (0…1). */
function raky_site_mix(array $a, array $b, float $alpha): array {
	return [$a[0] * $alpha + $b[0] * (1 - $alpha), $a[1] * $alpha + $b[1] * (1 - $alpha), $a[2] * $alpha + $b[2] * (1 - $alpha)];
}

/**
 * Середній колір картинки (зменшуємо до 1×1 піксела). Рахуємо раз і запам'ятовуємо.
 * @return int[]|null
 */
function raky_site_image_average(string $url): ?array {
	$key    = 'raky_bg_avg_' . md5($url);
	$cached = get_transient($key);
	if (is_array($cached)) {
		return $cached;
	}
	$id   = attachment_url_to_postid($url);
	$file = $id ? get_attached_file($id) : '';
	if (! $file || ! is_readable($file) || ! function_exists('imagecreatefromstring')) {
		return null;
	}
	$image = @imagecreatefromstring((string) file_get_contents($file));
	if (! $image) {
		return null;
	}
	$dot = imagecreatetruecolor(1, 1);
	imagecopyresampled($dot, $image, 0, 0, 0, 0, 1, 1, imagesx($image), imagesy($image));
	$color = imagecolorat($dot, 0, 0);
	$rgb   = [($color >> 16) & 0xFF, ($color >> 8) & 0xFF, $color & 0xFF];
	set_transient($key, $rgb, MONTH_IN_SECONDS);
	return $rgb;
}

/** Колір, який око бачить на фоні сторінки: колір або картинка разом із накладанням. */
function raky_site_background_rgb(): ?array {
	$mode = raky_site_opt('bg_mode');
	if ($mode === 'color') {
		return raky_site_rgb(raky_site_opt('bg_color'));
	}
	if ($mode !== 'image' || raky_site_opt('bg_image') === '') {
		return null;
	}
	$rgb = raky_site_image_average(raky_site_opt('bg_image')) ?? raky_site_rgb(raky_site_opt('bg_color'));
	switch (raky_site_opt('bg_layer')) {
		case 'color':
			return raky_site_mix(raky_site_rgb(raky_site_opt('bg_layer_color')), $rgb, (int) raky_site_opt('bg_layer_opacity') / 100);
		case 'gradient':
			// Середина градієнта: обидва кольори порівну.
			$first  = raky_site_mix(raky_site_rgb(raky_site_opt('bg_layer_color')), $rgb, (int) raky_site_opt('bg_layer_opacity') / 100);
			$second = raky_site_mix(raky_site_rgb(raky_site_opt('bg_layer_color2')), $rgb, (int) raky_site_opt('bg_layer_opacity2') / 100);
			return raky_site_mix($first, $second, 0.5);
	}
	return $rgb;
}

/**
 * Змінні тексту для області з фоном $bg: --ink (заголовки) і --text (звичайний, трохи м'якший).
 * $mode 'auto' — білий чи темний, що контрастніший; 'custom' — свій колір.
 */
function raky_site_text_vars(array $bg, string $mode, string $custom): string {
	if ($mode === 'custom') {
		$ink = raky_site_rgb($custom);
	} else {
		$light = raky_site_luminance($bg);
		// Контраст із білим (1.05 / (L + 0.05)) проти контрасту з темним (#111111).
		$ink = 1.05 / ($light + 0.05) >= ($light + 0.05) / (raky_site_luminance([17, 17, 17]) + 0.05) ? [255, 255, 255] : [17, 17, 17];
	}
	return sprintf('--ink:%s;--text:%s;color:var(--text)', raky_site_hex($ink), raky_site_hex(raky_site_mix($ink, $bg, 0.82)));
}

/** Кольори шапки й низу сайту, якщо вони відрізняються від стандартних. */
function raky_site_frame_css(): string {
	$css = '';
	// Нижнє меню на телефоні — у кольорах низу сайту; обідок кошика — того ж кольору, що й меню.
	foreach (['header' => '.top', 'footer' => '.foot,.bar'] as $area => $selector) {
		$bg   = raky_site_opt($area . '_bg');
		$mode = raky_site_opt($area . '_text');
		// Стандартні шапку й низ пропускаємо, лише якщо й фон сайту стандартний:
		// інакше вони успадкували б світлий текст сторінки й стали б білим по білому.
		if (strcasecmp($bg, raky_site_defaults()[$area . '_bg']) === 0 && $mode === 'auto' && raky_site_background_rgb() === null) {
			continue;
		}
		$css .= sprintf('%s{background:%s;%s}', $selector, $bg, raky_site_text_vars(raky_site_rgb($bg), $mode, raky_site_opt($area . '_text_color')));
		if ($area === 'footer') {
			$css .= sprintf('.bar__item--cart .bar__icon{border-color:%s}', $bg);
			// Смуга під меню, де лежать системні кнопки телефону (коли браузер малює сторінку до самого низу), — на тон темніша за низ сайту.
			$under = raky_site_hex(raky_site_mix(raky_site_rgb($bg), [0, 0, 0], 0.88));
			$css  .= sprintf('.bar{background:linear-gradient(to top,%1$s env(safe-area-inset-bottom),%2$s env(safe-area-inset-bottom))}', $under, $bg);
		} else {
			// Бічне меню й список телефонів — на тон темніші за шапку, текст підбирається під цей колір.
			// Смуги й блок «Гість» — напівпрозорі від кольору тексту, щоб не світилися білим на темному.
			$drop = raky_site_mix(raky_site_rgb($bg), [0, 0, 0], 0.88);
			$css .= sprintf(
				'.drawer__panel,.phones-pop{background:%s;%s}'
				. '.drawer__who{background:color-mix(in srgb,var(--ink) 8%%,transparent)}.drawer__who-text{color:var(--ink)}'
				. '.drawer__list a{border-bottom-color:color-mix(in srgb,var(--ink) 12%%,transparent)}.phones-pop{border-color:color-mix(in srgb,var(--ink) 15%%,transparent)}.phones-pop a:hover{background:color-mix(in srgb,var(--ink) 10%%,transparent)}',
				raky_site_hex($drop),
				raky_site_text_vars($drop, $mode, raky_site_opt('header_text_color'))
			);
		}
	}
	return $css;
}

/** CSS фону сайту з налаштувань або '' (звичайний білий). */
function raky_site_background_css(): string {
	$mode  = raky_site_opt('bg_mode');
	$color = raky_site_opt('bg_color');
	$image = raky_site_opt('bg_image');
	$rgb  = raky_site_background_rgb();
	// На кольоровому фоні картки акцій обведені білим, як банери на головній.
	// Прозорі картки (кошик, оформлення, замовлення…) стають матовим склом у тон фону.
	$text = $rgb ? sprintf(
		// Блок «Додати в кошик» теж скляний: текст у ньому — як на фоні сторінки (inherit), а не темний, як на білому.
		'body{%s;--glass:%s;--glass-blur:blur(18px) saturate(1.2)}.promo{border-color:var(--white)}'
		. '.buy{background:var(--glass);-webkit-backdrop-filter:var(--glass-blur);backdrop-filter:var(--glass-blur);--ink:inherit;--text:inherit;color:var(--text)}'
		// Уся колонка товару (назва, ціна, смак, вага, кошик) — одна скляна картка в рамці.
		// На комп'ютері блок кошика всередині без власного скла, лише з лінією зверху; на телефоні він прилипає до низу екрана й лишається скляним.
		. '.product__info{align-self:start;padding:16px;border:1px solid var(--line);border-radius:var(--radius);background:var(--glass);-webkit-backdrop-filter:var(--glass-blur);backdrop-filter:var(--glass-blur)}'
		. '@media (min-width:900px){.product__info{padding:20px 22px}.product__info .buy{background:none;-webkit-backdrop-filter:none;backdrop-filter:none;border:0;border-top:1px solid var(--line);border-radius:0;padding:16px 0 0}}',
		raky_site_text_vars($rgb, raky_site_opt('bg_text'), raky_site_opt('bg_text_color')),
		raky_site_rgba(raky_site_hex($rgb), '55')
	) : '';
	// Блок пошуку й категорій закріплений угорі: фарбуємо його фоном сайту, щоб під ним не просвічували товари.
	if ($mode === 'color') {
		return sprintf('body{background:%1$s}.catbar{background:%1$s}', $color) . $text;
	}
	if ($mode !== 'image' || $image === '') {
		return '';
	}
	$layer = '';
	switch (raky_site_opt('bg_layer')) {
		case 'color':
			$tint  = raky_site_rgba(raky_site_opt('bg_layer_color'), raky_site_opt('bg_layer_opacity'));
			$layer = "linear-gradient($tint,$tint),";
			break;
		case 'gradient':
			$layer = sprintf(
				'linear-gradient(%s,%s,%s),',
				raky_site_opt('bg_layer_dir'),
				raky_site_rgba(raky_site_opt('bg_layer_color'), raky_site_opt('bg_layer_opacity')),
				raky_site_rgba(raky_site_opt('bg_layer_color2'), raky_site_opt('bg_layer_opacity2'))
			);
	}
	$url = "url('" . esc_url($image) . "')";
	// Матове скло для блоку пошуку, коли збігти з картинкою точно не вийде: фон під ним розмивається
	// й тонується тим кольором, який реально видно на фоні (картинка разом із накладанням), — темним на темному, світлим на світлому.
	$tone    = raky_site_hex(raky_site_background_rgb() ?? raky_site_rgb($color));
	$frosted = sprintf('.catbar{background:%s;-webkit-backdrop-filter:blur(18px) saturate(1.2);backdrop-filter:blur(18px) saturate(1.2)}', raky_site_rgba($tone, '55'));
	if (raky_site_opt('bg_scroll') === 'scroll') {
		// Прокручується: картинка на всю ширину й повторюється донизу; накладання тягнеться на всю сторінку.
		return sprintf('body{background:%s%s center top/100%% auto repeat-y,%s}', $layer, $url, $color) . $frosted . $text;
	}
	// Нерухома: окремий шар на весь екран. background-attachment: fixed не працює на iPhone, а такий шар — працює.
	// Блок пошуку — те саме матове скло, що й у режимі прокручування.
	return sprintf(
		'body{background:%1$s}body::before{content:"";position:fixed;inset:0;z-index:-1;pointer-events:none;background:%2$s%3$s center/cover no-repeat,%1$s}',
		$color,
		$layer,
		$url
	) . $frosted . $text;
}

add_action('wp_head', static function () {
	if ($css = raky_site_background_css() . raky_site_frame_css()) {
		echo '<style id="raky-site-bg">' . strip_tags($css) . "</style>\n";
	}
}, 21);

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
