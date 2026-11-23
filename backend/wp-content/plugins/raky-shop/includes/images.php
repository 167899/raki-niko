<?php
/**
 * Стискання картинок при завантаженні — без сторонніх плагінів.
 *
 * Кожну завантажену JPG, PNG чи WebP зменшуємо до 2560 px по довшій стороні та перезберігаємо з якістю 82:
 * на око різниці немає, а файл зазвичай легшає в 3–5 разів. Великі PNG без прозорості (фото, збережені як PNG)
 * стають JPG. Якщо після стискання файл не став легшим, лишаємо оригінал.
 * Мініатюри WordPress робить уже зі стисненого файлу.
 *
 * Уже завантажені картинки стискає команда: wp raky compress-images [--dry-run]
 */
defined('ABSPATH') || exit;

const RAKY_IMAGE_MAX_SIDE  = 2560;
const RAKY_IMAGE_QUALITY   = 82;
const RAKY_IMAGE_MIN_BYTES = 200 * 1024; // легші файли без потреби не чіпаємо

add_filter('wp_handle_upload', function (array $upload) {
  if (!empty($upload['error']) || empty($upload['file'])) return $upload;
  $result = raky_compress_image($upload['file'], (string) ($upload['type'] ?? ''), true);
  if ($result && $result['file'] !== $upload['file']) {
    $upload['url']  = trailingslashit(dirname($upload['url'])) . basename($result['file']);
    $upload['file'] = $result['file'];
    $upload['type'] = $result['type'];
  }
  return $upload;
});

/**
 * Стискає файл на місці. Повертає ['file', 'type', 'before', 'after', 'width', 'height'] або null, якщо файл не змінено.
 * $allow_convert — дозволити PNG → JPG (лише для нових файлів: у старих адреса вже десь використана).
 */
function raky_compress_image(string $file, string $mime, bool $allow_convert = false): ?array {
  if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || !is_file($file)) return null;
  $size = @getimagesize($file);
  if (!$size) return null;
  $before = (int) filesize($file);
  $too_big = max($size[0], $size[1]) > RAKY_IMAGE_MAX_SIDE;
  if (!$too_big && $before < RAKY_IMAGE_MIN_BYTES) return null;

  $editor = wp_get_image_editor($file);
  if (is_wp_error($editor)) return null;
  // Фото з телефона: повертаємо за EXIF, бо після перезбереження позначка повороту може загубитися.
  if (method_exists($editor, 'maybe_exif_rotate')) $editor->maybe_exif_rotate();
  if ($too_big && is_wp_error($editor->resize(RAKY_IMAGE_MAX_SIDE, RAKY_IMAGE_MAX_SIDE, false))) return null;
  $editor->set_quality(RAKY_IMAGE_QUALITY);

  $target_mime = $mime;
  if ($mime === 'image/png' && $allow_convert && $before >= RAKY_IMAGE_MIN_BYTES && !raky_png_has_transparency($file)) {
    $target_mime = 'image/jpeg';
  }
  $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$target_mime];
  $tmp = $file . '.raky-tmp.' . $ext;
  $saved = $editor->save($tmp, $target_mime);
  if (is_wp_error($saved) || !is_file($tmp)) return null;
  clearstatcache(true, $tmp);
  $after = (int) filesize($tmp);

  // Не легше і розмір не змінювали — оригінал кращий.
  if ($after >= $before && !$too_big) {
    @unlink($tmp);
    return null;
  }

  $dest = $file;
  if ($target_mime !== $mime) {
    $dir  = dirname($file);
    $dest = $dir . '/' . wp_unique_filename($dir, pathinfo($file, PATHINFO_FILENAME) . '.' . $ext);
  }
  if (!@rename($tmp, $dest)) {
    @unlink($tmp);
    return null;
  }
  if ($dest !== $file) @unlink($file);
  return ['file' => $dest, 'type' => $target_mime, 'before' => $before, 'after' => $after,
    'width' => (int) $saved['width'], 'height' => (int) $saved['height']];
}

/** Чи є в PNG прозорі пікселі. Без Imagick вважаємо, що є (тоді PNG лишається PNG). */
function raky_png_has_transparency(string $file): bool {
  if (!class_exists('Imagick')) return true;
  try {
    $image = new Imagick($file);
    if (!$image->getImageAlphaChannel()) return false;
    $range = $image->getImageChannelRange(Imagick::CHANNEL_ALPHA);
    return $range['minima'] < $range['maxima'];
  } catch (Throwable $e) {
    return true;
  }
}

if (defined('WP_CLI') && WP_CLI) {
  /**
   * Стискає вже завантажені картинки (той самий формат і та сама адреса файлу).
   *
   * [--dry-run]
   * : Лише показати, скільки можна заощадити.
   */
  WP_CLI::add_command('raky compress-images', function (array $args, array $assoc) {
    $dry = !empty($assoc['dry-run']);
    $ids = get_posts(['post_type' => 'attachment', 'post_status' => 'any', 'fields' => 'ids', 'numberposts' => -1,
      'post_mime_type' => ['image/jpeg', 'image/png', 'image/webp']]);
    $saved_total = 0;
    $changed = 0;
    foreach ($ids as $id) {
      $file = get_attached_file($id);
      if (!$file || !is_file($file)) continue;
      $mime = (string) get_post_mime_type($id);
      if ($dry) {
        $size = @getimagesize($file);
        $bytes = (int) filesize($file);
        if ($size && ($bytes >= RAKY_IMAGE_MIN_BYTES || max($size[0], $size[1]) > RAKY_IMAGE_MAX_SIDE)) {
          WP_CLI::log(sprintf('#%d %s — %s', $id, basename($file), size_format($bytes, 1)));
          $changed++;
        }
        continue;
      }
      $meta = wp_get_attachment_metadata($id);
      // PNG-фото стають JPG: картинки сайт бере за номером, а не за адресою файлу, тож посилання не зламаються.
      $result = raky_compress_image($file, $mime, $mime === 'image/png');
      if (!$result) continue;
      if ($result['file'] !== $file) {
        // Старі мініатюри PNG прибираємо, нові робимо з JPG.
        foreach ((is_array($meta) ? $meta['sizes'] ?? [] : []) as $size) {
          @unlink(dirname($file) . '/' . $size['file']);
        }
        update_attached_file($id, $result['file']);
        wp_update_post(['ID' => $id, 'post_mime_type' => $result['type']]);
        require_once ABSPATH . 'wp-admin/includes/image.php';
        wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $result['file']));
      } elseif (is_array($meta)) {
        $meta['filesize'] = $result['after'];
        $meta['width'] = $result['width'];
        $meta['height'] = $result['height'];
        wp_update_attachment_metadata($id, $meta);
      }
      $saved_total += $result['before'] - $result['after'];
      $changed++;
      WP_CLI::log(sprintf('#%d %s: %s → %s', $id, basename($file), size_format($result['before'], 1), size_format($result['after'], 1)));
    }
    WP_CLI::success($dry
      ? sprintf('Можна стиснути: %d', $changed)
      : sprintf('Стиснуто: %d, заощаджено %s', $changed, size_format($saved_total, 1)));
  });
}
