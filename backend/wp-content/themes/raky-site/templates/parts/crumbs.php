<?php
/** Хлібні крихти. Розмітку для Google додає inc/seo.php. */
defined('ABSPATH') || exit;

$crumbs = raky_site_breadcrumbs();
if (count($crumbs) < 2) {
	return;
}
?>
<nav class="crumbs" aria-label="Ви тут">
	<?php foreach ($crumbs as $index => [$name, $url]) : ?>
		<?php if ($index === count($crumbs) - 1) : ?>
			<span aria-current="page"><?php echo esc_html($name); ?></span>
		<?php else : ?>
			<a href="<?php echo esc_url($url); ?>"><?php echo esc_html($name); ?></a><span class="crumbs__sep" aria-hidden="true">/</span>
		<?php endif; ?>
	<?php endforeach; ?>
</nav>
