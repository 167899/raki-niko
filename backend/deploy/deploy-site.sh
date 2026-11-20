#!/bin/sh
# Викладає тему сайту raky-site на хостинг.
#
#   ./deploy-site.sh raki              оновити тему (сайт уже ввімкнено)
#   ./deploy-site.sh raki --activate   перший раз: «красиві» адреси, адреси WooCommerce /tovar/ і /katalog/, увімкнути тему
#   ./deploy-site.sh raki --rollback   повернути стандартну тему й прості адреси
#
# API застосунку (?rest_route=/raky/v1/…) від цього не залежить і працює за будь-яких адрес.
set -e
HOST=$1
[ -n "$HOST" ] || { echo "Вкажіть SSH-хост: ./deploy-site.sh raki [--activate|--rollback]"; exit 1; }
WP_PATH=${WP_PATH:-public_html}
cd "$(dirname "$0")/.."
THEME=wp-content/themes/raky-site
WP=$(ssh "$HOST" 'command -v wp || echo "php ~/wp-cli.phar"' | tail -1)
wp() { ssh "$HOST" "cd $WP_PATH && $WP $*"; }

if [ "$2" = "--rollback" ]; then
  wp theme activate twentytwentyfive
  wp rewrite structure "''"
  wp litespeed-purge all 2>/dev/null || true
  echo "Повернуто стандартну тему. Тема raky-site лишилася на сервері, її можна ввімкнути знову."
  exit 0
fi

echo "Збираю стилі й скрипти…"
(cd "$THEME" && npm ci --silent && npm run build --silent)

echo "Викладаю тему…"
# Вихідні тексти, node_modules і налаштування збірки на сервері не потрібні.
COPYFILE_DISABLE=1 tar --no-xattrs -C "$THEME" \
  --exclude node_modules --exclude src --exclude tools --exclude 'package*.json' --exclude tsconfig.json --exclude vite.config.ts \
  -czf - . |
  ssh "$HOST" "set -e; cd $WP_PATH/wp-content/themes; rm -rf raky-site.new raky-site.old; mkdir raky-site.new; tar -xzf - -C raky-site.new; [ ! -d raky-site ] || mv raky-site raky-site.old; mv raky-site.new raky-site; rm -rf raky-site.old"

echo "Кеш у браузері на рік для файлів з незмінними іменами (збірка теми з хешем у назві, завантажені картинки)…"
# Сервер за замовчуванням дає 7 днів. Імена цих файлів при зміні вмісту змінюються, тож довгий кеш безпечний.
CACHE_RULES='# BEGIN RAKY CACHE
<IfModule mod_headers.c>
<FilesMatch "\.(js|css|jpe?g|png|webp|avif|gif|svg|woff2?)$">
Header set Cache-Control "public, max-age=31536000, immutable"
</FilesMatch>
</IfModule>
# END RAKY CACHE'
ssh "$HOST" "set -e; cd $WP_PATH/wp-content; printf '%s\n' '$CACHE_RULES' > themes/raky-site/dist/.htaccess; touch uploads/.htaccess; grep -q 'BEGIN RAKY CACHE' uploads/.htaccess || printf '\n%s\n' '$CACHE_RULES' >> uploads/.htaccess"

echo "Перенаправлення http:// і www на https://…"
# Без нього сайт відкривається і за http://, і Google бачить дві копії. Блок ставиться на самий верх .htaccess, до кешу LiteSpeed.
# /.well-known/ лишаємо доступним по http: через нього хостинг продовжує SSL-сертифікат.
HTTPS_RULES='# BEGIN RAKY HTTPS
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteCond %{REQUEST_URI} !^/\.well-known/
RewriteCond %{HTTPS} !=on
RewriteCond %{HTTP:X-Forwarded-Proto} !=https
RewriteCond %{HTTP_HOST} ^(?:www\.)?(.+)$ [NC]
RewriteRule ^ https://%1%{REQUEST_URI} [R=301,L]
</IfModule>
# END RAKY HTTPS
'
printf '%s\n' "$HTTPS_RULES" | ssh "$HOST" "set -e; cd $WP_PATH; grep -q 'BEGIN RAKY HTTPS' .htaccess || { cp .htaccess .htaccess.before-https; cat - .htaccess > .htaccess.new; mv .htaccess.new .htaccess; }"

echo "Викладаю плагін адрес латиницею…"
COPYFILE_DISABLE=1 tar --no-xattrs -C wp-content/plugins -czf - raky-latin-slugs |
  ssh "$HOST" "set -e; cd $WP_PATH/wp-content/plugins; rm -rf raky-latin-slugs.new; mkdir raky-latin-slugs.new; tar -xzf - -C raky-latin-slugs.new; rm -rf raky-latin-slugs; mv raky-latin-slugs.new/raky-latin-slugs raky-latin-slugs; rm -rf raky-latin-slugs.new"
wp plugin activate raky-latin-slugs

if [ "$2" = "--activate" ]; then
  wp option update woocommerce_permalinks "'{\"product_base\":\"/tovar\",\"category_base\":\"katalog\",\"tag_base\":\"mitka\",\"attribute_base\":\"\",\"use_verbose_page_rules\":false}'" --format=json
  wp rewrite structure "'/%postname%/'"
  wp theme activate raky-site
  wp rewrite flush
  # WP-CLI на хостингу не пише .htaccess сам: дописуємо стандартний блок WordPress поруч із блоком LiteSpeed.
  ssh "$HOST" "cd $WP_PATH && grep -q '# BEGIN WordPress' .htaccess || printf '%s\n' '' '# BEGIN WordPress' '<IfModule mod_rewrite.c>' 'RewriteEngine On' 'RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]' 'RewriteBase /' 'RewriteRule ^index\\.php\$ - [L]' 'RewriteCond %{REQUEST_FILENAME} !-f' 'RewriteCond %{REQUEST_FILENAME} !-d' 'RewriteRule . /index.php [L]' '</IfModule>' '# END WordPress' >> .htaccess"
fi

wp litespeed-purge all 2>/dev/null || true
HOME_URL=$(wp option get home)
echo "Перевіряю…"
for path in "/" "/aktsii/" "/dostavka/" "/wp-sitemap.xml" "/?rest_route=/raky/v1/catalog"; do
  printf '%s %s\n' "$(curl -s -o /dev/null -w '%{http_code}' "$HOME_URL$path")" "$path"
done
echo "Готово: $HOME_URL"
