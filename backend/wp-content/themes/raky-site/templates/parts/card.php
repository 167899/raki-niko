<?php
/**
 * Картка товару в списку. Для підкатегорії — найдешевший калібр із «від»,
 * для папки («Каліфорнія») — найдешевший товар у ній, а веде вона на сторінку папки.
 *
 * @var array $args ['card' => raky_site_cards()[n], 'eager' => bool]
 */
defined('ABSPATH') || exit;

$card    = $args['card'];
$product = $card['product'];
$url     = $card['url'] ?? raky_site_product_url($product['id']);
$image   = $card['image'] ?? $product['image'];
$slug    = raky_site_category($product['category_id'])['slug'] ?? '';
?>
<article class="card" data-search="<?php echo esc_attr(raky_site_card_search($card)); ?>"<?php echo ! empty($args['fav_card']) ? ' data-fav-card="' . (int) $product['id'] . '" hidden' : ''; ?>>
	<?php if (empty($card['folder'])) {
		raky_site_part('heart', ['id' => $product['id']]);
	} ?>
	<a class="card__pic" href="<?php echo esc_url($url); ?>" tabindex="-1" aria-hidden="true">
		<?php if ($image) : ?>
			<img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($card['title']); ?>" width="300" height="300" <?php echo empty($args['eager']) ? 'loading="lazy"' : 'fetchpriority="high"'; ?> decoding="async">
		<?php else : ?>
			<?php echo raky_site_icon(str_contains(urldecode($slug), 'жив') || str_contains($slug, 'zhyv') ? 'droplet' : 'flame', 44); ?>
		<?php endif; ?>
	</a>
	<div class="card__body">
		<h3 class="card__name"><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($card['title']); ?></a></h3>
		<p class="card__price"><?php echo $card['from'] ? 'від ' : ''; ?><?php echo esc_html(raky_site_unit_price($product['price'], $product['unit'])); ?></p>
		<a class="btn btn--sm" href="<?php echo esc_url($url); ?>" aria-label="Обрати: <?php echo esc_attr($card['title']); ?>">Обрати</a>
	</div>
</article>
