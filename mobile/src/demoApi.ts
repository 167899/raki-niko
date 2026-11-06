import AsyncStorage from '@react-native-async-storage/async-storage';
import type { Api, Banner, Catalog, Config, NewOrder, News, Order, OrderItem, Product } from './api';
import { ApiError } from './apiError';
import zones from './demoZones.json';

/** Демо для GitHub Pages: дані вбудовані, підтвердження менеджера імітується через кілька секунд. */
const CONFIRM_AFTER_MS = 6000;
const ORDERS_KEY = 'raky.demo.orders.v1';
const PHOTO = 'demo/raki-vershkovi.jpg';

const boiled = [
  { name: 'Класичні', extra: 0 },
  { name: 'Гострі', extra: 0 },
  { name: 'З часником', extra: 50 },
];
const creamy = [
  { name: 'Звичайні', extra: 0 },
  { name: 'Гострі', extra: 20 },
  { name: 'З сиром Дор блю', extra: 80 },
];

function product(
  id: number,
  name: string,
  description: string,
  category_id: number,
  price: number,
  group: string,
  caliber: string,
  caliber_hint: string,
  per_kg: number,
  flavors: Product['flavors'],
  image: string | null = null,
): Product {
  const base = { unit: 'kg', step: 0.5, min: 1, in_stock: true, sku: `DEMO-${id}` } as const;
  return { ...base, id, name, description, category_id, price, group, caliber, caliber_hint, per_kg, flavors, image, image_large: image };
}

const catalog: Catalog = {
  categories: [
    { id: 1, name: 'Варені', slug: 'vareni' },
    { id: 2, name: 'Живі', slug: 'zhyvi' },
  ],
  products: [
    product(1, 'Раки варені, середні', 'Зі спеціями та кропом', 1, 750, 'Раки варені', 'середні', '30–40 г', 28, boiled),
    product(2, 'Раки варені, великі', 'Зі спеціями та кропом', 1, 950, 'Раки варені', 'великі', '40–60 г', 20, boiled),
    product(3, 'Раки вершкові, добірні', '', 1, 1300, 'Раки вершкові', 'добірні', '70–90 г', 12, creamy, PHOTO),
    product(4, 'Раки вершкові, середні', '', 1, 1100, 'Раки вершкові', 'середні', '40–50 г', 23, creamy, PHOTO),
    product(5, 'Раки живі, середні', 'Щойно з води', 2, 650, '', 'середні', '30–40 г', 28, []),
    product(6, 'Раки живі, великі', 'Щойно з води', 2, 850, '', 'великі', '40–60 г', 20, []),
  ],
};

const hours = 'Щодня 10:00–21:00';
const config: Config = {
  currency: 'UAH',
  support_phone: '+380936473902',
  referral: { enabled: false, percent: 5 },
  outlets: [
    { id: 'center', name: 'Рюміна 15', address: 'Миколаїв, вул. Рюміна, 15', phone: '+380936473902', lat: 46.975, lng: 31.9946, hours },
    { id: 'north', name: 'Спаська 47/3', address: 'Миколаїв, вул. Спаська, 47/3', phone: '+380936473902', lat: 47.012, lng: 32.008, hours },
  ],
  zones: zones as Config['zones'],
};

const banners: Banner[] = [
  { id: 1, title: '−10% на перше замовлення', label: 'Акція', subtitle: 'Лише до неділі', color: '#D92B20', image: null, code: 'RAKI10' },
  { id: 2, title: '−15% для військових', label: 'Для військових', subtitle: 'На кожне замовлення', color: '#A32D2D', image: null, code: 'HERO15' },
  { id: 3, title: 'Вершкові раки', label: 'Новинка', subtitle: 'Спробуйте цього тижня', color: '#111111', image: null, code: null },
];

const news: News[] = [
  { id: 1, title: 'Вершкові раки вже в меню', text: 'Спробуйте новий смак цього тижня', date: '2026-11-06T10:00:00.000Z' },
];

const PROMOS: Record<string, number> = { RAKI10: 10, HERO15: 15 };

const REQUISITES = 'Це демонстрація, реквізити буде додано\nОтримувач: дані ФОП буде додано\nIBAN: буде додано';

const unitPrice = (item: OrderItem) => {
  const found = catalog.products.find((candidate) => candidate.id === item.product_id)!;
  return found.price + (found.flavors.find((flavor) => flavor.name === item.flavor)?.extra ?? 0);
};

const subtotalOf = (items: OrderItem[]) => items.reduce((sum, item) => sum + unitPrice(item) * item.quantity, 0);

function discountFor(code: string, items: OrderItem[]): number {
  const percent = PROMOS[code.trim().toUpperCase()];
  if (!percent) throw new ApiError('Такого промокоду немає.', 'raky_bad_promo', 422);
  return Math.round(subtotalOf(items) * percent) / 100;
}

async function loadOrders(): Promise<Order[]> {
  const raw = await AsyncStorage.getItem(ORDERS_KEY);
  return raw ? JSON.parse(raw) : [];
}

/** Статус залежить від часу: менеджер «підтверджує» замовлення через кілька секунд. */
function withStatus(order: Order): Order {
  if (Date.now() - new Date(order.created_at!).getTime() < CONFIRM_AFTER_MS) return order;
  return order.payment_method === 'cash'
    ? { ...order, status: { code: 'preparing', label: 'Готуємо замовлення' } }
    : { ...order, status: { code: 'awaiting_payment', label: 'Очікує оплату' }, requisites: REQUISITES };
}

export const demoApi: Api = {
  catalog: async () => catalog,
  config: async () => config,
  banners: async () => banners,
  news: async () => news,
  // У демо немає пошуку адрес: вартість доставки уточнює менеджер.
  quoteDelivery: async () => ({ found: false }),
  checkPromo: async (code, items) => ({ code: code.trim().toUpperCase(), discount: discountFor(code, items) }),
  createOrder: async (order: NewOrder) => {
    const subtotal = subtotalOf(order.items);
    const discount = order.promo ? discountFor(order.promo, order.items) : 0;
    const orders = await loadOrders();
    const outlet = config.outlets.find((candidate) => candidate.id === order.outlet_id);
    const created: Order = {
      id: orders.reduce((max, item) => Math.max(max, item.id), 1024) + 1,
      key: 'demo',
      status: { code: 'awaiting_confirmation', label: 'Менеджер перевіряє замовлення' },
      created_at: new Date().toISOString(),
      items: order.items.map((item) => ({
        name: catalog.products.find((candidate) => candidate.id === item.product_id)!.name,
        quantity: item.quantity,
        unit: 'kg',
        flavor: item.flavor,
        total: unitPrice(item) * item.quantity,
      })),
      shipping: 0,
      discount,
      delivery_type: order.delivery_type,
      total: subtotal - discount,
      has_weighted: true,
      address: outlet?.name ?? order.address,
      payment_method: order.payment_method,
      ready_at: order.ready_at,
      comment: order.comment,
      requisites: null,
    };
    await AsyncStorage.setItem(ORDERS_KEY, JSON.stringify([created, ...orders]));
    return created;
  },
  order: async (id) => {
    const found = (await loadOrders()).find((order) => order.id === id);
    if (!found) throw new ApiError('Замовлення не знайдено.', 'raky_order_not_found', 404);
    return withStatus(found);
  },
};
