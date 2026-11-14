<?php
/** Кошик: рядки й промокод малює src/cart.ts, кошик зберігається в браузері. */
defined('ABSPATH') || exit;
raky_site_part('header');
?>
<div class="wrap wrap--mid">
	<h1 class="page-title">Кошик</h1>
	<div class="checkout-grid" data-cart>
		<div class="loading" aria-label="Завантаження"></div>
	</div>
	<noscript><p>Щоб оформити замовлення, увімкніть JavaScript або зателефонуйте: <a href="tel:<?php echo esc_attr(RAKY_SITE_PHONES[0]); ?>"><?php echo esc_html(raky_site_pretty_phone(RAKY_SITE_PHONES[0])); ?></a></p></noscript>
</div>
<?php
raky_site_part('footer');
