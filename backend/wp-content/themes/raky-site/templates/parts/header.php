<?php
/** Верх сторінки: логотип, меню, телефон, улюблене, повідомлення, кошик. На телефоні — як у застосунку: меню під значком профілю й нижня панель. */
defined('ABSPATH') || exit;

$current = raky_site_current();
$active  = $current['type'] === 'page' ? $current['slug'] : $current['type'];
$nav     = [
	['home', 'Меню', home_url('/')],
	['aktsii', 'Акції', raky_site_url('aktsii')],
	['dostavka', 'Оплата і доставка', raky_site_url('dostavka')],
	['novyny', 'Новини', raky_site_url('novyny')],
	['kontakty', 'Контакти', raky_site_url('kontakty')],
];
$menu = [
	['home', 'Головна', home_url('/')],
	['receipt', 'Мої замовлення', raky_site_url('zamovlennia')],
	['info-circle', 'Про компанію', raky_site_url('pro-kompaniiu')],
	['shopping-cart', 'Кошик', raky_site_url('koshyk')],
	['discount', 'Акції', raky_site_url('aktsii')],
	['bell', 'Новини', raky_site_url('novyny')],
	['credit-card', 'Оплата і доставка', raky_site_url('dostavka')],
	['map-pin', 'Контакти', raky_site_url('kontakty')],
	['gift', 'Запросити друзів', raky_site_url('zaproshennia')],
	['shield-lock', 'Політика конфіденційності', raky_site_url('pryvatnist')],
	['file-text', 'Публічна оферта', raky_site_url('oferta')],
];
?><!doctype html>
<html lang="uk">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<?php wp_head(); ?>
</head>
<body class="is-<?php echo esc_attr($active); ?><?php echo is_admin_bar_showing() ? ' has-admin-bar' : ''; ?>">
<?php wp_body_open(); ?>
<a class="skip" href="#main">Перейти до змісту</a>
<header class="top">
	<div class="wrap top__row">
		<a class="brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="Raki Niko — на головну">
			<img src="<?php echo esc_url(get_theme_file_uri('assets/logo.png')); ?>" width="30" height="36" alt="">
			<span>Raki Niko</span>
		</a>
		<nav class="top__nav" aria-label="Основне меню">
			<?php foreach ($nav as [$key, $label, $url]) : ?>
				<a href="<?php echo esc_url($url); ?>"<?php echo $key === $active ? ' aria-current="page"' : ''; ?>><?php echo esc_html($label); ?></a>
			<?php endforeach; ?>
		</nav>
		<div class="top__phones">
			<button class="icon-btn top__phone" type="button" data-phones-open aria-label="Телефони" title="Телефони" aria-expanded="false" aria-controls="phones">
				<?php echo raky_site_icon('phone'); ?>
			</button>
			<div class="phones-pop" id="phones" data-phones hidden>
				<?php foreach (RAKY_SITE_PHONES as $phone) : ?>
					<a href="tel:<?php echo esc_attr($phone); ?>"><?php echo raky_site_icon('phone', 18, 'red'); ?><span><?php echo esc_html(raky_site_pretty_phone($phone)); ?></span></a>
				<?php endforeach; ?>
				<p class="phones-pop__hours">Щодня <?php echo esc_html(RAKY_SITE_HOURS); ?></p>
			</div>
		</div>
		<a class="icon-btn top__orders" href="<?php echo esc_url(raky_site_url('zamovlennia')); ?>" aria-label="Мої замовлення" title="Мої замовлення">
			<?php echo raky_site_icon('receipt'); ?>
		</a>
		<a class="icon-btn" href="<?php echo esc_url(raky_site_url('ulyublene')); ?>" aria-label="Улюблене" title="Улюблене">
			<span class="icon-btn__in"><?php echo raky_site_icon('heart'); ?><span class="badge" data-fav-count hidden></span></span>
		</a>
		<a class="icon-btn" href="<?php echo esc_url(raky_site_url('novyny')); ?>" aria-label="Новини" title="Новини">
			<span class="icon-btn__in"><?php echo raky_site_icon('bell'); ?><span class="badge" data-news-count hidden></span></span>
		</a>
		<a class="top__cart" href="<?php echo esc_url(raky_site_url('koshyk')); ?>" aria-label="Кошик">
			<?php echo raky_site_icon('shopping-cart'); ?>
			<span class="top__cart-label">Кошик</span>
			<span class="badge top__cart-badge" data-cart-count hidden></span>
		</a>
		<button class="icon-btn top__menu" type="button" data-menu-open aria-label="Відкрити меню" aria-expanded="false" aria-controls="menu">
			<?php echo raky_site_icon('user-circle'); ?><?php echo raky_site_icon('chevron-down', 14); ?>
		</button>
	</div>
</header>
<div class="drawer" id="menu" data-menu hidden>
	<div class="drawer__panel" role="dialog" aria-modal="true" aria-label="Меню" tabindex="-1">
		<button class="icon-btn drawer__close" type="button" data-menu-close aria-label="Закрити меню"><?php echo raky_site_icon('x'); ?></button>
		<div class="drawer__who">
			<?php echo raky_site_icon('user-circle', 30, 'red'); ?>
			<a class="drawer__who-text" href="<?php echo esc_url(raky_site_url('vkhid')); ?>" data-who>
				<span class="drawer__who-name" data-who-name>Гість</span>
				<span class="drawer__who-hint" data-who-hint>Увійти або зареєструватися</span>
			</a>
		</div>
		<nav class="drawer__list">
			<?php foreach ($menu as [$icon, $label, $url]) : ?>
				<a href="<?php echo esc_url($url); ?>"><?php echo raky_site_icon($icon, 20); ?><span><?php echo esc_html($label); ?></span></a>
			<?php endforeach; ?>
		</nav>
		<div class="drawer__contacts">
			<?php foreach (RAKY_SITE_PHONES as $phone) : ?>
				<a class="row-btn" href="tel:<?php echo esc_attr($phone); ?>"><?php echo raky_site_icon('phone', 18); ?><span><?php echo esc_html(raky_site_pretty_phone($phone)); ?></span></a>
			<?php endforeach; ?>
			<?php if (RAKY_SITE_TELEGRAM !== '') : ?><a class="row-btn" href="<?php echo esc_url(RAKY_SITE_TELEGRAM); ?>" target="_blank" rel="noopener"><?php echo raky_site_icon('brand-telegram', 18); ?><span>Написати в Telegram</span></a><?php endif; ?>
		</div>
	</div>
</div>
<main id="main">
