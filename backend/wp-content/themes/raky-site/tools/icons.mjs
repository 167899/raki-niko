// Переносить іконки застосунку (mobile/src/icons.ts) у PHP-файл теми, щоб сайт і застосунок мали однакові іконки.
// Запуск: npm run icons
import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const source = readFileSync(resolve(here, '../../../../../mobile/src/icons.ts'), 'utf8');

function block(name) {
  const match = source.match(new RegExp(`export const ${name} = (\\{[\\s\\S]*?\\n\\}) as const;`));
  if (!match) throw new Error(`Не знайдено ${name} у icons.ts`);
  return JSON.parse(match[1].replace(/,\s*\}$/, '}'));
}

const outline = block('outlineIcons');
const filled = block('filledIcons');
const php = (map) =>
  Object.entries(map)
    .map(([key, paths]) => `\t\t'${key}' => [${paths.map((path) => `'${path}'`).join(', ')}],`)
    .join('\n');

writeFileSync(
  resolve(here, '../inc/icons.php'),
  `<?php
// Згенеровано tools/icons.mjs із mobile/src/icons.ts. Не редагуйте вручну.
defined('ABSPATH') || exit;

function raky_site_icon_paths(): array {
	return [
		'outline' => [
${php(outline)}
		],
		'filled'  => [
${php(filled)}
		],
	];
}
`,
);
console.log(`Іконок: ${Object.keys(outline).length + Object.keys(filled).length}`);
