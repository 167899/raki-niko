/**
 * Лічильник відвідувань для сторінки «Статистика» в адмінці (плагін, includes/stats.php).
 * Без cookie та особистих даних: браузер лише пам'ятає день останнього заходу й поточний сеанс.
 */
const DAY_KEY = 'raky.stats.day';
const SESSION_KEY = 'raky.stats.session';

function today(): string {
  const now = new Date();
  return `${now.getFullYear()}-${now.getMonth() + 1}-${now.getDate()}`;
}

/** Повертає true, якщо значення змінилося (новий день чи новий сеанс). */
function mark(storage: () => Storage, key: string, value: string): boolean {
  try {
    if (storage().getItem(key) === value) return false;
    storage().setItem(key, value);
  } catch {
    // Приватний режим без сховища: рахуємо як новий захід.
  }
  return true;
}

export function trackVisit(): void {
  if (!window.RAKY.track || navigator.webdriver || /bot|crawl|spider|lighthouse|headless/i.test(navigator.userAgent)) return;
  const body = {
    path: location.pathname,
    label: window.RAKY.label,
    new_visitor: mark(() => localStorage, DAY_KEY, today()),
    new_session: mark(() => sessionStorage, SESSION_KEY, '1'),
    referrer: document.referrer,
    mobile: matchMedia('(pointer: coarse)').matches,
  };
  fetch(`${window.RAKY.api}/stats/hit`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
    keepalive: true,
  }).catch(() => undefined);
}
