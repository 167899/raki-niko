#!/bin/sh
# Локальний WooCommerce для розробки. Дані живуть до зупинки сервера.
cd "$(dirname "$0")"
# Архів WooCommerce зберігається локально, щоб запуск не залежав від завантаження всередині Playground.
if [ ! -f cache/woocommerce.zip ]; then
  mkdir -p cache
  curl -fsSL -o cache/woocommerce.zip https://downloads.wordpress.org/plugin/woocommerce.latest-stable.zip || exit 1
fi
exec npx wp-playground-cli server \
  --port 9400 --login --blueprint ./blueprint.json \
  --mount "$PWD/wp-content/plugins/raky-shop:/wordpress/wp-content/plugins/raky-shop" \
  --mount "$PWD/cache:/wordpress/wp-content/raky-cache" \
  --define RAKY_SYNC_SECRET "${RAKY_SYNC_SECRET:-local-dev-secret}" \
  --define RAKY_DEMO_MANAGER_PASSWORD "${RAKY_DEMO_MANAGER_PASSWORD:-local-manager-test}" \
  --php-ini memory_limit 512M
