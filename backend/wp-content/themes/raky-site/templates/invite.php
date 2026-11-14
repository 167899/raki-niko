<?php
/** Запросити друзів: особистий код за номером телефону (той самий, що в застосунку). Код показує src/invite.ts. */
defined('ABSPATH') || exit;

$referral = raky_site_config()['referral'];
$percent  = raky_site_number((float) $referral['percent']);
raky_site_part('header');
?>
<div class="wrap wrap--narrow">
	<?php raky_site_part('crumbs'); ?>
	<h1 class="page-title">Запросити друзів</h1>
	<div class="invite-card">
		<strong>−<?php echo esc_html($percent); ?>% вам і другу</strong>
		<span>Разова знижка кожному учаснику.</span>
	</div>
	<h2 class="heading">Як це працює</h2>
	<ol class="steps">
		<li>Надішліть другу свій код.</li>
		<li>Друг вводить код у кошику під час першого замовлення й отримує −<?php echo esc_html($percent); ?>%.</li>
		<li>Коли замовлення друга виконано, ви отримуєте −<?php echo esc_html($percent); ?>% на одне наступне замовлення. Знижка сама з'явиться в кошику.</li>
	</ol>
	<?php if (! $referral['enabled']) : ?>
		<div class="warn">Запрошення друзів зараз не діє.</div>
	<?php else : ?>
		<div data-invite></div>
	<?php endif; ?>
</div>
<?php
raky_site_part('footer');
