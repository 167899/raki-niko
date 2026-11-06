import assert from 'node:assert/strict';
import { existsSync, statSync } from 'node:fs';
import { mkdtemp, writeFile } from 'node:fs/promises';
import http from 'node:http';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { after, beforeEach, test } from 'node:test';
import { createClient, LoginStopped, queries, sessionFromRedirect } from './skyservice.mjs';

const state = { logins: 0, redirects: 0, validToken: 'company-1', loginReply: null, rejectAll: false };

const server = http.createServer((request, response) => {
  const url = new URL(request.url, 'http://mock');
  let body = '';
  request.on('data', (chunk) => (body += chunk));
  request.on('end', () => {
    const send = (json) => response.end(JSON.stringify(json));
    const action = url.searchParams.get('action');
    if (action === 'auth') {
      state.logins += 1;
      const { password } = JSON.parse(body);
      if (state.loginReply) return send(state.loginReply);
      return send(password === 'right' ? { status: 'done', data: { token: 'account-1' } } : { status: 'error', error: ['pass is incorrect'] });
    }
    if (action === 'redirect') {
      state.redirects += 1;
      return send({ status: 'done', data: `http://company.test/?token=${state.validToken}` });
    }
    if (action === 'getProducts') {
      if (state.rejectAll || url.searchParams.get('token') !== state.validToken) return send({ status: 'error' });
      return send({ status: 'done', data: { items: [{ nomenclatureCode: 'A', price: 1, active: 1 }] } });
    }
    send({ status: 'error' });
  });
});
await new Promise((resolve) => server.listen(0, resolve));
const base = `http://127.0.0.1:${server.address().port}`;
after(() => server.close());

let sessionFile;
const make = (password = 'right') =>
  createClient({ authUrl: base, apiUrl: base, login: 'u', password, companyId: '7', sessionFile });

beforeEach(async () => {
  Object.assign(state, { logins: 0, redirects: 0, validToken: 'company-1', loginReply: null, rejectAll: false });
  sessionFile = join(await mkdtemp(join(tmpdir(), 'sky-')), 'session.json');
});

test('вхід зберігає сесію з обмеженими правами на файл', async () => {
  const data = await make().get(queries.products(1));
  assert.equal(data.items.length, 1);
  assert.equal(state.logins, 1);
  assert.equal(statSync(sessionFile).mode & 0o777, 0o600);
});

test('наступний запуск не входить повторно', async () => {
  await make().get(queries.products(1));
  await make().get(queries.products(1));
  assert.equal(state.logins, 1);
});

test('застарілу сесію замінює один повторний вхід', async () => {
  await writeFile(sessionFile, JSON.stringify({ token: 'old', baseUrl: base }));
  await make().get(queries.products(1));
  assert.equal(state.logins, 1);
});

test('неправильний пароль зупиняє запуск після однієї спроби', async () => {
  await assert.rejects(make('wrong').get(queries.products(1)), LoginStopped);
  assert.equal(state.logins, 1);
  assert.equal(existsSync(sessionFile), false);
});

test('блокування входу зупиняє запуск', async () => {
  state.loginReply = { status: 'done', data: { canAuth: false, countsLeft: 0 } };
  await assert.rejects(make().get(queries.products(1)), /заблокував/);
  assert.equal(state.logins, 1);
});

test('сервер відхиляє нову сесію: без циклу входів', async () => {
  state.rejectAll = true;
  await assert.rejects(make().get(queries.products(1)), LoginStopped);
  assert.equal(state.logins, 1);
});

test('домен dashboard перетворюється на api, як в офіційному SDK', () => {
  assert.deepEqual(sessionFromRedirect('https://dashboard.skyservice.online/?token=t', 'a'), {
    token: 't',
    baseUrl: 'https://api.skyservice.online',
  });
  assert.equal(sessionFromRedirect('https://x.pro/', 'account', 'http://fallback').token, 'account');
});
