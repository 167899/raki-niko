#!/bin/sh
# Викладає плагін raky-shop на хостинг через SSH і, з --setup, налаштовує WordPress через WP-CLI.
#
#   ./deploy.sh raki            оновити плагін
#   ./deploy.sh raki --setup    перший раз: WooCommerce, українська мова, налаштування магазину
#
# raki — Host у ~/.ssh/config (адреса, користувач і ключ від хостингу).
# WP_PATH — тека WordPress на сервері відносно домашньої, за замовчуванням public_html.
set -e
HOST=$1
[ -n "$HOST" ] || { echo "Вкажіть SSH-хост: ./deploy.sh raki [--setup]"; exit 1; }
WP_PATH=${WP_PATH:-public_html}
cd "$(dirname "$0")/.."
PLUGIN=wp-content/plugins/raky-shop

# WP-CLI: системний, а якщо його немає — власна копія в домашній теці.
WP=$(ssh "$HOST" 'command -v wp || { test -f ~/wp-cli.phar || curl -fsSLo ~/wp-cli.phar https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar; echo "php ~/wp-cli.phar"; }' | tail -1)
wp() { ssh "$HOST" "cd $WP_PATH && $WP $*"; }

wp core is-installed || { echo "WordPress у $WP_PATH не знайдено. Встановіть його в панелі хостингу або вкажіть WP_PATH."; exit 1; }

echo "Викладаю плагін…"
# Демо-дані й тестові фото на бойовому сервері не потрібні.
rsync -az --delete --exclude tools/seed-demo.php --exclude tools/seed-images "$PLUGIN/" "$HOST:$WP_PATH/$PLUGIN/"

if [ "$2" = "--setup" ]; then
  wp plugin install woocommerce --activate
  wp language core install uk --activate
  wp language plugin install woocommerce uk
  wp plugin activate raky-shop
  # WP-Cron запускає системний cron щохвилини, а не відвідування сайту.
  wp config set DISABLE_WP_CRON true --raw
  scp -q deploy/setup-production.php "$HOST:$WP_PATH/raky-setup.php"
  wp eval-file raky-setup.php
  ssh "$HOST" "rm -f $WP_PATH/raky-setup.php"
  echo
  echo "Додайте в панелі хостингу завдання cron щохвилини:"
  echo "  cd \$HOME/$WP_PATH && $WP cron event run --due-now >/dev/null 2>&1"
else
  wp plugin activate raky-shop
  wp language plugin update --all
fi
echo "Готово: $(wp option get home)"
