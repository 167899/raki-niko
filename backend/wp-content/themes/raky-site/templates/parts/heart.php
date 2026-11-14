<?php
/**
 * Кнопка «В улюблене» (як серце в застосунку). Стан бере скрипт із браузера: src/favorites.ts.
 *
 * @var array $args ['id' => int, 'class' => string]
 */
defined('ABSPATH') || exit;
?>
<button type="button" class="fav <?php echo esc_attr($args['class'] ?? ''); ?>" data-fav="<?php echo (int) $args['id']; ?>" aria-pressed="false" aria-label="В улюблене">
	<?php echo raky_site_icon('heart', 21, 'fav__off'); ?><?php echo raky_site_icon('heart-filled', 24, 'fav__on'); ?>
</button>
