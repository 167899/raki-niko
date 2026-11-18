<?php
/** Категорія чи папка в ній («Суші та роли» → «Каліфорнія»): картки папок і по одній картці на калібри. */
defined('ABSPATH') || exit;

$category = raky_site_current()['category'];
$folder   = raky_site_current()['folder'] ?? null;
$cards    = raky_site_cards_at($category['id'], $folder['id'] ?? 0);
$eager    = 4;

raky_site_part('header');
?>
<div class="wrap">
	<?php raky_site_part('crumbs'); ?>
	<h1 class="page-title"><?php echo esc_html($folder['name'] ?? $category['name']); ?></h1>
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
		<?php if ($cards) : ?>
			<div class="grid">
				<?php foreach ($cards as $card) : ?>
					<?php raky_site_part('card', ['card' => $card, 'eager' => $eager-- > 0]); ?>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<p class="nothing">Зараз у цій категорії немає товарів у наявності.</p>
		<?php endif; ?>
	</section>
	<p class="nothing" data-nothing hidden>Нічого не знайдено</p>
</div>
<?php
raky_site_part('footer');
