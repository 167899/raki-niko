<?php
/** Головна: банери, пошук, категорії й товари всіх категорій. */
defined('ABSPATH') || exit;

$catalog = raky_site_catalog();
$config  = raky_site_config();
$banners = ($config['banners_on'] ?? true) ? raky_site_banners() : [];
$eager   = 4;

raky_site_part('header');
?>
<div class="wrap">
	<?php if ($banners) : ?>
		<section class="slider" data-slider aria-roledescription="карусель" aria-label="Акції">
			<div class="slider__track" data-slider-track>
				<?php foreach ($banners as $index => $banner) : ?>
					<div class="slider__slide" aria-roledescription="слайд" aria-label="<?php echo esc_attr(($index + 1) . ' з ' . count($banners)); ?>">
						<?php raky_site_part('banner', ['banner' => $banner, 'href' => raky_site_url('aktsii') . '#banner-' . $banner['id'], 'first' => $index === 0]); ?>
					</div>
				<?php endforeach; ?>
			</div>
			<?php if (count($banners) > 1) : ?>
				<div class="slider__dots">
					<?php foreach ($banners as $index => $banner) : ?>
						<button type="button" data-slider-dot="<?php echo (int) $index; ?>" aria-label="Банер <?php echo (int) $index + 1; ?>"<?php echo $index === 0 ? ' aria-current="true"' : ''; ?>></button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<h1 class="home-title"><?php echo esc_html(RAKY_SITE_TAGLINE); ?></h1>

	<div class="catbar" data-catbar>
		<label class="search">
			<?php echo raky_site_icon('search', 18); ?>
			<input type="search" placeholder="Швидкий пошук" aria-label="Швидкий пошук" data-search autocomplete="off">
			<button type="button" class="search__clear" data-search-clear aria-label="Очистити пошук" hidden><?php echo raky_site_icon('x', 18); ?></button>
		</label>
		<nav class="chips" aria-label="Категорії">
			<a class="chip is-on" href="<?php echo esc_url(home_url('/')); ?>" data-chip="all">Усі</a>
			<?php foreach ($catalog['categories'] as $category) : ?>
				<a class="chip" href="<?php echo esc_url(raky_site_category_url($category['id'])); ?>" data-chip="<?php echo (int) $category['id']; ?>"><?php echo esc_html($category['name']); ?></a>
			<?php endforeach; ?>
		</nav>
	</div>

	<?php foreach ($catalog['categories'] as $category) : ?>
		<?php $blocks = raky_site_blocks($category['id']); ?>
		<?php if (! $blocks) {
			continue;
		} ?>
		<section class="section" id="cat-<?php echo (int) $category['id']; ?>" data-section="<?php echo (int) $category['id']; ?>">
			<h2 class="section__title"><a href="<?php echo esc_url(raky_site_category_url($category['id'])); ?>"><?php echo esc_html($category['name']); ?></a></h2>
			<?php raky_site_blocks_html($blocks, $eager); ?>
		</section>
	<?php endforeach; ?>
	<p class="nothing" data-nothing hidden>Нічого не знайдено</p>
	<?php if (! $catalog['products']) : ?>
		<p class="nothing">Меню оновлюється. Зателефонуйте нам: <a href="tel:<?php echo esc_attr(RAKY_SITE_PHONES[0]); ?>"><?php echo esc_html(raky_site_pretty_phone(RAKY_SITE_PHONES[0])); ?></a></p>
	<?php endif; ?>
</div>
<?php
raky_site_part('footer');
