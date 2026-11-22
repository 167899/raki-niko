/**
 * Скриншоти й банер для Google Play та App Store з екранів у store/raw (їх знімає store/capture.mjs).
 *   node store/compose.mjs
 * Результат — store/out/: app-store/ (1290×2796), google-play/ (1080×1920), банер 1024×500, іконка 512×512.
 * Малює Chrome за HTML-шаблоном, тож шрифти й кирилиця такі самі, як у браузері.
 */
import { execFileSync } from 'node:child_process';
import { mkdirSync, writeFileSync, mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';

const CHROME = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const RAW = resolve('store/raw');
const OUT = resolve('store/out');
const ICON = resolve('assets/icon.png');
const RED = '#D92B20';

// Порядок і підписи скриншотів.
const SHOTS = [
  ['1-home', 'Раки й морепродукти', 'з доставкою додому'],
  ['2-product', 'Обирайте розмір,', 'смак і вагу'],
  ['3-sushi', 'Суші, роли', 'та сети'],
  ['4-delivery', 'Доставка по місту', 'або самовивіз'],
];

const SIZES = {
  'app-store': { width: 1290, height: 2796 },
  // Google: довга сторона не більше ніж удвічі довша за коротку.
  'google-play': { width: 1080, height: 1920 },
};

const tmp = mkdtempSync(join(tmpdir(), 'raky-compose-'));

function render(html, width, height, file) {
  const page = join(tmp, 'page.html');
  writeFileSync(page, html);
  execFileSync(CHROME, [
    '--headless=new', '--hide-scrollbars', '--force-device-scale-factor=1', '--default-background-color=00000000',
    `--window-size=${width},${height}`, `--screenshot=${file}`, `file://${page}`,
  ], { stdio: 'ignore' });
  // Магазини не приймають прозорість у скриншотах: зберігаємо без альфа-каналу.
  execFileSync('python3', ['-c', 'import sys; from PIL import Image; Image.open(sys.argv[1]).convert("RGB").save(sys.argv[1])', file]);
}

const base = `
  * { margin: 0; box-sizing: border-box; }
  html, body { width: 100%; height: 100%; overflow: hidden; }
  body { font-family: -apple-system, "SF Pro Display", "Helvetica Neue", Arial, sans-serif; color: #fff;
    background: radial-gradient(120% 80% at 50% 0%, #EE4A3D 0%, ${RED} 45%, #A9180F 100%); }
`;

function screenshotHtml(raw, line1, line2, { width, height }) {
  const pad = Math.round(width * 0.075);
  const title = Math.round(width * 0.074);
  const phoneWidth = Math.round(width * (height / width > 2 ? 0.8 : 0.66));
  const radius = Math.round(phoneWidth * 0.11);
  const bezel = Math.round(phoneWidth * 0.028);
  return `<!doctype html><meta charset="utf-8"><style>${base}
    .head { padding: ${Math.round(height * 0.055)}px ${pad}px 0; text-align: center; }
    h1 { font-size: ${title}px; line-height: 1.12; font-weight: 800; letter-spacing: -0.01em; }
    .phone { position: absolute; left: 50%; transform: translateX(-50%); top: ${Math.round(height * 0.2)}px;
      width: ${phoneWidth}px; padding: ${bezel}px; background: #141414; border-radius: ${radius}px;
      box-shadow: 0 ${Math.round(width * 0.03)}px ${Math.round(width * 0.08)}px rgba(60, 0, 0, .45); }
    .phone img { display: block; width: 100%; border-radius: ${radius - bezel}px; }
  </style>
  <div class="head"><h1>${line1}<br>${line2}</h1></div>
  <div class="phone"><img src="${encodeURI(`file://${RAW}/${raw}.png`)}"></div>`;
}

for (const [folder, size] of Object.entries(SIZES)) {
  mkdirSync(join(OUT, folder), { recursive: true });
  for (const [raw, line1, line2] of SHOTS) {
    const file = join(OUT, folder, `${raw}.png`);
    render(screenshotHtml(raw, line1, line2, size), size.width, size.height, file);
    console.log(file);
  }
}

// Банер Google Play 1024×500: важливе тримаємо по центру, краї Google може обрізати.
render(`<!doctype html><meta charset="utf-8"><style>${base}
  body { display: flex; align-items: center; justify-content: center; gap: 44px; }
  .logo { width: 250px; height: 250px; border-radius: 56px; background: #fff url("${encodeURI(`file://${ICON}`)}") center / 118% no-repeat;
    box-shadow: 0 14px 40px rgba(60, 0, 0, .35); flex: none; }
  h1 { font-size: 76px; font-weight: 800; letter-spacing: -0.01em; }
  p { font-size: 32px; line-height: 1.25; margin-top: 10px; opacity: .95; }
</style>
<div class="logo"></div>
<div><h1>Raki Niko</h1><p>Раки, морепродукти й суші<br>з доставкою в Миколаєві</p></div>`, 1024, 500, join(OUT, 'google-play', 'feature-graphic.png'));

// Іконка Google Play 512×512 з іконки застосунку.
execFileSync('sips', ['-z', '512', '512', ICON, '--out', join(OUT, 'google-play', 'icon-512.png')], { stdio: 'ignore' });

rmSync(tmp, { recursive: true, force: true });
console.log('Готово:', OUT);
