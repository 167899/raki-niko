<?php
/** Контакти: телефони, месенджери, точки продажу, години роботи. */
defined('ABSPATH') || exit;

$config = raky_site_config();
raky_site_part('header');
?>
<div class="wrap wrap--narrow">
	<?php raky_site_part('crumbs'); ?>
	<h1 class="page-title">Контакти</h1>
	<p>Raki Niko продає раків у Миколаєві. Щодня <?php echo esc_html(RAKY_SITE_HOURS); ?>.</p>
	<h2 class="heading">Телефони</h2>
	<div class="box">
		<?php foreach (RAKY_SITE_PHONES as $phone) : ?>
			<a class="contact" href="tel:<?php echo esc_attr($phone); ?>"><?php echo raky_site_icon('phone', 18, 'red'); ?><span><?php echo esc_html(raky_site_pretty_phone($phone)); ?></span></a>
		<?php endforeach; ?>
	</div>
	<h2 class="heading">Написати нам</h2>
	<div class="box">
		<?php if (RAKY_SITE_TELEGRAM !== '') : ?><a class="contact" href="<?php echo esc_url(RAKY_SITE_TELEGRAM); ?>" target="_blank" rel="noopener"><?php echo raky_site_icon('brand-telegram', 18, 'red'); ?><span>Telegram</span></a><?php endif; ?>
		<?php if (RAKY_SITE_VIBER !== '') : ?>
			<a class="contact" href="<?php echo esc_url(RAKY_SITE_VIBER); ?>"><?php echo raky_site_icon('message-circle', 18, 'red'); ?><span>Viber</span></a>
		<?php endif; ?>
		<?php foreach (raky_site_socials() as $social) : if ($social['label'] === 'Telegram') continue; ?>
			<a class="contact" href="<?php echo esc_url($social['url']); ?>" target="_blank" rel="noopener"><?php echo raky_site_icon($social['icon'], 18, 'red'); ?><span><?php echo esc_html($social['label']); ?></span></a>
		<?php endforeach; ?>
	</div>
	<h2 class="heading">Адреси</h2>
	<div class="box">
		<?php foreach ($config['outlets'] as $outlet) : ?>
			<a class="contact" href="<?php echo esc_url('https://www.google.com/maps/search/?api=1&query=' . rawurlencode($outlet['lat'] . ',' . $outlet['lng'])); ?>" target="_blank" rel="noopener">
				<?php echo raky_site_icon('map-pin', 18, 'red'); ?>
				<span><?php echo esc_html($outlet['name']); ?><?php echo $outlet['address'] !== '' && $outlet['address'] !== $outlet['name'] ? ', ' . esc_html($outlet['address']) : ''; ?> <span class="muted">· <?php echo esc_html(mb_strtolower($outlet['hours'])); ?></span></span>
			</a>
		<?php endforeach; ?>
	</div>
	<h2 class="heading">Робочі години</h2>
	<div class="info-row"><span>Щодня</span><span class="muted"><?php echo esc_html(RAKY_SITE_HOURS); ?></span></div>
</div>
<?php
raky_site_part('footer');
