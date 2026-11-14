<?php
/**
 * Обов'язковий файл теми WordPress. Сторінки обирає inc/routes.php (фільтр template_include),
 * сюди запит потрапляє, лише якщо шаблон не знайдено.
 */
defined('ABSPATH') || exit;

require __DIR__ . '/templates/404.php';
