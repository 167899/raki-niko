/**
 * Вхід через Telegram: відкриваємо бота магазину, клієнт ділиться номером, сайт отримує сесію.
 * Далі «Мої замовлення» показують усі замовлення з цим номером — і з сайту, і з застосунку.
 */
import { api, ApiError } from './api';
import { store } from './store';
import { esc, icon } from './ui';

const POLL_MS = 2000;

type Done = (result: { name: string; phone: string }) => void;

/**
 * Малює в box кнопку «Увійти через Telegram» і веде весь вхід.
 * Якщо щось не вийшло, клієнт просто лишається без синхронізації, як раніше.
 */
export function telegramButton(box: HTMLElement, label: string, onDone: Done) {
  let timer: ReturnType<typeof setTimeout> | undefined;
  let token = '';

  const idle = (error = '') => {
    clearTimeout(timer);
    token = '';
    box.innerHTML = `
      <button class="auth__social auth__social--tg" type="button" data-tg-start>${icon('brand-telegram', 18)}${esc(label)}</button>
      ${error ? `<p class="field__error" role="alert">${esc(error)}</p>` : ''}`;
  };

  const waiting = (url: string) => {
    box.innerHTML = `
      <div class="tg-wait" role="status">
        <span class="spinner" aria-hidden="true"></span>
        <p><strong>Підтвердіть номер у Telegram</strong></p>
        <p class="muted">У боті Raki Niko натисніть «Поділитися номером». Ця сторінка оновиться сама.</p>
        <a class="btn btn--sm" href="${esc(url)}" target="_blank" rel="noopener">${icon('brand-telegram', 16)} Відкрити Telegram</a>
        <button type="button" class="link" data-tg-cancel>Скасувати</button>
      </div>`;
  };

  const poll = async () => {
    const current = token;
    try {
      const status = await api.authStatus(current);
      if (current !== token) return;
      if (status.status === 'confirmed') {
        store.setSession({ session: status.session, phone: status.phone, mergeFavorites: true });
        token = '';
        onDone({ name: status.name, phone: status.phone });
        return;
      }
    } catch (error) {
      if (current !== token) return;
      if (error instanceof ApiError && error.status === 404) {
        idle(error.message);
        return;
      }
      // Зв'язок пропав на мить — пробуємо далі.
    }
    timer = setTimeout(poll, POLL_MS);
  };

  box.addEventListener('click', async (event) => {
    const target = event.target as Element;
    if (target.closest('[data-tg-cancel]')) {
      idle();
      return;
    }
    if (!target.closest('[data-tg-start]')) return;
    // Вкладку відкриваємо одразу, у самому натисканні: інакше браузер вважатиме її спливаючим вікном.
    const tab = window.open('', '_blank');
    try {
      const start = await api.authStart();
      token = start.token;
      if (tab) tab.location.href = start.url;
      waiting(start.url);
      timer = setTimeout(poll, POLL_MS);
    } catch (error) {
      tab?.close();
      idle(error instanceof ApiError ? error.message : 'Не вдалося почати вхід. Спробуйте ще раз.');
    }
  });

  idle();
}
