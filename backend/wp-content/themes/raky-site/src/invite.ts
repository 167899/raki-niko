/** Запросити друзів: особистий код за номером телефону, той самий, що в застосунку. */
import type { Referral } from '@app/api';
import { fullPhone, validPhone } from '@app/format';
import { api, ApiError } from './api';
import { store } from './store';
import { copy, esc, loadingHtml, toast } from './ui';

export function initInvite() {
  const root = document.querySelector<HTMLElement>('[data-invite]');
  if (!root) return;

  const askPhone = (error = '') => {
    root.innerHTML = `
      <form class="panel invite-form" data-phone>
        <label class="field"><span class="field__label">Ваш телефон</span><input name="phone" type="tel" autocomplete="tel" placeholder="050 123 45 67" required></label>
        ${error ? `<p class="field__error">${esc(error)}</p>` : ''}
        <button class="btn" type="submit">Отримати код</button>
        <p class="muted">Код прив'язаний до номера: той самий номер у застосунку покаже той самий код.</p>
      </form>`;
  };

  const show = (referral: Referral) => {
    const code = referral.code ?? '';
    const message = `Тримай −${referral.percent}% на перше замовлення в Raki Niko. Введи мій код ${code} у кошику. Раки в Миколаєві: rakiniko.com.ua`;
    root.innerHTML = `
      <h2 class="heading">Ваш код</h2>
      <div class="code-box">${esc(code)}</div>
      <div class="actions">
        <button class="btn" type="button" data-share>Поділитися</button>
        <button class="btn btn--outline" type="button" data-copy="${esc(code)}">Копіювати</button>
      </div>
      ${
        referral.bonuses.length
          ? `<div class="bonus"><strong>У вас ${referral.bonuses.length > 1 ? `${referral.bonuses.length} бонуси` : 'бонус'} −${referral.bonuses[0].percent}%</strong><span>Знижка сама підставиться в кошику на наступне замовлення.</span></div>`
          : ''
      }
      ${referral.friends > 0 ? `<p class="muted">Друзів, які вже замовили за вашим кодом: ${referral.friends}.</p>` : ''}
      <p class="muted">Номер: ${esc(fullPhone(store.user()?.phone ?? ''))}. <button type="button" class="link" data-change>Змінити</button></p>`;
    root.querySelector('[data-share]')?.addEventListener('click', async () => {
      if (navigator.share) await navigator.share({ text: message }).catch(() => undefined);
      else copy(message);
    });
    root.querySelector('[data-change]')?.addEventListener('click', () => askPhone());
  };

  const load = async (phone: string) => {
    root.innerHTML = loadingHtml;
    try {
      show(await api.referral(phone));
    } catch (error) {
      askPhone(error instanceof ApiError ? error.message : 'Не вдалося отримати код.');
    }
  };

  root.addEventListener('submit', (event) => {
    event.preventDefault();
    const phone = (root.querySelector<HTMLInputElement>('[name="phone"]')?.value ?? '').replace(/[\s()-]/g, '');
    if (!validPhone(phone)) {
      askPhone('Вкажіть номер із 10 цифр');
      return;
    }
    const user = store.user();
    store.setUser({ name: user?.name ?? '', email: user?.email ?? '', phone });
    toast('Номер збережено');
    load(phone);
  });

  const user = store.user();
  if (user?.phone) load(user.phone);
  else askPhone();
}
