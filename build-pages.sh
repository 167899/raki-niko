#!/bin/sh
# Збирає вебверсію застосунку з демо-даними й кладе її в корінь репозиторію для GitHub Pages.
set -e
cd "$(dirname "$0")"
OUT="$(mktemp -d)"
(cd mobile && CI=1 EXPO_PUBLIC_DEMO=1 npx expo export --platform web --clear --output-dir "$OUT")
rm -rf _expo assets demo favicon.ico metadata.json index.html
cp -R "$OUT"/. .
rm -rf "$OUT"
