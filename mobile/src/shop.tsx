import AsyncStorage from '@react-native-async-storage/async-storage';
import { createContext, ReactNode, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { AddonCounts, api, ApiError, Banner, Catalog, Config, News, Order, OrderItem, OrderStatusCode, Product, Referral } from './api';
import { phoneDigits } from './format';

/** addons: «Додатково» до цього рядка, назва → штук. Старі записи кошика поля не мають. */
export type CartLine = { productId: number; flavor: string; quantity: number; addons?: AddonCounts };
export type ChosenAddon = { name: string; quantity: number; price: number };
/** total — товар разом із додатками. */
export type ResolvedLine = CartLine & { product: Product; unitPrice: number; extras: ChosenAddon[]; total: number };

/** Замовлення на цьому пристрої: ключ дає доступ до статусу, lines потрібні для повтору. */
type SavedOrder = { id: number; key: string; lines: CartLine[] };

export type User = { name: string; phone: string; email: string };

/** Акаунт поки живе лише на пристрої: серверні акаунти з'являться разом із перевіркою телефона. */
type Accounts = Record<string, User>;

type Promo = { code: string; discount: number };

/** Сповіщення про зміну статусу замовлення, створене на цьому пристрої. */
export type LocalNote = { id: string; title: string; text: string; date: string };

const STORAGE = {
  cart: 'raky.cart.v2',
  orders: 'raky.orders.v2',
  favorites: 'raky.favorites.v1',
  user: 'raky.user.v1',
  accounts: 'raky.accounts.v1',
  read: 'raky.read.v1',
  notes: 'raky.notes.v1',
  statuses: 'raky.statuses.v1',
};

const ORDER_POLL_MS = process.env.EXPO_PUBLIC_DEMO === '1' ? 3_000 : 30_000;

const STATUS_NOTES: Partial<Record<OrderStatusCode, (order: Order) => [string, string]>> = {
  awaiting_payment: ({ id }) => [`Замовлення № ${id} підтверджено`, 'Реквізити для оплати вже в замовленні'],
  preparing: ({ id, payment_method }) =>
    payment_method === 'cash'
      ? [`Замовлення № ${id} підтверджено`, 'Готуємо ваше замовлення']
      : [`Оплату за № ${id} отримано`, 'Готуємо ваше замовлення'],
  delivering: ({ id }) => [`Замовлення № ${id} передано в доставку`, "Кур'єр уже в дорозі"],
  cancelled: ({ id }) => [`Замовлення № ${id} скасовано`, 'Зателефонуйте нам, якщо є запитання'],
};

/** Рядок кошика — товар і смак. Повторне додавання збільшує вагу й докладає додатки. */
const sameLine = (a: CartLine, b: CartLine) => a.productId === b.productId && a.flavor === b.flavor;

const mergeAddons = (a?: AddonCounts, b?: AddonCounts): AddonCounts => {
  const merged = { ...a };
  for (const [name, count] of Object.entries(b ?? {})) merged[name] = (merged[name] ?? 0) + count;
  return merged;
};

/** Стан, що зберігається на пристрої. До відновлення нічого не записуємо, щоб не стерти збережене. */
function useStored<T>(key: string, initial: T, restored: boolean) {
  const [value, setValue] = useState<T>(initial);
  useEffect(() => {
    if (restored) AsyncStorage.setItem(key, JSON.stringify(value)).catch(() => undefined);
  }, [key, value, restored]);
  return [value, setValue] as const;
}

function useShopState() {
  const [catalog, setCatalog] = useState<Catalog | null>(null);
  const [config, setConfig] = useState<Config | null>(null);
  const [banners, setBanners] = useState<Banner[]>([]);
  const [news, setNews] = useState<News[]>([]);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [orderDetails, setOrderDetails] = useState<Record<number, Order>>({});
  const [promo, setPromo] = useState<Promo | null>(null);
  const [promoError, setPromoError] = useState('');
  const [ordersChecked, setOrdersChecked] = useState(false);

  const [restored, setRestored] = useState(false);
  // Останні відомі статуси: зміна, що сталася поки застосунок був закритий, теж дасть сповіщення.
  const statuses = useRef<Record<number, OrderStatusCode>>({});
  const [cart, setCart] = useStored<CartLine[]>(STORAGE.cart, [], restored);
  const [savedOrders, setSavedOrders] = useStored<SavedOrder[]>(STORAGE.orders, [], restored);
  const [favorites, setFavorites] = useStored<number[]>(STORAGE.favorites, [], restored);
  const [user, setUser] = useStored<User | null>(STORAGE.user, null, restored);
  const [accounts, setAccounts] = useStored<Accounts>(STORAGE.accounts, {}, restored);
  const [readIds, setReadIds] = useStored<string[]>(STORAGE.read, [], restored);
  const [notes, setNotes] = useStored<LocalNote[]>(STORAGE.notes, [], restored);

  const reload = useCallback(async () => {
    try {
      const [nextCatalog, nextConfig, nextBanners, nextNews] = await Promise.all([
        api.catalog(),
        api.config(),
        api.banners(),
        api.news(),
      ]);
      setCatalog(nextCatalog);
      setConfig(nextConfig);
      setBanners(nextBanners);
      setNews(nextNews);
      setLoadError(null);
    } catch (error) {
      setLoadError(error instanceof ApiError ? error.message : 'Не вдалося завантажити меню.');
    }
  }, []);

  useEffect(() => {
    reload();
    (async () => {
      const pairs = await AsyncStorage.multiGet(Object.values(STORAGE)).catch(() => []);
      const stored = Object.fromEntries(pairs);
      const parse = <T,>(key: string, apply: (value: T) => void) => {
        const raw = stored[key];
        if (!raw) return;
        try {
          apply(JSON.parse(raw));
        } catch {
          // Пошкоджений запис ігноруємо: застосунок стартує з порожнім значенням.
        }
      };
      parse(STORAGE.cart, setCart);
      parse(STORAGE.orders, setSavedOrders);
      parse(STORAGE.favorites, setFavorites);
      parse(STORAGE.user, setUser);
      parse(STORAGE.accounts, setAccounts);
      parse(STORAGE.read, setReadIds);
      parse(STORAGE.notes, setNotes);
      parse<Record<number, OrderStatusCode>>(STORAGE.statuses, (value) => {
        statuses.current = value;
      });
      setRestored(true);
    })();
    // Сетери стабільні, відновлення виконується один раз.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [reload]);

  const productById = useMemo(() => new Map(catalog?.products.map((product) => [product.id, product])), [catalog]);

  const lines = useMemo<ResolvedLine[]>(
    () =>
      cart.flatMap((line) => {
        const product = productById.get(line.productId);
        if (!product?.in_stock) return [];
        const unitPrice = product.price + (product.flavors.find((flavor) => flavor.name === line.flavor)?.extra ?? 0);
        // Додаток, який прибрали з товару в адмінці, з кошика зникає.
        const extras = (product.addons ?? []).flatMap((addon) => {
          const count = line.addons?.[addon.name] ?? 0;
          return count > 0 ? [{ name: addon.name, quantity: count, price: addon.price }] : [];
        });
        const extrasTotal = extras.reduce((sum, addon) => sum + addon.price * addon.quantity, 0);
        return [{ ...line, product, unitPrice, extras, total: unitPrice * line.quantity + extrasTotal }];
      }),
    [cart, productById],
  );

  const subtotal = useMemo(() => lines.reduce((sum, line) => sum + line.total, 0), [lines]);

  const orderItems = useMemo<OrderItem[]>(
    () =>
      lines.map((line) => ({
        product_id: line.productId,
        quantity: line.quantity,
        flavor: line.flavor,
        addons: line.extras.map(({ name, quantity }) => ({ name, quantity })),
      })),
    [lines],
  );

  const addToCart = useCallback(
    (added: CartLine) =>
      setCart((current) =>
        current.some((line) => sameLine(line, added))
          ? current.map((line) =>
              sameLine(line, added)
                ? { ...line, quantity: line.quantity + added.quantity, addons: mergeAddons(line.addons, added.addons) }
                : line,
            )
          : [...current, added],
      ),
    [setCart],
  );

  const setLineQuantity = useCallback(
    (target: CartLine, quantity: number) =>
      setCart((current) => current.map((line) => (sameLine(line, target) ? { ...line, quantity } : line))),
    [setCart],
  );

  const setLineAddon = useCallback(
    (target: CartLine, name: string, count: number) =>
      setCart((current) =>
        current.map((line) => (sameLine(line, target) ? { ...line, addons: { ...line.addons, [name]: count } } : line)),
      ),
    [setCart],
  );

  const removeLine = useCallback(
    (target: CartLine) => setCart((current) => current.filter((line) => !sameLine(line, target))),
    [setCart],
  );

  const clearCart = useCallback(() => {
    setCart([]);
    setPromo(null);
    setPromoError('');
  }, [setCart]);

  const applyPromo = useCallback(
    async (code: string) => {
      const trimmed = code.trim();
      if (!trimmed) {
        setPromo(null);
        setPromoError('Введіть промокод');
        return;
      }
      try {
        setPromo(await api.checkPromo(trimmed, orderItems, user?.phone ?? ''));
        setPromoError('');
      } catch (error) {
        setPromo(null);
        setPromoError(error instanceof ApiError ? error.message : 'Не вдалося перевірити промокод.');
      }
    },
    [orderItems, user?.phone],
  );

  // Знижка залежить від складу кошика, тому після кожної зміни перераховуємо її на сервері.
  const appliedCode = promo?.code;
  useEffect(() => {
    if (!appliedCode) return;
    if (!orderItems.length) {
      setPromo(null);
      return;
    }
    applyPromo(appliedCode);
    // Запускаємо лише при зміні складу кошика.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [orderItems]);

  const clearPromoMessage = useCallback(() => setPromoError(''), []);

  // Запрошення друзів: особистий код і бонуси за друзів, чиї замовлення вже виконано.
  const [referral, setReferral] = useState<Referral | null>(null);
  const referralOn = config?.referral.enabled === true;
  const refreshReferral = useCallback(async () => {
    if (!user?.phone || !referralOn) {
      setReferral(null);
      return;
    }
    try {
      setReferral(await api.referral(user.phone));
    } catch {
      // Немає зв'язку — лишаємо те, що вже показано.
    }
  }, [user?.phone, referralOn]);
  useEffect(() => {
    refreshReferral();
  }, [refreshReferral]);

  // Бонус сам підставляється в кошик один раз; якщо клієнт його прибере, не повертаємо.
  const offeredBonuses = useRef(new Set<string>());
  const bonus = referral?.bonuses[0]?.code;
  // Перевіряємо тихо: якщо зараз не підходить (наприклад, мінімальна сума), спробуємо після зміни кошика.
  useEffect(() => {
    if (!bonus || promo || !orderItems.length || offeredBonuses.current.has(bonus)) return;
    let current = true;
    api
      .checkPromo(bonus, orderItems, user?.phone ?? '')
      .then((checked) => {
        if (!current) return;
        offeredBonuses.current.add(bonus);
        setPromo(checked);
        setPromoError('');
      })
      .catch(() => undefined);
    return () => {
      current = false;
    };
  }, [bonus, promo, orderItems, user?.phone]);

  const toggleFavorite = useCallback(
    (id: number) => setFavorites((current) => (current.includes(id) ? current.filter((x) => x !== id) : [...current, id])),
    [setFavorites],
  );

  const refreshOrders = useCallback(async () => {
    const results = await Promise.all(savedOrders.map((order) => api.order(order.id, order.key).catch(() => null)));
    const fresh = results.filter((order) => order !== null);
    for (const order of fresh) {
      const before = statuses.current[order.id];
      const note = before && before !== order.status.code ? STATUS_NOTES[order.status.code]?.(order) : null;
      if (note) {
        const [title, text] = note;
        const id = `order-${order.id}-${order.status.code}`;
        setNotes((current) => (current.some((x) => x.id === id) ? current : [{ id, title, text, date: new Date().toISOString() }, ...current]));
      }
      statuses.current[order.id] = order.status.code;
    }
    AsyncStorage.setItem(STORAGE.statuses, JSON.stringify(statuses.current)).catch(() => undefined);
    setOrderDetails((current) => ({ ...current, ...Object.fromEntries(fresh.map((order) => [order.id, order])) }));
    setOrdersChecked(true);
  }, [savedOrders, setNotes]);

  useEffect(() => {
    if (!restored || !savedOrders.length) return;
    refreshOrders();
    const timer = setInterval(refreshOrders, ORDER_POLL_MS);
    return () => clearInterval(timer);
  }, [restored, savedOrders, refreshOrders]);

  const rememberOrder = useCallback(
    (order: Order, placed: CartLine[]) => {
      statuses.current[order.id] = order.status.code;
      setOrderDetails((current) => ({ ...current, [order.id]: order }));
      setSavedOrders((current) => [{ id: order.id, key: order.key, lines: placed }, ...current]);
    },
    [setSavedOrders],
  );

  const repeatOrder = useCallback(
    (id: number) => savedOrders.find((order) => order.id === id)?.lines.forEach(addToCart),
    [savedOrders, addToCart],
  );

  const signIn = useCallback(
    (next: User) => {
      setUser(next);
      setAccounts((current) => ({ ...current, [phoneDigits(next.phone)]: next }));
    },
    [setUser, setAccounts],
  );

  const updateProfile = useCallback(
    (next: User) => {
      setAccounts((current) => {
        const { [phoneDigits(user?.phone ?? '')]: _previous, ...rest } = current;
        return { ...rest, [phoneDigits(next.phone)]: next };
      });
      setUser(next);
    },
    [user?.phone, setAccounts, setUser],
  );

  const findAccount = useCallback((phone: string): User | null => accounts[phoneDigits(phone)] ?? null, [accounts]);

  const signOut = useCallback(() => setUser(null), [setUser]);

  const deleteAccount = useCallback(() => {
    if (user) {
      setAccounts((current) => {
        const { [phoneDigits(user.phone)]: _removed, ...rest } = current;
        return rest;
      });
    }
    setUser(null);
    setSavedOrders([]);
    setOrderDetails({});
    setNotes([]);
    statuses.current = {};
  }, [user, setAccounts, setUser, setSavedOrders, setNotes]);

  const allNotes = useMemo(
    () =>
      [
        ...notes,
        ...news.map((item) => ({ id: `news-${item.id}`, title: item.title, text: item.text, date: item.date })),
      ].sort((a, b) => b.date.localeCompare(a.date)),
    [notes, news],
  );

  const unread = useMemo(() => allNotes.filter((note) => !readIds.includes(note.id)).length, [allNotes, readIds]);

  const markRead = useCallback(
    (id: string) => setReadIds((current) => (current.includes(id) ? current : [...current, id])),
    [setReadIds],
  );

  const orders = useMemo(
    () => savedOrders.flatMap((order) => (orderDetails[order.id] ? [orderDetails[order.id]] : [])),
    [savedOrders, orderDetails],
  );

  return {
    catalog,
    config,
    banners,
    loadError,
    reload,
    productById,
    cart,
    lines,
    subtotal,
    orderItems,
    addToCart,
    setLineQuantity,
    setLineAddon,
    removeLine,
    clearCart,
    promo,
    promoError,
    applyPromo,
    clearPromoMessage,
    referral,
    refreshReferral,
    favorites,
    toggleFavorite,
    orders,
    refreshOrders,
    // Сервер може не знайти старі замовлення, тому чекаємо лише першу перевірку, а не всі записи.
    ordersLoading: savedOrders.length > 0 && !ordersChecked,
    rememberOrder,
    repeatOrder,
    user,
    signIn,
    updateProfile,
    signOut,
    deleteAccount,
    findAccount,
    notes: allNotes,
    unread,
    markRead,
    readIds,
  };
}

export type Shop = ReturnType<typeof useShopState>;

const ShopContext = createContext<Shop | null>(null);

export function ShopProvider({ children }: { children: ReactNode }) {
  const shop = useShopState();
  return <ShopContext.Provider value={shop}>{children}</ShopContext.Provider>;
}

export function useShop(): Shop {
  const shop = useContext(ShopContext);
  if (!shop) throw new Error('useShop outside ShopProvider');
  return shop;
}
