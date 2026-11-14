<?php
/**
 * Товар. Калібри — посилання на сусідні товари підкатегорії, смаки, «Додатково» й вагу обирає скрипт (src/product.ts).
 * Без скрипта сторінка показує ціну й телефон для замовлення.
 */
defined('ABSPATH') || exit;

$product  = raky_site_current()['product'];
$calibers = raky_site_calibers($product);
$photo    = $product['image_large'] ?: $product['image'];
$weighed  = raky_unit_weighed($product['unit']);
$amount   = $weighed ? 'Вага' : (in_array($product['unit'], ['l', 'ml'], true) ? 'Об’єм' : 'Кількість');
$related  = array_values(array_filter(
	raky_site_cards(raky_site_products_in($product['category_id'])),
	static fn (array $card) => $card['product']['group'] === '' ? $card['product']['id'] !== $product['id'] : $card['product']['group'] !== $product['group']
));
$data = [
	'id'      => $product['id'],
	'name'    => $product['name'],
	'price'   => $product['price'],
	'unit'    => $product['unit'],
	'step'    => $product['step'],
	'min'     => $product['min'],
	'per_kg'  => $product['per_kg'],
	'flavors' => $product['flavors'],
	'addons'  => $product['addons'],
	'category' => raky_site_category($product['category_id'])['name'] ?? '',
];

raky_site_part('header');
?>
<div class="wrap">
	<?php raky_site_part('crumbs'); ?>
	<div class="product" data-product="<?php echo esc_attr(wp_json_encode($data, JSON_UNESCAPED_UNICODE)); ?>">
		<div class="product__media">
			<?php if ($photo) : ?>
				<a class="product__photo" href="<?php echo esc_url($photo); ?>" data-zoom aria-label="Відкрити фото на весь екран">
					<img src="<?php echo esc_url($photo); ?>" alt="<?php echo esc_attr($product['name']); ?>" width="600" height="600" fetchpriority="high">
					<span class="product__zoom"><?php echo raky_site_icon('zoom-in', 20); ?></span>
				</a>
			<?php else : ?>
				<div class="product__photo product__photo--empty"><?php echo raky_site_icon('flame', 48); ?></div>
			<?php endif; ?>
		</div>
		<div class="product__info">
			<div class="product__head">
				<h1 class="product__name"><?php echo esc_html($product['name']); ?></h1>
				<?php raky_site_part('heart', ['id' => $product['id'], 'class' => 'fav--inline']); ?>
			</div>
			<?php if ($product['description'] !== '') : ?>
				<p class="muted"><?php echo esc_html($product['description']); ?></p>
			<?php endif; ?>
			<p class="product__price"><?php echo esc_html(raky_site_unit_price($product['price'], $product['unit'])); ?></p>

			<?php if (! $product['in_stock']) : ?>
				<div class="warn">Зараз немає в наявності. Зателефонуйте нам — підкажемо, коли буде.</div>
				<a class="btn" href="tel:<?php echo esc_attr(RAKY_SITE_PHONES[0]); ?>"><?php echo raky_site_icon('phone', 18); ?> Зателефонувати</a>
			<?php else : ?>
				<?php if (count($calibers) > 1) : ?>
					<p class="label">Калібр</p>
					<div class="chips chips--wrap">
						<?php foreach ($calibers as $item) : ?>
							<a class="chip<?php echo $item['id'] === $product['id'] ? ' is-on' : ''; ?>" href="<?php echo esc_url(raky_site_product_url($item['id'])); ?>"<?php echo $item['id'] === $product['id'] ? ' aria-current="page"' : ''; ?>>
								<?php echo esc_html(implode(', ', array_filter([raky_site_capitalize($item['caliber']), $item['caliber_hint']])) ?: $item['name']); ?>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php if ($product['flavors']) : ?>
					<p class="label">Смак</p>
					<div class="chips chips--wrap" role="radiogroup" aria-label="Смак">
						<?php foreach ($product['flavors'] as $index => $flavor) : ?>
							<button type="button" class="chip<?php echo $index === 0 ? ' is-on' : ''; ?>" role="radio" aria-checked="<?php echo $index === 0 ? 'true' : 'false'; ?>" data-flavor="<?php echo esc_attr($flavor['name']); ?>">
								<?php echo esc_html($flavor['name']); ?><?php echo $flavor['extra'] > 0 ? ' +' . esc_html(raky_site_unit_price($flavor['extra'], $product['unit'])) : ''; ?>
							</button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php if ($product['addons']) : ?>
					<p class="label">Додатково</p>
					<div class="chips chips--wrap">
						<?php foreach ($product['addons'] as $addon) : ?>
							<div class="addon" data-addon="<?php echo esc_attr($addon['name']); ?>">
								<button type="button" class="chip addon__add" data-addon-add>
									<?php echo esc_html($addon['name']); ?><?php echo $addon['price'] > 0 ? ' +' . esc_html(raky_site_money($addon['price'])) : ''; ?>
									<span class="addon__count" data-addon-count hidden></span>
								</button>
								<button type="button" class="addon__minus" data-addon-minus aria-label="Прибрати: <?php echo esc_attr($addon['name']); ?>" hidden><?php echo raky_site_icon('minus', 14); ?></button>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<p class="label"><?php echo esc_html($amount); ?></p>
				<div class="stepper">
					<button type="button" class="stepper__btn" data-step="-1" aria-label="Менше"><?php echo raky_site_icon('minus', 16); ?></button>
					<output class="stepper__value" data-amount aria-live="polite"><?php echo esc_html(raky_site_quantity($product['min'], $product['unit'])); ?></output>
					<button type="button" class="stepper__btn" data-step="1" aria-label="Більше"><?php echo raky_site_icon('plus', 16); ?></button>
				</div>
				<p class="muted" data-pieces hidden></p>
				<?php if ($weighed) : ?>
					<div class="warn">Менеджер уточнить вагу і суму перед оплатою.</div>
				<?php endif; ?>

				<div class="buy">
					<div class="sum">
						<span class="muted" data-unit-price><?php echo esc_html(raky_site_unit_price($product['price'], $product['unit'])); ?></span>
						<strong class="sum__total" data-total><?php echo esc_html(raky_site_money($product['price'] * $product['min'])); ?></strong>
					</div>
					<button type="button" class="btn" data-add>Додати в кошик</button>
					<div class="buy__after" data-after hidden>
						<a class="btn btn--outline" href="<?php echo esc_url(home_url('/')); ?>">Продовжити покупки</a>
						<a class="btn" href="<?php echo esc_url(raky_site_url('koshyk')); ?>">Оформити</a>
					</div>
					<noscript><p class="muted">Щоб замовити, зателефонуйте: <a href="tel:<?php echo esc_attr(RAKY_SITE_PHONES[0]); ?>"><?php echo esc_html(raky_site_pretty_phone(RAKY_SITE_PHONES[0])); ?></a></p></noscript>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<?php if ($related) : ?>
		<section class="section">
			<h2 class="section__title">Також у категорії</h2>
			<div class="grid">
				<?php foreach (array_slice($related, 0, 4) as $card) : ?>
					<?php raky_site_part('card', ['card' => $card]); ?>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>
</div>
<?php
raky_site_part('footer');
