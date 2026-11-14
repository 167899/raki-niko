#!/bin/sh
# Локальний сайт: той самий WordPress, що й ./start.sh, але з темою raky-site і «красивими» адресами.
# Порт 9410, щоб не заважати серверу застосунку на 9400. Дані живуть до зупинки сервера.
# Стилі й скрипти теми: cd wp-content/themes/raky-site && npm install && npm run watch
cd "$(dirname "$0")"
[ -f cache/woocommerce.zip ] && [ -f cache/woocommerce-uk.zip ] || { echo "Спершу запустіть ./start.sh: він завантажить WooCommerce у cache/."; exit 1; }
exec npx wp-playground-cli server \
  --port "${PORT:-9410}" --login --blueprint ./blueprint-site.json \
  --mount "$PWD/wp-content/plugins/raky-shop:/wordpress/wp-content/plugins/raky-shop" \
  --mount "$PWD/wp-content/themes/raky-site:/wordpress/wp-content/themes/raky-site" \
  --mount "$PWD/wp-content/plugins/raky-latin-slugs:/wordpress/wp-content/plugins/raky-latin-slugs" \
  --mount "$PWD/cache:/wordpress/wp-content/raky-cache" \
  --define RAKY_DEMO_MANAGER_PASSWORD "${RAKY_DEMO_MANAGER_PASSWORD:-local-manager-test}" \
  --php-ini memory_limit 512M
