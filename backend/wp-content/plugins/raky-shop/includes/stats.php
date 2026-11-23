<?php
/**
 * Статистика відвідувань сайту і застосунку.
 *
 * Сторінки сайту кешує LiteSpeed, тож PHP на кожен перегляд не запускається. Тому рахує браузер:
 * після завантаження сторінки скрипт теми надсилає POST /stats/hit. Особистих даних не зберігаємо:
 * ні IP, ні cookie. Чи це новий відвідувач за день, браузер вирішує сам (позначка в localStorage).
 *
 * Таблиця: день + показник + об'єкт → кількість. Показники:
 *   view    — перегляд сторінки, item = адреса сторінки, label = її назва;
 *   visitor — новий відвідувач за день, item = '';
 *   device  — пристрій нового відвідувача, item = mobile | desktop;
 *   source  — звідки прийшли (перша сторінка сеансу), item = google | facebook | … | direct | домен;
 *   app     — події застосунку, item = install-android | open-ios | …
 *   signup  — реєстрація: перший вхід через Telegram із номером, якого ще не було.
 *             item = mobile | desktop | app | earlier (увійшли до початку обліку, пристрій невідомий)
 */
defined('ABSPATH') || exit;

const RAKY_STATS_DB_VERSION = 1;
const RAKY_STATS_KEEP_DAYS  = 800;
const RAKY_STATS_SIGNUPS   = 'raky_signup_phones'; // [sha256(номера) => день першого входу]

function raky_stats_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'raky_stats';
}

function raky_stats_install(): void {
	if ((int) get_option('raky_stats_db') === RAKY_STATS_DB_VERSION) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta('CREATE TABLE ' . raky_stats_table() . " (
  day date NOT NULL,
  metric varchar(16) NOT NULL,
  item varchar(150) NOT NULL DEFAULT '',
  label varchar(150) NOT NULL DEFAULT '',
  hits int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (day,metric,item)
) " . $wpdb->get_charset_collate() . ';');
	update_option('raky_stats_db', RAKY_STATS_DB_VERSION);
}
add_action('init', 'raky_stats_install');

function raky_stats_add(string $metric, string $item = '', string $label = '', string $day = ''): void {
	global $wpdb;
	$wpdb->query($wpdb->prepare(
		'INSERT INTO ' . raky_stats_table() . ' (day, metric, item, label, hits) VALUES (%s, %s, %s, %s, 1)
		 ON DUPLICATE KEY UPDATE hits = hits + 1, label = IF(VALUES(label) = \'\', label, VALUES(label))',
		$day !== '' ? $day : current_time('Y-m-d'),
		$metric,
		mb_substr($item, 0, 150),
		mb_substr($label, 0, 150)
	));
}

/**
 * Номери, які вже реєструвалися. Перший раз заповнюємо з чинних сесій входу:
 * ці клієнти увійшли до початку обліку, тож рахуємо їх у день входу з позначкою earlier.
 */
function raky_stats_signup_phones(): array {
	$known = get_option(RAKY_STATS_SIGNUPS, null);
	if (is_array($known)) {
		return $known;
	}
	$known = [];
	foreach (raky_auth_sessions() as $session) {
		$hash = hash('sha256', (string) $session['phone']);
		$day  = wp_date('Y-m-d', (int) $session['created']);
		if (! isset($known[$hash]) || $day < $known[$hash]) {
			$known[$hash] = $day;
		}
	}
	update_option(RAKY_STATS_SIGNUPS, $known, false);
	foreach ($known as $day) {
		raky_stats_add('signup', 'earlier', '', $day);
	}
	return $known;
}

/** Клієнт підтвердив номер у боті. Рахуємо лише перший раз для номера. */
function raky_stats_signup(string $phone, string $source): void {
	$known = raky_stats_signup_phones();
	$hash  = hash('sha256', $phone);
	if (isset($known[$hash])) {
		return;
	}
	$known[$hash] = current_time('Y-m-d');
	update_option(RAKY_STATS_SIGNUPS, $known, false);
	raky_stats_add('signup', in_array($source, ['mobile', 'desktop', 'app'], true) ? $source : 'desktop');
}

/** Домен, з якого прийшли → коротка назва джерела. */
function raky_stats_source(string $referrer): string {
	$host = strtolower((string) wp_parse_url($referrer, PHP_URL_HOST));
	$host = preg_replace('/^(www\.|m\.|l\.|lm\.)/', '', $host);
	if ($host === '' || $host === strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST))) {
		return 'direct';
	}
	$known = [
		'google'    => '/(^|\.)google\./',
		'bing'      => '/(^|\.)bing\.com$/',
		'facebook'  => '/(^|\.)(facebook\.com|fb\.com|fb\.me)$/',
		'instagram' => '/(^|\.)instagram\.com$/',
		'telegram'  => '/(^|\.)(t\.me|telegram\.org|telegram\.me)$/',
		'tiktok'    => '/(^|\.)tiktok\.com$/',
		'viber'     => '/(^|\.)viber\.com$/',
	];
	foreach ($known as $name => $pattern) {
		if (preg_match($pattern, $host)) {
			return $name;
		}
	}
	return mb_substr($host, 0, 100);
}

function raky_rest_stats_hit(WP_REST_Request $request): WP_REST_Response {
	$path = (string) $request->get_param('path');
	// Лише адреса сторінки без параметрів: ?utm=…, ?s=… не роздувають таблицю.
	$path = '/' . ltrim((string) wp_parse_url($path, PHP_URL_PATH), '/');
	if (strlen($path) > 150 || preg_match('#^/(wp-|\?)#', $path)) {
		return new WP_REST_Response(['ok' => false], 400);
	}
	raky_stats_add('view', rawurldecode($path), sanitize_text_field((string) $request->get_param('label')));
	if ($request->get_param('new_visitor')) {
		raky_stats_add('visitor');
		raky_stats_add('device', $request->get_param('mobile') ? 'mobile' : 'desktop');
	}
	if ($request->get_param('new_session')) {
		raky_stats_add('source', raky_stats_source((string) $request->get_param('referrer')));
	}
	// Зрідка прибираємо старі дні, щоб таблиця не росла безкінечно.
	if (wp_rand(1, 500) === 1) {
		global $wpdb;
		$wpdb->query($wpdb->prepare('DELETE FROM ' . raky_stats_table() . ' WHERE day < %s', gmdate('Y-m-d', time() - RAKY_STATS_KEEP_DAYS * DAY_IN_SECONDS)));
	}
	return new WP_REST_Response(['ok' => true]);
}

/** Підготовка для застосунку: перший запуск після встановлення (install) і кожен запуск (open). */
function raky_rest_stats_app(WP_REST_Request $request): WP_REST_Response {
	$event    = (string) $request->get_param('event');
	$platform = (string) $request->get_param('platform');
	if (! in_array($event, ['install', 'open'], true) || ! in_array($platform, ['android', 'ios'], true)) {
		return new WP_REST_Response(['ok' => false], 400);
	}
	raky_stats_add('app', $event . '-' . $platform);
	return new WP_REST_Response(['ok' => true]);
}

add_action('rest_api_init', static function () {
	register_rest_route(RAKY_NS, '/stats/hit', [
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'raky_rest_stats_hit',
	]);
	register_rest_route(RAKY_NS, '/stats/app', [
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'raky_rest_stats_app',
	]);
});

/* ---------- Сторінка в адмінці ---------- */

add_action('admin_menu', static function () {
	add_menu_page('Статистика', 'Статистика', 'manage_woocommerce', 'raky-stats', 'raky_stats_page', 'dashicons-chart-bar', 58);
});

/** Сума за показником по днях: [день => кількість]. */
function raky_stats_by_day(string $metric, string $from): array {
	global $wpdb;
	$rows = $wpdb->get_results($wpdb->prepare('SELECT day, SUM(hits) FROM ' . raky_stats_table() . ' WHERE metric = %s AND day >= %s GROUP BY day', $metric, $from), ARRAY_N);
	return array_map('intval', array_column($rows, 1, 0));
}

/** Найчастіші об'єкти показника: [[item, label, кількість]]. */
function raky_stats_top(string $metric, string $from, int $limit = 0): array {
	global $wpdb;
	$sql = 'SELECT item, MAX(label), SUM(hits) AS total FROM ' . raky_stats_table() . ' WHERE metric = %s AND day >= %s GROUP BY item ORDER BY total DESC';
	if ($limit) {
		$sql .= ' LIMIT ' . $limit;
	}
	return $wpdb->get_results($wpdb->prepare($sql, $metric, $from), ARRAY_N);
}

/**
 * Замовлення, створені від дня $from: кількість, виконані, скасовані, сума й середній чек виконаних, звідки замовляли.
 * Замовлення до початку обліку джерела не мають (source = '').
 */
function raky_stats_orders(string $from): array {
	$stats = ['all' => 0, 'completed' => 0, 'cancelled' => 0, 'revenue' => 0.0, 'source' => ['mobile' => 0, 'desktop' => 0, 'app' => 0, '' => 0]];
	$statuses = array_diff(array_keys(wc_get_order_statuses()), ['wc-checkout-draft']);
	foreach (wc_get_orders(['limit' => -1, 'status' => $statuses, 'date_created' => '>=' . $from, 'type' => 'shop_order']) as $order) {
		$status = $order->get_status();
		if (in_array($status, ['cancelled', 'refunded', 'failed'], true)) {
			$stats['cancelled']++;
			continue;
		}
		$stats['all']++;
		$source = (string) $order->get_meta('_raky_source');
		$stats['source'][isset($stats['source'][$source]) ? $source : '']++;
		if ($status === 'completed') {
			$stats['completed']++;
			$stats['revenue'] += (float) $order->get_total();
		}
	}
	return $stats;
}

function raky_stats_source_name(string $item): string {
	return [
		'direct'    => 'Прямі заходи й закладки',
		'google'    => 'Пошук Google',
		'bing'      => 'Пошук Bing',
		'facebook'  => 'Facebook',
		'instagram' => 'Instagram',
		'telegram'  => 'Telegram',
		'tiktok'    => 'TikTok',
		'viber'     => 'Viber',
	][$item] ?? $item;
}

function raky_stats_page(): void {
	$periods = [7 => '7 днів', 30 => '30 днів', 90 => '90 днів', 365 => 'Рік'];
	$days    = (int) ($_GET['days'] ?? 30);
	$days    = isset($periods[$days]) ? $days : 30;
	$today   = current_time('Y-m-d');
	$from    = wp_date('Y-m-d', strtotime($today . ' -' . ($days - 1) . ' days'));

	$views    = raky_stats_by_day('view', $from);
	$visitors = raky_stats_by_day('visitor', $from);
	$devices  = array_column(raky_stats_top('device', $from), 2, 0);
	$sources  = raky_stats_top('source', $from);
	$pages    = raky_stats_top('view', $from, 15);
	$app      = array_column(raky_stats_top('app', $from), 2, 0);
	$app_all  = array_column(raky_stats_top('app', '2000-01-01'), 2, 0);
	raky_stats_signup_phones();
	$orders      = raky_stats_orders($from);
	$orders_all  = raky_stats_orders('2000-01-01');
	$signups     = array_column(raky_stats_top('signup', $from), 2, 0);
	$signups_all = array_column(raky_stats_top('signup', '2000-01-01'), 2, 0);

	$total_visitors = array_sum($visitors);
	$total_views    = array_sum($views);
	$mobile_share   = array_sum($devices) ? round(100 * ($devices['mobile'] ?? 0) / array_sum($devices)) : 0;
	$today_visitors = $visitors[$today] ?? 0;
	$sources_total  = array_sum(array_column($sources, 2)) ?: 1;

	// Дні графіка, зокрема порожні.
	$chart = [];
	for ($i = $days - 1; $i >= 0; $i--) {
		$day         = wp_date('Y-m-d', strtotime($today . ' -' . $i . ' days'));
		$chart[$day] = [$visitors[$day] ?? 0, $views[$day] ?? 0];
	}
	$peak = max(1, ...array_column($chart, 1));
	?>
	<div class="wrap raky-stats">
		<h1>Статистика</h1>
		<style>
			.raky-stats .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin: 16px 0; }
			.raky-stats .card { background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 14px 16px; margin: 0; max-width: none; }
			.raky-stats .card b { display: block; font-size: 28px; line-height: 1.2; margin-top: 4px; }
			.raky-stats .card span { color: #646970; }
			.raky-stats .box { background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 16px; margin-bottom: 16px; }
			.raky-stats .box h2 { margin-top: 0; }
			.raky-stats .chart { display: flex; align-items: flex-end; gap: 2px; height: 180px; border-bottom: 1px solid #dcdcde; }
			.raky-stats .chart div { flex: 1; position: relative; background: #c5d9ed; border-radius: 3px 3px 0 0; min-height: 1px; }
			.raky-stats .chart i { position: absolute; left: 0; right: 0; bottom: 0; background: #2271b1; border-radius: 3px 3px 0 0; }
			.raky-stats .legend { display: flex; gap: 16px; margin-top: 8px; color: #646970; }
			.raky-stats .legend span:not(.muted)::before { content: ''; display: inline-block; width: 10px; height: 10px; border-radius: 2px; margin-right: 6px; background: #2271b1; }
			.raky-stats .legend span.views::before { background: #c5d9ed; }
			.raky-stats .grid2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 16px; }
			.raky-stats table { width: 100%; border-collapse: collapse; }
			.raky-stats td { padding: 6px 4px; border-top: 1px solid #f0f0f1; }
			.raky-stats td.num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
			.raky-stats .bar { height: 6px; background: #2271b1; border-radius: 3px; margin-top: 4px; }
			.raky-stats .muted { color: #646970; }
		</style>

		<p>
			<?php foreach ($periods as $value => $name) : ?>
				<a class="button <?php echo $value === $days ? 'button-primary' : ''; ?>" href="<?php echo esc_url(add_query_arg('days', $value)); ?>"><?php echo esc_html($name); ?></a>
			<?php endforeach; ?>
		</p>

		<h2>Сайт</h2>
		<div class="cards">
			<div class="card"><span>Відвідувачів сьогодні</span><b><?php echo esc_html(number_format_i18n($today_visitors)); ?></b></div>
			<div class="card"><span>Відвідувачів за період</span><b><?php echo esc_html(number_format_i18n($total_visitors)); ?></b></div>
			<div class="card"><span>Переглядів сторінок</span><b><?php echo esc_html(number_format_i18n($total_views)); ?></b></div>
			<div class="card"><span>З телефонів</span><b><?php echo esc_html($mobile_share); ?>%</b></div>
		</div>

		<div class="box">
			<h2>По днях</h2>
			<div class="chart">
				<?php foreach ($chart as $day => [$people, $seen]) : ?>
					<div style="height: <?php echo esc_attr(round(100 * $seen / $peak, 1)); ?>%" title="<?php echo esc_attr(wp_date('j F', strtotime($day)) . ': відвідувачів ' . $people . ', переглядів ' . $seen); ?>">
						<i style="height: <?php echo esc_attr($seen ? round(100 * $people / $seen, 1) : 0); ?>%"></i>
					</div>
				<?php endforeach; ?>
			</div>
			<div class="legend"><span>Відвідувачі</span><span class="views">Перегляди сторінок</span><span class="muted">Наведіть на стовпчик, щоб побачити числа.</span></div>
		</div>

		<div class="grid2">
			<div class="box">
				<h2>Популярні сторінки</h2>
				<?php if (! $pages) : ?>
					<p class="muted">Ще немає даних.</p>
				<?php else : ?>
					<table>
						<?php foreach ($pages as [$path, $label, $total]) : ?>
							<tr>
								<td><a href="<?php echo esc_url(home_url($path)); ?>" target="_blank" rel="noopener"><?php echo esc_html($label !== '' ? $label : $path); ?></a></td>
								<td class="num"><?php echo esc_html(number_format_i18n((int) $total)); ?></td>
							</tr>
						<?php endforeach; ?>
					</table>
				<?php endif; ?>
			</div>
			<div class="box">
				<h2>Звідки приходять</h2>
				<?php if (! $sources) : ?>
					<p class="muted">Ще немає даних.</p>
				<?php else : ?>
					<table>
						<?php foreach ($sources as [$item, , $total]) : ?>
							<tr>
								<td><?php echo esc_html(raky_stats_source_name($item)); ?><div class="bar" style="width: <?php echo esc_attr(round(100 * $total / $sources_total)); ?>%"></div></td>
								<td class="num"><?php echo esc_html(round(100 * $total / $sources_total)); ?>%</td>
							</tr>
						<?php endforeach; ?>
					</table>
				<?php endif; ?>
			</div>
		</div>
		<p class="muted">Рахуються лише справжні браузери: пошукові роботи й ваші перегляди, коли ви увійшли в адмінку, не враховуються. Особисті дані відвідувачів (IP, cookie) не зберігаються.</p>

		<h2>Покупки</h2>
		<div class="cards">
			<div class="card"><span>Замовлень за період</span><b><?php echo esc_html(number_format_i18n($orders['all'])); ?></b></div>
			<div class="card"><span>Виконано</span><b><?php echo esc_html(number_format_i18n($orders['completed'])); ?></b></div>
			<div class="card"><span>Сума виконаних</span><b><?php echo wp_kses_post(wc_price($orders['revenue'], ['decimals' => 0])); ?></b></div>
			<div class="card"><span>Середній чек</span><b><?php echo wp_kses_post(wc_price($orders['completed'] ? $orders['revenue'] / $orders['completed'] : 0, ['decimals' => 0])); ?></b></div>
			<div class="card"><span>Скасовано</span><b><?php echo esc_html(number_format_i18n($orders['cancelled'])); ?></b></div>
		</div>
		<div class="cards">
			<div class="card"><span>З телефона</span><b><?php echo esc_html(number_format_i18n($orders['source']['mobile'])); ?></b></div>
			<div class="card"><span>З комп’ютера</span><b><?php echo esc_html(number_format_i18n($orders['source']['desktop'])); ?></b></div>
			<div class="card"><span>Із застосунку</span><b><?php echo esc_html(number_format_i18n($orders['source']['app'])); ?></b></div>
			<div class="card"><span>Виконано за весь час</span><b><?php echo esc_html(number_format_i18n($orders_all['completed'])); ?></b></div>
		</div>
		<p class="muted">«Замовлень» — усі, крім скасованих, зокрема ті, що ще в роботі. Сума й середній чек — лише за виконаними.
			<?php if ($orders['source']['']) : ?>
				<?php echo esc_html(sprintf('%s — зроблені до початку обліку, тож пристрій невідомий: вони є в «Замовлень», але не в розбивці.', number_format_i18n($orders['source']['']))); ?>
			<?php endif; ?>
		</p>

		<h2>Зареєстровані клієнти</h2>
		<div class="cards">
			<div class="card"><span>Усього</span><b><?php echo esc_html(number_format_i18n(array_sum($signups_all))); ?></b></div>
			<div class="card"><span>За період</span><b><?php echo esc_html(number_format_i18n(array_sum($signups))); ?></b></div>
			<div class="card"><span>З телефона</span><b><?php echo esc_html(number_format_i18n($signups['mobile'] ?? 0)); ?></b></div>
			<div class="card"><span>З комп’ютера</span><b><?php echo esc_html(number_format_i18n($signups['desktop'] ?? 0)); ?></b></div>
			<div class="card"><span>Із застосунку</span><b><?php echo esc_html(number_format_i18n($signups['app'] ?? 0)); ?></b></div>
		</div>
		<p class="muted">Реєстрація — перший вхід через Telegram із підтвердженим номером. Повторні входи й вхід з іншого пристрою не рахуються.
			<?php if (! empty($signups_all['earlier'])) : ?>
				<?php echo esc_html(sprintf('%s — увійшли до початку обліку, тож пристрій невідомий: вони є в «Усього», але не в розбивці.', number_format_i18n($signups_all['earlier']))); ?>
			<?php endif; ?>
		</p>

		<h2>Застосунок</h2>
		<div class="cards">
			<div class="card"><span>Встановлень за період</span><b><?php echo esc_html(number_format_i18n(($app['install-android'] ?? 0) + ($app['install-ios'] ?? 0))); ?></b></div>
			<div class="card"><span>Android / iPhone</span><b><?php echo esc_html(number_format_i18n($app['install-android'] ?? 0) . ' / ' . number_format_i18n($app['install-ios'] ?? 0)); ?></b></div>
			<div class="card"><span>Запусків за період</span><b><?php echo esc_html(number_format_i18n(($app['open-android'] ?? 0) + ($app['open-ios'] ?? 0))); ?></b></div>
			<div class="card"><span>Встановлень за весь час</span><b><?php echo esc_html(number_format_i18n(($app_all['install-android'] ?? 0) + ($app_all['install-ios'] ?? 0))); ?></b></div>
		</div>
		<div class="box">
			<p style="margin: 0">Цей розділ поки що підготовка. Числа почнуть рахуватися, коли застосунок вийде в Google Play і App Store: він сам повідомлятиме про перше встановлення та запуски. Точну кількість завантажень із магазинів також видно в Google Play Console і App Store Connect.</p>
		</div>
	</div>
	<?php
}
