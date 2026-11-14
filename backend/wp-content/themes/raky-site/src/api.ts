/**
 * Той самий API, що й у застосунку (mobile/src/api.ts): /?rest_route=/raky/v1/…
 * Сайт і сервер на одному домені, тож запити йдуть без CORS.
 */
import type { Banner, Catalog, Config, DeliveryQuote, MyFavorites, NewOrder, Order, OrderItem, Referral } from '@app/api';
import { ApiError } from '@app/apiError';

export { ApiError };

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  let response: Response;
  try {
    response = await fetch(`${window.RAKY.api}${path}`, { cache: 'no-store', ...init });
  } catch {
    throw new ApiError("Немає зв'язку із сервером. Перевірте інтернет.", 'network', 0);
  }
  const body = await response.json().catch(() => null);
  if (!response.ok) {
    throw new ApiError(body?.message ?? 'Щось пішло не так. Спробуйте ще раз.', body?.code ?? 'unknown', response.status);
  }
  return body as T;
}

const post = <T,>(path: string, body: unknown, headers: Record<string, string> = {}) =>
  request<T>(path, { method: 'POST', headers: { 'Content-Type': 'application/json', ...headers }, body: JSON.stringify(body) });

/** Вхід через Telegram (includes/auth.php плагіна). */
export type AuthStart = { token: string; url: string; expires_in: number };
export type AuthStatus = { status: 'pending' } | { status: 'confirmed'; session: string; phone: string; name: string };
export type MyOrders = { phone: string; orders: Order[] };
const SESSION_HEADER = 'X-Raky-Session';

export const api = {
  catalog: () => request<Catalog>('/catalog'),
  config: () => request<Config>('/config'),
  banners: () => request<Banner[]>('/banners'),
  quoteDelivery: (address: string) => request<DeliveryQuote>(`/delivery-quote&address=${encodeURIComponent(address)}`),
  checkPromo: (code: string, items: OrderItem[], phone: string) =>
    post<{ code: string; discount: number }>('/promo-check', { code, items, phone }),
  createOrder: (order: NewOrder) => post<Order>('/orders', order),
  referral: (phone: string) => post<Referral>('/referral', { phone }),
  order: (id: number, key: string) => request<Order>(`/orders/${id}&key=${encodeURIComponent(key)}`),
  authStart: () => post<AuthStart>('/auth/telegram', {}),
  authStatus: (token: string) => request<AuthStatus>(`/auth/telegram/${encodeURIComponent(token)}`),
  myOrders: (session: string) => request<MyOrders>('/me/orders', { headers: { [SESSION_HEADER]: session } }),
  favorites: (session: string) => request<MyFavorites>('/me/favorites', { headers: { [SESSION_HEADER]: session } }),
  saveFavorites: (session: string, ids: number[]) => post<MyFavorites>('/me/favorites', { ids }, { [SESSION_HEADER]: session }),
  logout: (session: string) => post<{ ok: boolean }>('/auth/logout', {}, { [SESSION_HEADER]: session }),
};
