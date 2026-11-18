<?php
/** Категорія: підрозділи з підзаголовками, одна картка на найглибшу категорію (калібри обираються в товарі). */
defined('ABSPATH') || exit;

$category = raky_site_current()['category'];
$blocks   = raky_site_blocks($category['id']);
$eager    = 4;

raky_site_part('header');
?>
<div class="wrap">
	<?php raky_site_part('crumbs'); ?>
	<h1 class="page-title"><?php echo esc_html($category['name']); ?></h1>
	<div class="catbar" data-catbar>
		<label class="search">
			<?php echo raky_site_icon('search', 18); ?>
			<input type="search" placeholder="Пошук у категорії" aria-label="Пошук у категорії" data-search autocomplete="off">
			<button type="button" class="search__clear" data-search-clear aria-label="Очистити пошук" hidden><?php echo raky_site_icon('x', 18); ?></button>
		</label>
		<nav class="chips" aria-label="Категорії">
			<a class="chip" href="<?php echo esc_url(home_url('/')); ?>">Усі</a>
			<?php foreach (raky_site_catalog()['categories'] as $item) : ?>
				<a class="chip<?php echo $item['id'] === $category['id'] ? ' is-on' : ''; ?>" href="<?php echo esc_url(raky_site_category_url($item['id'])); ?>"<?php echo $item['id'] === $category['id'] ? ' aria-current="page"' : ''; ?>><?php echo esc_html($item['name']); ?></a>
			<?php endforeach; ?>
		</nav>
	</div>
	<section class="section" data-section="<?php echo (int) $category['id']; ?>">
		<?php if ($blocks) : ?>
			<?php raky_site_blocks_html($blocks, $eager); ?>
		<?php else : ?>
			<p class="nothing">Зараз у цій категорії немає товарів у наявності.</p>
		<?php endif; ?>
	</section>
	<p class="nothing" data-nothing hidden>Нічого не знайдено</p>
</div>
<?php
raky_site_part('footer');
