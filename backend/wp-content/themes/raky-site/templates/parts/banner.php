<?php
/**
 * Лицьова сторона банера: колір або картинка й написи, увімкнені в адмінці (як BannerFace у застосунку).
 *
 * @var array $args ['banner' => array, 'subtitle' => bool, 'tag' => 'div'|'a', 'href' => string]
 */
defined('ABSPATH') || exit;

$banner   = $args['banner'];
$show     = array_merge(['title' => true, 'label' => true, 'subtitle' => true], (array) ($banner['show'] ?? []));
$label    = $show['label'] && $banner['label'] !== '';
$title    = $show['title'] && $banner['title'] !== '';
$subtitle = ($args['subtitle'] ?? true) && $show['subtitle'] && $banner['subtitle'] !== '';
$tag      = ($args['href'] ?? '') !== '' ? 'a' : 'div';
$style    = '--banner:' . ($banner['color'] ?: '#D92B20') . ';';
if ($banner['image']) {
	$style .= "background-image:url('" . esc_url($banner['image']) . "');";
}
?>
<<?php echo $tag; ?> class="banner<?php echo $banner['image'] ? ' banner--image' : ''; ?><?php echo ($label || $title || $subtitle) && $banner['image'] ? ' banner--shade' : ''; ?>" style="<?php echo esc_attr($style); ?>"<?php echo $tag === 'a' ? ' href="' . esc_url($args['href']) . '"' : ''; ?><?php echo ! ($label || $title || $subtitle) ? ' aria-label="Акція: ' . esc_attr($banner['title']) . '"' : ''; ?>>
	<?php if ($label) : ?><span class="banner__small"><?php echo esc_html($banner['label']); ?></span><?php endif; ?>
	<?php if ($title) : ?><span class="banner__title"><?php echo esc_html($banner['title']); ?></span><?php endif; ?>
	<?php if ($subtitle) : ?><span class="banner__small"><?php echo esc_html($banner['subtitle']); ?></span><?php endif; ?>
</<?php echo $tag; ?>>
