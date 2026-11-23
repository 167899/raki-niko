/**
 * Стан у браузері: кошик, контакти покупця, оформлені замовлення, промокод.
 * Правила кошика ті самі, що в застосунку (mobile/src/shop.tsx): рядок — товар і смак,
 * повторне додавання збільшує вагу й докладає «Додатково».
 */
import { addonCost } from '@app/addons';
import type { Addon, AddonCounts, Catalog, OrderItem, Product } from '@app/api';
import { phoneDigits } from '@app/format';

export type CartLine = { productId: number; flavor: string; quantity: number; addons?: AddonCounts };
export type ChosenAddon = Addon & { quantity: number; total: number };
export type ResolvedLine = CartLine & { product: Product; unitPrice: number; extras: ChosenAddon[]; total: number };
export type User = { name: string; phone: string; email: string };
export type SavedOrder = { id: number; key: string; lines: CartLine[] };

const KEYS = {
  cart: 'raky.site.cart.v1',
  user: 'raky.site.user.v1',
  orders: 'raky.site.orders.v1',
  promo: 'raky.site.promo.v1',
  offered: 'raky.site.offered.v1',
  favorites: 'raky.site.favorites.v1',
  accounts: 'raky.site.accounts.v1',
  newsRead: 'raky.site.news-read.v1',
  session: 'raky.site.session.v1',
};

/** Номер, підтверджений у Telegram: з ним сервер віддає всі замовлення клієнта (і з застосунку). */
/** mergeFavorites — щойно увійшли: «Улюблене» з браузера додаємо до збереженого на сервері, а не замінюємо ним. */
export type Session = { session: string; phone: string; mergeFavorites?: boolean };

function read<T>(key: string, fallback: T): T {
  try {
    const raw = localStorage.getItem(key);
    return raw ? (JSON.parse(raw) as T) : fallback;
  } catch {
    // Приватний режим або пошкоджений запис: працюємо з порожнім значенням.
    return fallback;
  }
}

function write(key: string, value: unknown) {
  try {
    if (value === null) localStorage.removeItem(key);
    else localStorage.setItem(key, JSON.stringify(value));
  } catch {
    // Сховище недоступне: стан проживе до перезавантаження сторінки.
  }
}

const sameLine = (a: CartLine, b: CartLine) => a.productId === b.productId && a.flavor === b.flavor;

function mergeAddons(a?: AddonCounts, b?: AddonCounts): AddonCounts {
  const merged = { ...a };
  for (const [name, count] of Object.entries(b ?? {})) merged[name] = (merged[name] ?? 0) + count;
  return merged;
}

/** Повідомляє шапку й інші вкладки, що кошик змінився. */
function changed() {
  window.dispatchEvent(new CustomEvent('raky:cart'));
}

/** Улюблене, акаунт чи прочитані новини змінилися: шапка перемальовує лічильники й меню. */
function changedMine() {
  window.dispatchEvent(new CustomEvent('raky:mine'));
}

export const store = {
  cart: (): CartLine[] => read<CartLine[]>(KEYS.cart, []),
  setCart(lines: CartLine[]) {
    write(KEYS.cart, lines);
    changed();
  },
  addToCart(added: CartLine) {
    const current = store.cart();
    store.setCart(
      current.some((line) => sameLine(line, added))
        ? current.map((line) =>
            sameLine(line, added)
              ? { ...line, quantity: line.quantity + added.quantity, addons: mergeAddons(line.addons, added.addons) }
              : line,
          )
        : [...current, added],
    );
  },
  updateLine(target: CartLine, patch: Partial<CartLine>) {
    store.setCart(store.cart().map((line) => (sameLine(line, target) ? { ...line, ...patch } : line)));
  },
  removeLine(target: CartLine) {
    store.setCart(store.cart().filter((line) => !sameLine(line, target)));
  },
  clearCart() {
    store.setCart([]);
    store.setPromo(null);
  },

  /** Поточний покупець. Як у застосунку, акаунт живе на цьому пристрої; після оформлення замовлення він створюється сам. */
  user: (): User | null => read<User | null>(KEYS.user, null),
  setUser(user: User | null) {
    const previous = store.user();
    write(KEYS.user, user);
    if (user) {
      const accounts = store.accounts();
      if (previous && !samePhone(previous.phone, user.phone)) delete accounts[phoneDigits(previous.phone)];
      write(KEYS.accounts, { ...accounts, [phoneDigits(user.phone)]: user });
    }
    changedMine();
  },
  accounts: (): Record<string, User> => read<Record<string, User>>(KEYS.accounts, {}),
  findAccount: (phone: string): User | null => store.accounts()[phoneDigits(phone)] ?? null,
  signOut() {
    write(KEYS.user, null);
    write(KEYS.session, null);
    changedMine();
  },

  session: (): Session | null => read<Session | null>(KEYS.session, null),
  setSession(session: Session | null) {
    write(KEYS.session, session);
    changedMine();
  },
  /** Чи підтверджено в Telegram саме номер поточного акаунта. */
  verified: (): boolean => {
    const session = store.session();
    const user = store.user();
    return !!session && !!user && samePhone(session.phone, user.phone);
  },
  /** Видаляє акаунт, його дані й список замовлень на цьому пристрої. */
  deleteAccount() {
    const user = store.user();
    if (user) {
      const accounts = store.accounts();
      delete accounts[phoneDigits(user.phone)];
      write(KEYS.accounts, accounts);
    }
    write(KEYS.orders, null);
    store.signOut();
  },

  favorites: (): number[] => read<number[]>(KEYS.favorites, []),
  isFavorite: (id: number) => store.favorites().includes(id),
  setFavorites(ids: number[]) {
    write(KEYS.favorites, ids);
    changedMine();
  },
  /** Повертає новий стан: true — додано в улюблене. */
  toggleFavorite(id: number): boolean {
    const current = store.favorites();
    const on = !current.includes(id);
    write(KEYS.favorites, on ? [...current, id] : current.filter((item) => item !== id));
    changedMine();
    return on;
  },

  unreadNews: () => {
    const seen = read<number[]>(KEYS.newsRead, []);
    return (window.RAKY.news ?? []).filter((id) => !seen.includes(id)).length;
  },
  markNewsRead() {
    write(KEYS.newsRead, [...new Set([...read<number[]>(KEYS.newsRead, []), ...(window.RAKY.news ?? [])])]);
    changedMine();
  },

  orders: (): SavedOrder[] => read<SavedOrder[]>(KEYS.orders, []),
  rememberOrder(order: SavedOrder) {
    write(KEYS.orders, [order, ...store.orders().filter((item) => item.id !== order.id)]);
  },

  /** Застосований промокод живе між кошиком і оформленням. */
  promo: (): string | null => read<string | null>(KEYS.promo, null),
  setPromo: (code: string | null) => write(KEYS.promo, code),

  /** Бонус за друга підставляється сам один раз; якщо клієнт його прибрав, не повертаємо. */
  wasOffered: (code: string) => read<string[]>(KEYS.offered, []).includes(code),
  markOffered: (code: string) => write(KEYS.offered, [...new Set([...read<string[]>(KEYS.offered, []), code])]),
};

/** Рядки кошика з цінами з каталогу. Товари, яких уже немає, і прибрані додатки зникають. */
export function resolveLines(cart: CartLine[], catalog: Catalog): ResolvedLine[] {
  const byId = new Map(catalog.products.map((product) => [product.id, product]));
  return cart.flatMap((line) => {
    const product = byId.get(line.productId);
    if (!product?.in_stock) return [];
    const unitPrice = product.price + (product.flavors.find((flavor) => flavor.name === line.flavor)?.extra ?? 0);
    const extras = (product.addons ?? []).flatMap((addon) => {
      const count = line.addons?.[addon.name] ?? 0;
      return count > 0 ? [{ ...addon, quantity: count, total: addonCost(addon, count) }] : [];
    });
    const extrasTotal = extras.reduce((sum, addon) => sum + addon.total, 0);
    return [{ ...line, product, unitPrice, extras, total: unitPrice * line.quantity + extrasTotal }];
  });
}

export const subtotalOf = (lines: ResolvedLine[]) => lines.reduce((sum, line) => sum + line.total, 0);

export const orderItemsOf = (lines: ResolvedLine[]): OrderItem[] =>
  lines.map((line) => ({
    product_id: line.productId,
    quantity: line.quantity,
    flavor: line.flavor,
    addons: line.extras.map(({ name, quantity }) => ({ name, quantity })),
  }));

export const samePhone = (a: string, b: string) => phoneDigits(a) === phoneDigits(b);
