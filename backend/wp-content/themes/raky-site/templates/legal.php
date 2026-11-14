<?php
/** Політика конфіденційності й публічна оферта: ті самі тексти, що в застосунку. */
defined('ABSPATH') || exit;

$slug     = raky_site_current()['slug'];
$sections = $slug === 'oferta' ? raky_site_offer() : raky_site_privacy();
raky_site_part('header');
?>
<div class="wrap wrap--narrow">
	<?php raky_site_part('crumbs'); ?>
	<h1 class="page-title"><?php echo esc_html(raky_site_pages()[$slug][1]); ?></h1>
	<?php if (! RAKY_SITE_LEGAL_OK) : ?>
		<div class="warn"><?php echo esc_html($slug === 'oferta' ? 'Макет. Остаточний текст має підготувати юрист.' : 'Макет. Остаточний текст має перевірити юрист.'); ?></div>
	<?php endif; ?>
	<?php foreach ($sections as [$title, $text]) : ?>
		<h2 class="heading"><?php echo esc_html($title); ?></h2>
		<p><?php echo esc_html($text); ?></p>
	<?php endforeach; ?>
</div>
<?php
raky_site_part('footer');
