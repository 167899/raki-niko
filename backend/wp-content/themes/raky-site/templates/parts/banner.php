<?php
/**
 * Лицьова сторона банера: колір або картинка й написи, увімкнені в адмінці (як BannerFace у застосунку).
 *
 * @var array $args ['banner' => array, 'subtitle' => bool, 'tag' => 'div'|'a', 'href' => string, 'first' => bool]
 */
defined('ABSPATH') || exit;

$banner   = $args['banner'];
$show     = array_merge(['title' => true, 'label' => true, 'subtitle' => true], (array) ($banner['show'] ?? []));
$label    = $show['label'] && $banner['label'] !== '';
$title    = $show['title'] && $banner['title'] !== '';
$subtitle = ($args['subtitle'] ?? true) && $show['subtitle'] && $banner['subtitle'] !== '';
$tag      = ($args['href'] ?? '') !== '' ? 'a' : 'div';
$style    = '--banner:' . ($banner['color'] ?: '#D92B20') . ';';
// Картинку показуємо цілком, у її власних пропорціях: без обрізання країв.
// Розміри — щоб сторінка не «стрибала» під час завантаження; srcset — щоб телефон брав зменшену копію, а не 2000 px.
$img = '';
if ($banner['image']) {
	$id    = attachment_url_to_postid($banner['image']);
	$meta  = $id ? wp_get_attachment_metadata($id) : null;
	$size  = ! empty($meta['width']) && ! empty($meta['height']) ? sprintf(' width="%d" height="%d"', $meta['width'], $meta['height']) : '';
	$set   = $id ? wp_get_attachment_image_srcset($id, 'full') : '';
	// Перший банер — найбільший елемент екрана: вантажимо одразу, решту — коли дійде черга.
	$load  = ! empty($args['first']) ? ' fetchpriority="high"' : ' loading="lazy"';
	$img   = sprintf(
		'<img class="banner__img" src="%s"%s%s alt="" decoding="async"%s>',
		esc_url($banner['image']),
		$set ? ' srcset="' . esc_attr($set) . '" sizes="(min-width: 1200px) 1150px, calc(100vw - 32px)"' : '',
		$size,
		$load
	);
}
?>
<<?php echo $tag; ?> class="banner<?php echo $banner['image'] ? ' banner--image' : ''; ?><?php echo ($label || $title || $subtitle) && $banner['image'] ? ' banner--shade' : ''; ?>" style="<?php echo esc_attr($style); ?>"<?php echo $tag === 'a' ? ' href="' . esc_url($args['href']) . '"' : ''; ?><?php echo ! ($label || $title || $subtitle) ? ' aria-label="Акція: ' . esc_attr($banner['title']) . '"' : ''; ?>>
	<?php echo $img; // phpcs:ignore — зібрано з екранованих частин вище ?>
	<?php if ($banner['image'] && ($label || $title || $subtitle)) : ?><span class="banner__text"><?php endif; ?>
	<?php if ($label) : ?><span class="banner__small"><?php echo esc_html($banner['label']); ?></span><?php endif; ?>
	<?php if ($title) : ?><span class="banner__title"><?php echo esc_html($banner['title']); ?></span><?php endif; ?>
	<?php if ($subtitle) : ?><span class="banner__small"><?php echo esc_html($banner['subtitle']); ?></span><?php endif; ?>
	<?php if ($banner['image'] && ($label || $title || $subtitle)) : ?></span><?php endif; ?>
</<?php echo $tag; ?>>
