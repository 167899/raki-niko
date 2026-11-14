<?php
/** Низ сторінки: розділи, контакти, юридичні сторінки. На телефоні — нижня панель, як у застосунку. */
defined('ABSPATH') || exit;

$current = raky_site_current();
$active  = $current['type'] === 'page' ? $current['slug'] : $current['type'];
$config  = raky_site_config();
$bar     = [
	['home', 'Головна', home_url('/'), 'home'],
	['aktsii', 'Акції', raky_site_url('aktsii'), 'discount'],
	['koshyk', 'Кошик', raky_site_url('koshyk'), 'shopping-cart'],
	['dostavka', 'Доставка', raky_site_url('dostavka'), 'truck-delivery'],
];
?>
</main>
<footer class="foot">
	<div class="wrap foot__grid">
		<div>
			<a class="brand" href="<?php echo esc_url(home_url('/')); ?>">
				<img src="<?php echo esc_url(get_theme_file_uri('assets/logo.png')); ?>" width="30" height="36" alt="" loading="lazy">
				<span>Raki Niko</span>
			</a>
			<p class="muted"><?php echo esc_html(RAKY_SITE_TAGLINE); ?>.</p>
			<div class="socials">
				<?php foreach (raky_site_socials() as $social) : ?>
					<a href="<?php echo esc_url($social['url']); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr($social['label']); ?>"><?php echo raky_site_icon($social['icon'], 24); ?></a>
				<?php endforeach; ?>
			</div>
		</div>
		<nav aria-label="Меню магазину">
			<h2 class="foot__title">Меню</h2>
			<?php foreach (raky_site_catalog()['categories'] as $category) : ?>
				<a href="<?php echo esc_url(raky_site_category_url($category['id'])); ?>"><?php echo esc_html($category['name']); ?></a>
			<?php endforeach; ?>
			<a href="<?php echo esc_url(raky_site_url('aktsii')); ?>">Акції</a>
		</nav>
		<nav aria-label="Покупцям">
			<h2 class="foot__title">Покупцям</h2>
			<a href="<?php echo esc_url(raky_site_url('dostavka')); ?>">Оплата і доставка</a>
			<a href="<?php echo esc_url(raky_site_url('zamovlennia')); ?>">Мої замовлення</a>
			<a href="<?php echo esc_url(raky_site_url('zaproshennia')); ?>">Запросити друзів</a>
			<a href="<?php echo esc_url(raky_site_url('novyny')); ?>">Новини</a>
			<a href="<?php echo esc_url(raky_site_url('pryvatnist')); ?>">Політика конфіденційності</a>
			<a href="<?php echo esc_url(raky_site_url('oferta')); ?>">Публічна оферта</a>
		</nav>
		<div>
			<h2 class="foot__title">Контакти</h2>
			<?php foreach (RAKY_SITE_PHONES as $phone) : ?>
				<a href="tel:<?php echo esc_attr($phone); ?>"><?php echo esc_html(raky_site_pretty_phone($phone)); ?></a>
			<?php endforeach; ?>
			<?php foreach ($config['outlets'] as $outlet) : ?>
				<p class="muted"><?php echo esc_html($outlet['name']); ?></p>
			<?php endforeach; ?>
			<p class="muted">Щодня <?php echo esc_html(RAKY_SITE_HOURS); ?></p>
		</div>
	</div>
	<div class="wrap foot__copy muted">© <?php echo esc_html(wp_date('Y')); ?> Raki Niko, <?php echo esc_html(RAKY_SITE_CITY); ?></div>
</footer>
<nav class="bar" aria-label="Швидкі розділи">
	<?php foreach ($bar as [$key, $label, $url, $icon]) : ?>
		<a class="bar__item<?php echo $key === 'koshyk' ? ' bar__item--cart' : ''; ?>" href="<?php echo esc_url($url); ?>"<?php echo $key === $active ? ' aria-current="page"' : ''; ?>>
			<span class="bar__icon"><?php echo raky_site_icon($icon, $key === 'koshyk' ? 23 : 20); ?><?php if ($key === 'koshyk') : ?><span class="badge" data-cart-count hidden></span><?php endif; ?></span>
			<span class="bar__label"><?php echo esc_html($label); ?></span>
		</a>
	<?php endforeach; ?>
	<button class="bar__item" type="button" data-ask-open aria-haspopup="dialog" aria-controls="ask">
		<span class="bar__icon"><?php echo raky_site_icon('message-circle', 20); ?></span>
		<span class="bar__label">Запитати</span>
	</button>
</nav>
<div class="sheet-layer" id="ask" data-ask hidden>
	<div class="sheet" role="dialog" aria-modal="true" aria-labelledby="ask-title">
		<div class="sheet__head">
			<h2 class="sheet__title" id="ask-title">Запитати</h2>
			<button class="icon-btn" type="button" data-ask-close aria-label="Закрити"><?php echo raky_site_icon('x'); ?></button>
		</div>
		<p class="sheet__hint">Оберіть номер</p>
		<?php foreach (RAKY_SITE_PHONES as $phone) : ?>
			<a class="row-btn" href="tel:<?php echo esc_attr($phone); ?>"><?php echo raky_site_icon('phone', 18, 'red'); ?><span><?php echo esc_html(raky_site_pretty_phone($phone)); ?></span></a>
		<?php endforeach; ?>
		<?php if (RAKY_SITE_VIBER !== '') : ?>
			<a class="row-btn" href="<?php echo esc_url(RAKY_SITE_VIBER); ?>"><?php echo raky_site_icon('message-circle', 18, 'red'); ?><span>Написати у Viber</span></a>
		<?php endif; ?>
		<?php if (RAKY_SITE_TELEGRAM !== '') : ?>
			<a class="row-btn" href="<?php echo esc_url(RAKY_SITE_TELEGRAM); ?>" target="_blank" rel="noopener"><?php echo raky_site_icon('brand-telegram', 18, 'red'); ?><span>Написати в Telegram</span></a>
		<?php endif; ?>
	</div>
</div>
<div class="toast" data-toast role="status" aria-live="polite" hidden></div>
<?php wp_footer(); ?>
</body>
</html>
