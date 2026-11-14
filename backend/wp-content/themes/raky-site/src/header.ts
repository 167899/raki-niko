/** Шапка й нижня панель: меню під значком профілю, «Запитати», лічильники кошика, улюбленого й новин. */
import { fullPhone } from '@app/format';
import { store } from './store';

/** Шар поверх сторінки (меню чи «Запитати»): відкриття, закриття по тлу, хрестику й Escape. */
function layer(box: HTMLElement | null, openers: HTMLElement[], closeSelector: string, focusSelector = closeSelector) {
  if (!box) return;
  let opener: HTMLElement | null = null;
  const close = () => {
    box.hidden = true;
    openers.forEach((button) => button.setAttribute('aria-expanded', 'false'));
    document.body.classList.remove('no-scroll');
    opener?.focus();
  };
  openers.forEach((button) =>
    button.addEventListener('click', () => {
      opener = button;
      box.hidden = false;
      button.setAttribute('aria-expanded', 'true');
      document.body.classList.add('no-scroll');
      box.querySelector<HTMLElement>(focusSelector)?.focus();
    }),
  );
  box.addEventListener('click', (event) => {
    if (event.target === box || (event.target as Element).closest(closeSelector)) close();
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !box.hidden) close();
  });
}

const count = (selector: string, value: number) =>
  document.querySelectorAll<HTMLElement>(selector).forEach((badge) => {
    badge.textContent = String(value);
    badge.hidden = value === 0;
  });

export function initHeader() {
  // Фокус — на саму панель меню: так хрестик не підсвічується, ніби це головна кнопка.
  layer(document.querySelector('[data-menu]'), [...document.querySelectorAll<HTMLElement>('[data-menu-open]')], '[data-menu-close]', '.drawer__panel');
  layer(document.querySelector('[data-ask]'), [...document.querySelectorAll<HTMLElement>('[data-ask-open]')], '[data-ask-close]');

  // Трубка в шапці: список усіх телефонів під нею.
  const phones = document.querySelector<HTMLElement>('[data-phones]');
  const phonesOpen = document.querySelector<HTMLButtonElement>('[data-phones-open]');
  const togglePhones = (open: boolean) => {
    if (!phones || !phonesOpen) return;
    phones.hidden = !open;
    phonesOpen.setAttribute('aria-expanded', String(open));
  };
  phonesOpen?.addEventListener('click', () => togglePhones(!!phones?.hidden));
  document.addEventListener('click', (event) => {
    if (phones && !phones.hidden && !(event.target as Element).closest('.top__phones')) togglePhones(false);
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && phones && !phones.hidden) {
      togglePhones(false);
      phonesOpen?.focus();
    }
  });

  const paintCart = () => count('[data-cart-count]', store.cart().length);
  const paintMine = () => {
    count('[data-fav-count]', store.favorites().length);
    count('[data-news-count]', store.unreadNews());
    // У меню, як у застосунку: «Гість» веде на вхід, ім'я й телефон — у профіль.
    const user = store.user();
    const who = document.querySelector<HTMLAnchorElement>('[data-who]');
    const name = document.querySelector<HTMLElement>('[data-who-name]');
    const hint = document.querySelector<HTMLElement>('[data-who-hint]');
    if (who && name && hint) {
      who.href = user ? window.RAKY.urls.profile : window.RAKY.urls.login;
      name.textContent = user ? user.name || 'Мій акаунт' : 'Гість';
      hint.textContent = user ? fullPhone(user.phone) : 'Увійти або зареєструватися';
    }
  };
  paintCart();
  paintMine();
  window.addEventListener('raky:cart', paintCart);
  window.addEventListener('raky:mine', paintMine);
  // Змінили в іншій вкладці.
  window.addEventListener('storage', () => {
    paintCart();
    paintMine();
  });
}
