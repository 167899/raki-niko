<?php
/** Улюблене: товари, позначені серцем. Список живе в браузері (src/favorites.ts), тут — усі картки, скрипт показує потрібні. */
defined('ABSPATH') || exit;

$products = array_filter(raky_site_catalog()['products'], static fn (array $product) => $product['in_stock']);
raky_site_part('header');
?>
<div class="wrap">
	<?php raky_site_part('crumbs'); ?>
	<h1 class="page-title">Улюблене</h1>
	<div class="grid" data-favorites>
		<?php foreach ($products as $product) : ?>
			<?php raky_site_part('card', ['card' => ['product' => $product, 'title' => $product['name'], 'from' => false, 'members' => [$product]], 'fav_card' => true]); ?>
		<?php endforeach; ?>
		<div class="empty" data-favorites-empty>
			<?php echo raky_site_icon('heart', 40); ?>
			<p>Тут будуть ваші улюблені товари</p>
			<a class="btn btn--sm" href="<?php echo esc_url(home_url('/')); ?>">До меню</a>
		</div>
	</div>
</div>
<?php
raky_site_part('footer');
