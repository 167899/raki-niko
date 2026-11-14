<?php
/** Мої замовлення: оформлені в цьому браузері, а після входу через Telegram — усі за номером (і з застосунку). Статуси оновлює src/orders.ts. */
defined('ABSPATH') || exit;
raky_site_part('header');
?>
<div class="wrap wrap--narrow">
	<h1 class="page-title">Мої замовлення</h1>
	<p class="muted" data-orders-hint>Тут видно замовлення, оформлені на сайті в цьому браузері.</p>
	<div data-orders><div class="loading" aria-label="Завантаження"></div></div>
</div>
<?php
raky_site_part('footer');
