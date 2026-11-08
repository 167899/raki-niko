function number(value: number): string {
  const [whole, fraction] = (Math.round(value * 100) / 100).toString().split('.');
  const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
  return fraction ? `${grouped},${fraction}` : grouped;
}

export const money = (value: number) => `${number(value)} ₴`;

/** Одиниці, що приходять із каталогу. Кількість у кошику рахується в них. */
export type Unit = 'g' | 'kg' | '100g' | 'l' | 'ml' | 'por' | 'pcs';

const UNIT_NAMES: Record<Unit, string> = { g: 'г', kg: 'кг', '100g': '100 г', l: 'л', ml: 'мл', por: 'порція', pcs: 'шт' };

export const unitName = (unit: Unit) => UNIT_NAMES[unit] ?? 'шт';

/** Вагові одиниці: точну вагу й суму менеджер підтверджує перед оплатою. */
export const isWeighed = (unit: Unit) => unit === 'g' || unit === 'kg' || unit === '100g';

/** Скільки кілограмів в одиниці, для підказки «приблизно N шт»; 0 — не вагова. */
export const kgPerUnit = (unit: Unit) => ({ g: 0.001, kg: 1, '100g': 0.1 } as Partial<Record<Unit, number>>)[unit] ?? 0;

/** «300 г» для трьох по 100 г, «2 порц.» для порцій. */
export function quantity(value: number, unit: Unit): string {
  if (unit === '100g') return `${number(value * 100)} г`;
  if (unit === 'por') return `${number(value)} порц.`;
  return `${number(value)} ${unitName(unit)}`;
}

export const unitPrice = (price: number, unit: Unit) => `${number(price)} ₴ / ${unitName(unit)}`;

export const capitalize = (text: string) => text.charAt(0).toUpperCase() + text.slice(1);

/** Останні 9 цифр: один номер у будь-якому записі дає той самий ключ. */
export const phoneDigits = (phone: string) => phone.replace(/\D/g, '').slice(-9);

export const prettyPhone = (phone: string) =>
  phone.replace(/^(\+380)(\d\d)(\d{3})(\d\d)(\d\d)$/, '$1 $2 $3 $4 $5');

export const fullPhone = (phone: string) => prettyPhone(`+380${phoneDigits(phone)}`);

export const validPhone = (phone: string) => /^(\+?38)?0\d{9}$/.test(phone.replace(/[\s()-]/g, ''));

export const validEmail = (email: string) => !email.trim() || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim());

const MONTHS = ['січня', 'лютого', 'березня', 'квітня', 'травня', 'червня', 'липня', 'серпня', 'вересня', 'жовтня', 'листопада', 'грудня'];

const dayKey = (date: Date) => `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`;

/** «2026-11-09 14:30» → «9 листопада, 14:30». Рік показуємо, лише якщо він не поточний. */
export function readyLabel(value: string, now = new Date()): string {
  const [day, time] = value.split(' ');
  const [year, month, date] = day.split('-').map(Number);
  return `${date} ${MONTHS[month - 1]}${year === now.getFullYear() ? '' : ` ${year}`}, ${time}`;
}

export const monthDay = (date: Date) => `${date.getDate()} ${MONTHS[date.getMonth()]}`;

/** «Сьогодні», «Вчора» або «2 листопада». */
export function dayLabel(iso: string | null, now = new Date()): string {
  if (!iso) return '';
  const date = new Date(iso);
  if (dayKey(date) === dayKey(now)) return 'Сьогодні';
  const yesterday = new Date(now);
  yesterday.setDate(now.getDate() - 1);
  if (dayKey(date) === dayKey(yesterday)) return 'Вчора';
  return `${date.getDate()} ${MONTHS[date.getMonth()]}`;
}

export function dateTimeLabel(iso: string | null, now = new Date()): string {
  if (!iso) return '';
  const date = new Date(iso);
  const time = `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
  return `${dayLabel(iso, now)}, ${time}`;
}
