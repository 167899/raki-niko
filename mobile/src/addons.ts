/**
 * «Додатково» до товару: ціна з урахуванням штук «за замовчуванням».
 * Перші default штук коштують default_price (напр. перший набір паличок безкоштовно), наступні — price.
 * Той самий розрахунок на сервері: raky_addon_cost() у плагіні.
 */
import type { Addon } from './api';
import { money } from './format';

/** Ціна штуки в межах «за замовчуванням»; без окремої ціни — звичайна. */
export const addonFirstPrice = (addon: Addon) => addon.default_price ?? addon.price;

export function addonCost(addon: Addon, count: number): number {
  const first = Math.min(count, addon.default ?? 0);
  return first * addonFirstPrice(addon) + (count - first) * addon.price;
}

/** Підпис кнопки: «Рукавички +10 ₴» або «Набір (1 шт. безкоштовно, далі +15 ₴)». */
export function addonLabel(addon: Addon): string {
  const included = addon.default ?? 0;
  if (included > 0 && addonFirstPrice(addon) !== addon.price) {
    const first = addonFirstPrice(addon) > 0 ? money(addonFirstPrice(addon)) : 'безкоштовно';
    return `${addon.name} (${included} шт. ${first}, далі +${money(addon.price)})`;
  }
  return addon.price > 0 ? `${addon.name} +${money(addon.price)}` : addon.name;
}

/** Рядок розкладу ціни: «Набір × 2 (1 шт. безкоштовно)». */
export function addonLineName(addon: Addon, count: number): string {
  const included = Math.min(count, addon.default ?? 0);
  if (included > 0 && addonFirstPrice(addon) !== addon.price) {
    const first = addonFirstPrice(addon) > 0 ? `по ${money(addonFirstPrice(addon))}` : 'безкоштовно';
    return `${addon.name} × ${count} (${included} шт. ${first})`;
  }
  return `${addon.name} × ${count}`;
}

/** Сума для розкладу ціни: 0 показуємо словом. */
export const addonSum = (sum: number) => (sum > 0 ? money(sum) : 'безкоштовно');
