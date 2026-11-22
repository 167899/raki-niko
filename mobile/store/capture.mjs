/**
 * Знімає екрани застосунку (вебзбірка Expo) у розмірі телефона для скриншотів магазинів.
 *   node store/capture.mjs [адреса] [папка]
 * Адреса за замовчуванням — запущений `expo start --web` з даними сайту (порт 8093).
 * Кроки описані в STEPS: натиснути текст, почекати, зняти екран.
 */
import { spawn } from 'node:child_process';
import { mkdirSync, writeFileSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const URL = process.argv[2] ?? 'http://localhost:8093';
const OUT = process.argv[3] ?? 'store/raw';
const CHROME = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
// iPhone 6,9": 430×932 точки, ×3 = 1290×2796 — один із розмірів, які приймає App Store (ширше 430 застосунок у браузері не розтягується).
const WIDTH = 430, HEIGHT = 932, SCALE = 3;
const PORT = 9333;

const STEPS = JSON.parse(process.env.STEPS ?? 'null') ?? [
  { wait: 9000 },
  { shot: 'home' },
];

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
mkdirSync(OUT, { recursive: true });

const chrome = spawn(CHROME, [
  '--headless=new', `--remote-debugging-port=${PORT}`, '--no-first-run', '--no-default-browser-check',
  `--user-data-dir=${mkdtempSync(join(tmpdir(), 'raky-shots-'))}`, '--hide-scrollbars', 'about:blank',
], { stdio: 'ignore' });

let target;
for (let i = 0; i < 50 && !target; i++) {
  await sleep(200);
  target = await fetch(`http://127.0.0.1:${PORT}/json/list`).then((r) => r.json()).then((l) => l.find((t) => t.type === 'page')).catch(() => null);
}
const ws = new WebSocket(target.webSocketDebuggerUrl);
await new Promise((r) => ws.addEventListener('open', r, { once: true }));
let id = 0;
const pending = new Map();
ws.addEventListener('message', (e) => {
  const msg = JSON.parse(e.data);
  if (msg.id && pending.has(msg.id)) { pending.get(msg.id)(msg); pending.delete(msg.id); }
});
const send = (method, params = {}) => new Promise((resolve, reject) => {
  const n = ++id;
  pending.set(n, (m) => (m.error ? reject(new Error(`${method}: ${m.error.message}`)) : resolve(m.result)));
  ws.send(JSON.stringify({ id: n, method, params }));
});
const evaluate = async (expression) => (await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true })).result.value;

await send('Emulation.setDeviceMetricsOverride', { width: WIDTH, height: HEIGHT, deviceScaleFactor: SCALE, mobile: true });
await send('Page.enable');
await send('Page.navigate', { url: URL });

async function click(text, nth = 0) {
  let rect = null;
  // Чекаємо до 20 с: дані каталогу приходять із сайту.
  for (let i = 0; i < 40 && !rect; i++) {
    if (i) await sleep(500);
    rect = await evaluate(`(() => {
    const all = [...document.querySelectorAll('body *')].filter((el) => el.textContent.trim() === ${JSON.stringify(text)} && el.getClientRects().length);
    const leaves = all.filter((el) => !all.some((o) => o !== el && el.contains(o)));
    const el = leaves[${nth}];
    if (!el) return null;
    el.scrollIntoView({ block: 'center' });
    const r = el.getBoundingClientRect();
    return { x: r.x + r.width / 2, y: r.y + r.height / 2 };
  })()`);
  }
  if (!rect) throw new Error(`Не знайдено «${text}»`);
  await sleep(300);
  for (const type of ['mousePressed', 'mouseReleased']) {
    await send('Input.dispatchMouseEvent', { type, x: rect.x, y: rect.y, button: 'left', clickCount: 1 });
  }
}

try {
  for (const step of STEPS) {
    if (step.wait) await sleep(step.wait);
    if (step.click) { await click(step.click, step.nth ?? 0); await sleep(step.after ?? 1200); }
    if (step.eval) { await evaluate(step.eval); await sleep(step.after ?? 600); }
    if (step.text) console.log(`--- ${step.text}\n` + (await evaluate('document.body.innerText')).slice(0, 1500));
    if (step.shot) {
      const { data } = await send('Page.captureScreenshot', { format: 'png' });
      writeFileSync(join(OUT, `${step.shot}.png`), Buffer.from(data, 'base64'));
      console.log(`знято ${step.shot}`);
    }
  }
} finally {
  ws.close();
  chrome.kill();
}
