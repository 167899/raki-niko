<?php
defined('ABSPATH') || exit;
raky_site_part('header');
?>
<div class="wrap wrap--narrow">
	<div class="empty">
		<?php echo raky_site_icon('info-circle', 40); ?>
		<h1 class="page-title">Сторінку не знайдено</h1>
		<p>Можливо, товар уже знято з продажу.</p>
		<a class="btn" href="<?php echo esc_url(home_url('/')); ?>">До меню</a>
	</div>
</div>
<?php
raky_site_part('footer');
