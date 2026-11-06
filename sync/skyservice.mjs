/**
 * Клієнт Skyservice: вхід за логіном і паролем, як у вебкабінеті.
 * Це не публічне API: запити взято з коду кабінету та SDK і можуть змінитися.
 *
 * Вхід обмежений за кількістю спроб, а сесія живе довго, тому сесія кешується,
 * а повторний вхід робиться щонайбільше один раз за запуск.
 */
import { chmod, readFile, rm, writeFile } from 'node:fs/promises';

export class LoginStopped extends Error {}

const DEFAULT_API_URL = 'https://api.skyservice.online';

async function postJson(url, body) {
  const response = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  if (!response.ok) throw new LoginStopped(`Skyservice: вхід повернув HTTP ${response.status}`);
  return response.json();
}

export async function login({ authUrl, login, password }) {
  const json = await postJson(`${authUrl}/?section=users&action=auth`, { login, password, lang: 'uk' });
  if (json.status === 'done' && json.data?.token) return json.data.token;
  if (json.data?.canAuth === false) {
    throw new LoginStopped('Skyservice тимчасово заблокував вхід. Запуск зупинено, повторних спроб не буде.');
  }
  throw new LoginStopped('Skyservice не прийняв логін або пароль. Запуск зупинено, щоб не заблокувати вхід.');
}

export function sessionFromRedirect(redirectUrl, accountToken, fallbackApiUrl = DEFAULT_API_URL) {
  const url = new URL(redirectUrl);
  return {
    token: url.searchParams.get('token') ?? accountToken,
    // Те саме перетворення домену робить офіційний SDK.
    baseUrl: url.hostname.includes('dashboard') ? `https://${url.hostname.replace('dashboard', 'api')}` : fallbackApiUrl,
  };
}

export async function openCompany({ authUrl, accountToken, companyId, apiUrl }) {
  const json = await postJson(`${authUrl}/?section=users&action=redirect&token=${encodeURIComponent(accountToken)}`, {
    token: accountToken,
    company_id: companyId,
  });
  if (json.status !== 'done' || typeof json.data !== 'string') {
    throw new LoginStopped('Skyservice не відкрив компанію. Перевірте SKYSERVICE_COMPANY_ID.');
  }
  return sessionFromRedirect(json.data, accountToken, apiUrl);
}

export async function listCompanies({ authUrl, accountToken }) {
  const params = new URLSearchParams({ section: 'company', action: 'getCompanies', token: accountToken });
  const json = await (await fetch(`${authUrl}/?${params}`)).json();
  if (json.status !== 'done') throw new Error('Skyservice не повернув список компаній');
  const companies = Array.isArray(json.data) ? json.data : (json.data.companies ?? json.data.items ?? []);
  return companies.map(({ company_id, company_name }) => ({ id: company_id, name: company_name }));
}

async function apiGet(session, companyId, params) {
  const query = new URLSearchParams({ token: session.token, companyId, ...params });
  const response = await fetch(`${session.baseUrl}/?${query}`);
  if (response.status === 401 || response.status === 403) return { expired: true };
  if (!response.ok) throw new Error(`Skyservice: HTTP ${response.status}`);
  const json = await response.json();
  if (json.status !== 'done') return { expired: true };
  return { data: json.data };
}

export const queries = {
  tradepoints: { section: 'tradepoints', action: 'getTradepoints' },
  products: (tradepointId) => ({
    section: 'products',
    action: 'getProducts',
    pagelimit: '9999',
    ot: 'desc',
    of: 'nomenclatureId',
    tradepointId: String(tradepointId),
  }),
};

/** Сесія з кешем: логін лише коли її немає або сервер її відхилив. */
export function createClient({ authUrl, apiUrl = DEFAULT_API_URL, login: userLogin, password, companyId, sessionFile }) {
  let session = null;
  let loggedIn = false;

  async function freshSession() {
    if (loggedIn) throw new LoginStopped('Skyservice відхилив нову сесію. Запуск зупинено.');
    loggedIn = true;
    const accountToken = await login({ authUrl, login: userLogin, password });
    session = await openCompany({ authUrl, accountToken, companyId, apiUrl });
    await writeFile(sessionFile, JSON.stringify(session), { mode: 0o600 });
    await chmod(sessionFile, 0o600);
    return session;
  }

  async function currentSession() {
    if (session) return session;
    try {
      session = JSON.parse(await readFile(sessionFile, 'utf8'));
      return session;
    } catch {
      return freshSession();
    }
  }

  return {
    async get(params) {
      const first = await apiGet(await currentSession(), companyId, params);
      if (!first.expired) return first.data;
      await rm(sessionFile, { force: true });
      session = null;
      const second = await apiGet(await freshSession(), companyId, params);
      if (second.expired) throw new LoginStopped('Skyservice не приймає нову сесію. Запуск зупинено.');
      return second.data;
    },
  };
}
