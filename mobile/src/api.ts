import { ApiError } from './apiError';

export { ApiError };

const BASE_URL = (process.env.EXPO_PUBLIC_API_URL ?? 'http://127.0.0.1:9400').replace(/\/$/, '');

export type Category = { id: number; name: string; slug: string };

/** extra: доплата за кілограм або штуку. */
export type Flavor = { name: string; extra: number };

export type Product = {
  id: number;
  sku: string;
  name: string;
  description: string;
  category_id: number;
  price: number;
  unit: 'kg' | 'pcs';
  step: number;
  min: number;
  in_stock: boolean;
  image: string | null;
  image_large: string | null;
  group: string;
  caliber: string;
  caliber_hint: string;
  per_kg: number;
  flavors: Flavor[];
};

export type Catalog = { categories: Category[]; products: Product[] };

export type Outlet = {
  id: string;
  name: string;
  address: string;
  phone: string;
  lat: number;
  lng: number;
  hours: string;
};

export type Zone = {
  id: string;
  name: string;
  outlet_id: string;
  fee: number;
  min_order: number;
  color: string;
  polygon: [number, number][];
};

export type Config = {
  currency: string;
  support_phone: string;
  referral: { enabled: boolean; percent: number };
  outlets: Outlet[];
  zones: Zone[];
};

export type Banner = {
  id: number;
  title: string;
  label: string;
  subtitle: string;
  color: string;
  image: string | null;
  code: string | null;
};

export type News = { id: number; title: string; text: string; date: string };

export type DeliveryCheck =
  | { in_zone: false }
  | { in_zone: true; zone_id: string; zone_name: string; outlet_id: string; fee: number; min_order: number };

export type OrderStatusCode =
  | 'awaiting_confirmation'
  | 'awaiting_payment'
  | 'preparing'
  | 'delivering'
  | 'completed'
  | 'cancelled'
  | 'unknown';

export type PaymentMethod = 'requisites' | 'cash';

export type Order = {
  id: number;
  key: string;
  status: { code: OrderStatusCode; label: string };
  created_at: string | null;
  items: { name: string; quantity: number; unit: 'kg' | 'pcs'; flavor: string; total: number }[];
  shipping: number;
  discount: number;
  delivery_type: 'delivery' | 'pickup' | '';
  total: number;
  has_weighted: boolean;
  address: string;
  payment_method: PaymentMethod;
  requisites: string | null;
};

export type OrderItem = { product_id: number; quantity: number; flavor: string };

export type NewOrder = {
  name: string;
  phone: string;
  email: string;
  address: string;
  comment: string;
  delivery_type: 'delivery' | 'pickup';
  payment_method: PaymentMethod;
  outlet_id?: string;
  lat?: number;
  lng?: number;
  items: OrderItem[];
  promo: string;
  expected_total: number;
};

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  let response: Response;
  try {
    response = await fetch(`${BASE_URL}/?rest_route=/raky/v1${path}`, init);
  } catch {
    throw new ApiError("Немає зв'язку із сервером. Перевірте інтернет.", 'network', 0);
  }
  const body = await response.json().catch(() => null);
  if (!response.ok) {
    throw new ApiError(body?.message ?? 'Щось пішло не так. Спробуйте ще раз.', body?.code ?? 'unknown', response.status);
  }
  return body as T;
}

const post = <T,>(path: string, body: unknown) =>
  request<T>(path, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });

const liveApi = {
  catalog: () => request<Catalog>('/catalog'),
  config: () => request<Config>('/config'),
  banners: () => request<Banner[]>('/banners'),
  news: () => request<News[]>('/news'),
  checkDelivery: (lat: number, lng: number) => request<DeliveryCheck>(`/delivery-check&lat=${lat}&lng=${lng}`),
  checkPromo: (code: string, items: OrderItem[], phone: string) =>
    post<{ code: string; discount: number }>('/promo-check', { code, items, phone }),
  createOrder: (order: NewOrder) => post<Order>('/orders', order),
  order: (id: number, key: string) => request<Order>(`/orders/${id}&key=${encodeURIComponent(key)}`),
};

export type Api = typeof liveApi;

// Веб-версія на GitHub Pages працює без сервера, на вбудованих демо-даних.
export const api: Api = process.env.EXPO_PUBLIC_DEMO === '1' ? require('./demoApi').demoApi : liveApi;
