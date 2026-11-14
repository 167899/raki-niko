/**
 * Промокод і бонус за запрошеного друга. Знижку завжди рахує сервер (/promo-check),
 * тому після кожної зміни кошика її перевіряємо знову — як у застосунку.
 */
import type { OrderItem, Referral } from '@app/api';
import { api, ApiError } from './api';
import { store } from './store';

export type PromoState = { promo: { code: string; discount: number } | null; error: string; isBonus: boolean };

let referral: Referral | null = null;

async function loadReferral(): Promise<Referral | null> {
  const phone = store.user()?.phone;
  if (!phone || !window.RAKY.referral.enabled) return null;
  try {
    referral = await api.referral(phone);
  } catch {
    // Немає зв'язку — бонус просто не підставиться.
  }
  return referral;
}

const bonusCodes = () => new Set(referral?.bonuses.map((bonus) => bonus.code.toUpperCase()) ?? []);

/** Перевіряє збережений промокод; якщо його немає — пробує тихо підставити бонус за друга. */
export async function refreshPromo(items: OrderItem[], firstTime = false): Promise<PromoState> {
  const phone = store.user()?.phone ?? '';
  if (firstTime) await loadReferral();
  if (!items.length) return { promo: null, error: '', isBonus: false };
  const code = store.promo();
  if (code) return applyPromo(code, items);
  const bonus = referral?.bonuses[0]?.code;
  if (bonus && !store.wasOffered(bonus)) {
    try {
      const checked = await api.checkPromo(bonus, items, phone);
      store.markOffered(bonus);
      store.setPromo(checked.code);
      return { promo: checked, error: '', isBonus: true };
    } catch {
      // Зараз не підходить (наприклад, мінімальна сума): спробуємо після зміни кошика.
    }
  }
  return { promo: null, error: '', isBonus: false };
}

export async function applyPromo(code: string, items: OrderItem[]): Promise<PromoState> {
  const trimmed = code.trim();
  if (!trimmed) {
    store.setPromo(null);
    return { promo: null, error: 'Введіть промокод', isBonus: false };
  }
  try {
    const promo = await api.checkPromo(trimmed, items, store.user()?.phone ?? '');
    store.setPromo(promo.code);
    return { promo, error: '', isBonus: bonusCodes().has(promo.code.toUpperCase()) };
  } catch (error) {
    store.setPromo(null);
    return { promo: null, error: error instanceof ApiError ? error.message : 'Не вдалося перевірити промокод.', isBonus: false };
  }
}
