<?php
/** Акції: усі чинні банери з повним описом і промокодом. */
defined('ABSPATH') || exit;

$banners = raky_site_banners();
raky_site_part('header');
?>
<div class="wrap wrap--narrow">
	<?php raky_site_part('crumbs'); ?>
	<h1 class="page-title">Акції</h1>
	<?php if (! $banners) : ?>
		<div class="empty"><?php echo raky_site_icon('discount', 40); ?><p>Зараз акцій немає</p></div>
	<?php endif; ?>
	<?php foreach ($banners as $banner) : ?>
		<?php $about = trim((string) ($banner['details'] ?? '')) ?: $banner['subtitle']; ?>
		<article class="promo" id="banner-<?php echo (int) $banner['id']; ?>">
			<?php raky_site_part('banner', ['banner' => $banner, 'subtitle' => false]); ?>
			<?php if ($about !== '' || $banner['code']) : ?>
				<div class="promo__foot">
					<p>
						<?php echo nl2br(esc_html($about)); ?>
						<?php if ($banner['code']) : ?>
							<?php echo $about !== '' ? '<br>' : ''; ?>Код: <strong><?php echo esc_html($banner['code']); ?></strong>
						<?php endif; ?>
					</p>
					<?php if ($banner['code']) : ?>
						<button type="button" class="btn btn--outline btn--sm" data-copy="<?php echo esc_attr($banner['code']); ?>">Копіювати</button>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</article>
	<?php endforeach; ?>
	<p class="muted">Промокод вводиться в кошику перед оформленням замовлення.</p>
</div>
<?php
raky_site_part('footer');
