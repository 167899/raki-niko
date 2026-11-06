#!/usr/bin/env node
/**
 * Синхронізація цін Skyservice POS → WooCommerce. Налаштування: sync/.env.example.
 *   --list   показати компанії та торгові точки
 *   --apply  записати зміни; без нього лише показує, що зміниться
 */
import { fileURLToPath } from 'node:url';
import { createClient, listCompanies, login, LoginStopped, queries } from './skyservice.mjs';

const env = process.env;

function required(name) {
  if (!env[name]) throw new Error(`Не задано змінну ${name}`);
  return env[name];
}

/** Головна точка задає ціну, розбіжності на інших точках потрапляють у звіт. */
export function mergeTradepoints(productsByTradepoint) {
  const [main, ...others] = productsByTradepoint;
  const prices = new Map();
  for (const product of main) {
    if (product.nomenclatureCode) {
      prices.set(product.nomenclatureCode, {
        sku: product.nomenclatureCode,
        price: Number(product.price),
        active: product.active === 1,
      });
    }
  }
  const mismatches = [];
  for (const products of others) {
    for (const product of products) {
      const item = prices.get(product.nomenclatureCode);
      if (item && Number(product.price) !== item.price) {
        mismatches.push({ sku: item.sku, main: item.price, other: Number(product.price) });
      }
    }
  }
  return { items: [...prices.values()], mismatches };
}

async function list(authUrl) {
  const accountToken = await login({ authUrl, login: required('SKYSERVICE_LOGIN'), password: required('SKYSERVICE_PASSWORD') });
  console.log('Компанії:', await listCompanies({ authUrl, accountToken }));
  if (!env.SKYSERVICE_COMPANY_ID) return;
  const data = await client(authUrl).get(queries.tradepoints);
  console.log('Точки:', data.items.map(({ tradepointId, tradepointName }) => ({ id: tradepointId, name: tradepointName })));
}

function client(authUrl) {
  return createClient({
    authUrl,
    apiUrl: env.SKYSERVICE_URL,
    login: required('SKYSERVICE_LOGIN'),
    password: required('SKYSERVICE_PASSWORD'),
    companyId: required('SKYSERVICE_COMPANY_ID'),
    sessionFile: env.SKYSERVICE_SESSION_FILE ?? fileURLToPath(new URL('./.sky-session.json', import.meta.url)),
  });
}

async function main() {
  const authUrl = env.SKYSERVICE_AUTH_URL ?? 'https://api.auth.skyservice.online';
  if (process.argv.includes('--list')) return list(authUrl);

  const apply = process.argv.includes('--apply');
  // Перша точка у списку — головна.
  const tradepointIds = required('SKYSERVICE_TRADEPOINT_IDS').split(',').map((id) => id.trim());
  const wooUrl = required('WOO_URL').replace(/\/$/, '');
  const secret = required('RAKY_SYNC_SECRET');

  const sky = client(authUrl);
  const productsByTradepoint = [];
  for (const tradepointId of tradepointIds) {
    productsByTradepoint.push((await sky.get(queries.products(tradepointId))).items);
  }
  const { items, mismatches } = mergeTradepoints(productsByTradepoint);
  if (items.length === 0) throw new Error('Skyservice не повернув товарів, синхронізацію зупинено');

  const response = await fetch(`${wooUrl}/?rest_route=/raky/v1/sync/prices`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Raky-Sync-Secret': secret },
    body: JSON.stringify({ items, dry_run: !apply }),
  });
  if (!response.ok) throw new Error(`WooCommerce: HTTP ${response.status}`);
  console.log(JSON.stringify({ mode: apply ? 'apply' : 'dry-run', mismatches, report: await response.json() }, null, 2));
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  main().catch((error) => {
    console.error(error instanceof LoginStopped ? error.message : `Помилка: ${error.message}`);
    process.exit(1);
  });
}
