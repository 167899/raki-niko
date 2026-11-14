<?php
/** Новини: ті самі, що в розділі «Повідомлення» застосунку (менеджери публікують їх через Telegram-бот або адмінку). */
defined('ABSPATH') || exit;

$news = raky_site_news();
raky_site_part('header');
?>
<div class="wrap wrap--narrow">
	<?php raky_site_part('crumbs'); ?>
	<h1 class="page-title">Новини</h1>
	<?php if (! $news) : ?>
		<div class="empty"><?php echo raky_site_icon('bell', 40); ?><p>Новин поки немає</p></div>
	<?php endif; ?>
	<?php foreach ($news as $item) : ?>
		<article class="news" id="news-<?php echo (int) $item['id']; ?>">
			<time class="muted" datetime="<?php echo esc_attr($item['date']); ?>"><?php echo esc_html(raky_site_date($item['date'])); ?></time>
			<h2 class="news__title"><?php echo esc_html($item['title']); ?></h2>
			<?php if ($item['text'] !== '') : ?><p><?php echo nl2br(esc_html($item['text'])); ?></p><?php endif; ?>
		</article>
	<?php endforeach; ?>
</div>
<?php
raky_site_part('footer');
