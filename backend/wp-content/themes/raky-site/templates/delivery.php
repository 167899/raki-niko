<?php
/** Оплата і доставка: карта зон (та сама, що в застосунку), самовивіз, кроки оплати. */
defined('ABSPATH') || exit;

$config = raky_site_config();
$fees   = array_map(static fn (array $zone) => (float) $zone['fee'], $config['zones']);
raky_site_part('header');
?>
<div class="wrap wrap--narrow">
	<?php raky_site_part('crumbs'); ?>
	<h1 class="page-title">Оплата і доставка</h1>
	<h2 class="heading">Зони доставки</h2>
	<div class="map" data-map="<?php echo esc_attr(wp_json_encode(['zones' => $config['zones'], 'outlets' => $config['outlets']], JSON_UNESCAPED_UNICODE)); ?>"></div>
	<?php if ($fees) : ?>
		<p class="muted">Вартість від <?php echo esc_html(raky_site_money(min($fees))); ?> до <?php echo esc_html(raky_site_money(max($fees))); ?> залежно від району. Натисніть на район на карті, щоб побачити ціну. Точну вартість сайт покаже під час оформлення, щойно ви введете адресу.</p>
	<?php endif; ?>
	<p class="muted">Доставляє партнерська кур'єрська служба по Миколаєву.</p>

	<h2 class="heading">Самовивіз</h2>
	<?php foreach ($config['outlets'] as $outlet) : ?>
		<div class="info-row"><span><?php echo esc_html($outlet['name']); ?></span><span class="muted"><?php echo esc_html(mb_strtolower($outlet['hours'])); ?></span></div>
	<?php endforeach; ?>

	<h2 class="heading">Оплата</h2>
	<ol class="steps">
		<?php foreach (raky_site_pay_steps() as $step) : ?>
			<li><?php echo esc_html($step); ?></li>
		<?php endforeach; ?>
	</ol>
	<p class="muted"><?php echo esc_html(RAKY_SITE_CASH_NOTE); ?></p>
</div>
<?php
raky_site_part('footer');
