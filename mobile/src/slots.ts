import { SLOT_END_HOUR, SLOT_FIRST_HOUR, SLOT_HORIZON_DAYS, SLOT_LEAD_MINUTES, SLOT_STEP_MINUTES } from './content';
import { monthDay } from './format';

export type ReadyDate = { key: string; label: string };
export type ReadyParts = { date: string; hour: string; minute: string };

const WEEKDAYS = ['Нд', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];

const pad = (value: number) => String(value).padStart(2, '0');

const dateKey = (date: Date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;

const clock = (date: Date) => `${pad(date.getHours())}:${pad(date.getMinutes())}`;

export const READY_HOURS = Array.from({ length: SLOT_END_HOUR - SLOT_FIRST_HOUR }, (_, index) => pad(SLOT_FIRST_HOUR + index));

export const READY_MINUTES = Array.from({ length: 60 / SLOT_STEP_MINUTES }, (_, index) => pad(index * SLOT_STEP_MINUTES));

export const earliestReady = (now: Date) => new Date(now.getTime() + SLOT_LEAD_MINUTES * 60_000);

/** Усі дні від сьогодні, щоб передзамовлення можна було оформити задовго наперед. */
export function buildReadyDates(now: Date): ReadyDate[] {
  return Array.from({ length: SLOT_HORIZON_DAYS + 1 }, (_, offset) => {
    const date = new Date(now.getFullYear(), now.getMonth(), now.getDate() + offset);
    const year = date.getFullYear() === now.getFullYear() ? '' : ` ${date.getFullYear()}`;
    const label = offset === 0 ? 'Сьогодні' : offset === 1 ? 'Завтра' : `${WEEKDAYS[date.getDay()]}, ${monthDay(date)}${year}`;
    return { key: dateKey(date), label };
  });
}

/** Найближчий час, на який можна готувати. */
export function defaultReady(now: Date): ReadyParts {
  const step = SLOT_STEP_MINUTES * 60_000;
  const next = new Date(Math.ceil(earliestReady(now).getTime() / step) * step);
  if (next.getHours() >= SLOT_END_HOUR) {
    const tomorrow = new Date(next.getFullYear(), next.getMonth(), next.getDate() + 1);
    return { date: dateKey(tomorrow), hour: pad(SLOT_FIRST_HOUR), minute: '00' };
  }
  if (next.getHours() < SLOT_FIRST_HOUR) return { date: dateKey(next), hour: pad(SLOT_FIRST_HOUR), minute: '00' };
  return { date: dateKey(next), hour: pad(next.getHours()), minute: pad(next.getMinutes()) };
}

export const joinReady = ({ date, hour, minute }: ReadyParts) => `${date} ${hour}:${minute}`;

export function splitReady(value: string): ReadyParts {
  const [date, time] = value.split(' ');
  const [hour, minute] = time.split(':');
  return { date, hour, minute };
}

/** Текст помилки, якщо обраний час уже недоступний. */
export function readyProblem({ date, hour, minute }: ReadyParts, now: Date): string {
  const [year, month, day] = date.split('-').map(Number);
  const chosen = new Date(year, month - 1, day, Number(hour), Number(minute));
  const earliest = earliestReady(now);
  return chosen < earliest ? `Найближчий час: ${clock(earliest)}. Оберіть пізніший` : '';
}
