<?php
/** Про компанію (як AboutScreen застосунку): хто ми, телефони, адреси, години роботи, соцмережі. */
defined('ABSPATH') || exit;

$config = raky_site_config();
raky_site_part('header');
?>
<div class="wrap wrap--narrow">
	<?php raky_site_part('crumbs'); ?>
	<h1 class="page-title">Про компанію</h1>
	<p class="about__logo"><img src="<?php echo esc_url(get_theme_file_uri('assets/logo.png')); ?>" width="78" height="93" alt="Raki Niko"></p>
	<h2 class="heading">Хто ми</h2>
	<p>Raki Niko продає раків у Миколаєві.</p>
	<h2 class="heading">Телефони</h2>
	<div class="box">
		<?php foreach (RAKY_SITE_PHONES as $phone) : ?>
			<a class="contact" href="tel:<?php echo esc_attr($phone); ?>"><?php echo raky_site_icon('phone', 18, 'red'); ?><span><?php echo esc_html(raky_site_pretty_phone($phone)); ?></span></a>
		<?php endforeach; ?>
	</div>
	<h2 class="heading">Адреси</h2>
	<div class="box">
		<?php foreach ($config['outlets'] as $outlet) : ?>
			<a class="contact" href="<?php echo esc_url('https://www.google.com/maps/search/?api=1&query=' . rawurlencode($outlet['lat'] . ',' . $outlet['lng'])); ?>" target="_blank" rel="noopener"><?php echo raky_site_icon('map-pin', 18, 'red'); ?><span><?php echo esc_html($outlet['name']); ?></span></a>
		<?php endforeach; ?>
	</div>
	<h2 class="heading">Робочі години</h2>
	<div class="info-row"><span>Щодня</span><span class="muted"><?php echo esc_html(RAKY_SITE_HOURS); ?></span></div>
	<?php if (raky_site_socials()) : ?>
		<div class="about__socials">
			<?php foreach (raky_site_socials() as $social) : ?>
				<a class="icon-btn" href="<?php echo esc_url($social['url']); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr($social['label']); ?>"><?php echo raky_site_icon($social['icon'], 24, 'red'); ?></a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
<?php
raky_site_part('footer');
