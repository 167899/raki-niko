<?php
/**
 * Імпорт клієнтів зі старого застосунку: wp eval-file tools/import-clients.php /шлях/до/clients.json
 * Файл з даними клієнтів у репозиторій не кладемо.
 */
defined('ABSPATH') || exit;

$path = $args[0] ?? '';
if ($path === '' || ! is_readable($path)) {
	WP_CLI::error('Вкажіть шлях до JSON-файлу з клієнтами.');
}
$records = json_decode((string) file_get_contents($path), true);
if (! is_array($records)) {
	WP_CLI::error('Файл не схожий на експорт клієнтів (JSON-об\'єкт).');
}
$stats = raky_import_clients($records);
WP_CLI::log(sprintf('Нових клієнтів: %d, оновлено: %d, пропущено: %d.', $stats['created'], $stats['updated'], count($stats['skipped'])));
foreach ($stats['skipped'] as $reason) {
	WP_CLI::log('  пропущено — ' . $reason);
}
