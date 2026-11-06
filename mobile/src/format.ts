function number(value: number): string {
  const [whole, fraction] = (Math.round(value * 100) / 100).toString().split('.');
  const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
  return fraction ? `${grouped},${fraction}` : grouped;
}

export const money = (value: number) => `${number(value)} ₴`;

export const unitName = (unit: 'kg' | 'pcs') => (unit === 'kg' ? 'кг' : 'шт');

export const quantity = (value: number, unit: 'kg' | 'pcs') => `${number(value)} ${unitName(unit)}`;

export const unitPrice = (price: number, unit: 'kg' | 'pcs') => `${number(price)} ₴ / ${unitName(unit)}`;

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
