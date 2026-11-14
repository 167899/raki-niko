/**
 * Вхід, реєстрація й профіль — як LoginScreen і ProfileScreen застосунку.
 * Акаунт зберігається в цьому браузері (ім'я, телефон, email) і підставляється в оформлення замовлення.
 */
import { fullPhone, validEmail, validPhone } from '@app/format';
import { api } from './api';
import { samePhone, store } from './store';
import { telegramButton } from './telegram';
import { esc, icon, toast } from './ui';

const PHONE_ERROR = 'Вкажіть український номер із 10 цифр';

function fieldsOf(form: HTMLFormElement) {
  const input = (name: string) => form.elements.namedItem(name) as HTMLInputElement | null;
  const setError = (name: string, text: string) => {
    const box = form.querySelector<HTMLElement>(`[data-error="${name}"]`);
    if (box) box.textContent = text;
    input(name)?.setAttribute('aria-invalid', String(!!text));
  };
  form.addEventListener('input', (event) => setError((event.target as HTMLInputElement).name, ''));
  return { input, value: (name: string) => input(name)?.value.trim() ?? '', setError };
}

/** Після входу — туди, звідки прийшли (наприклад, з оформлення), інакше на головну. */
function goBack() {
  const from = document.referrer && new URL(document.referrer).origin === location.origin ? document.referrer : '';
  const login = window.RAKY.urls.login;
  location.href = from && !from.startsWith(login) && !from.startsWith(window.RAKY.urls.profile) ? from : window.RAKY.urls.home;
}

export function initLogin() {
  const form = document.querySelector<HTMLFormElement>('[data-login]');
  if (!form) return;
  if (store.user()) {
    location.replace(window.RAKY.urls.profile);
    return;
  }
  const { value, setError } = fieldsOf(form);
  let registering = false;
  const part = (selector: string) => form.querySelector<HTMLElement>(selector);

  const paint = () => {
    part('[data-login-title]')!.textContent = registering ? 'Реєстрація' : 'Вхід';
    part('[data-login-lead]')!.textContent = registering ? 'Створіть акаунт, щоб бачити замовлення й отримувати знижки.' : 'Увійдіть у свій акаунт.';
    part('[data-login-name]')!.hidden = !registering;
    part('[data-login-phone-label]')!.textContent = registering ? 'Телефон *' : 'Телефон';
    part('[data-login-submit]')!.textContent = registering ? 'Зареєструватися' : 'Увійти';
    part('[data-login-switch-text]')!.textContent = registering ? 'Вже є акаунт?' : 'Немає акаунта?';
    part('[data-login-switch]')!.textContent = registering ? 'Увійти' : 'Зареєструватися';
    document.title = document.title.replace(/^(Вхід|Реєстрація)/, registering ? 'Реєстрація' : 'Вхід');
  };

  part('[data-login-switch]')?.addEventListener('click', () => {
    registering = !registering;
    setError('name', '');
    setError('phone', '');
    paint();
  });
  const tg = form.querySelector<HTMLElement>('[data-tg-login]');
  if (tg) {
    telegramButton(tg, 'Увійти через Telegram', ({ name, phone }) => {
      // Акаунт із цим номером на пристрої вже був — беремо його ім'я й email.
      const known = store.findAccount(phone);
      store.setUser({ name: known?.name || name, phone, email: known?.email ?? '' });
      toast('Номер підтверджено');
      setTimeout(goBack, 600);
    });
  }
  form.querySelectorAll<HTMLButtonElement>('[data-social]').forEach((button) =>
    button.addEventListener('click', () => toast(`Вхід через ${button.dataset.social} буде доступний незабаром`)),
  );

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    const phone = value('phone');
    if (registering) {
      const badName = !value('name');
      const badPhone = !validPhone(phone);
      setError('name', badName ? "Вкажіть ім'я" : '');
      setError('phone', badPhone ? PHONE_ERROR : '');
      if (badName || badPhone) return;
      if (store.findAccount(phone)) {
        setError('phone', 'Цей номер уже зареєстровано. Увійдіть');
        return;
      }
      store.setUser({ name: value('name'), phone, email: '' });
      toast('Акаунт створено');
    } else {
      if (!validPhone(phone)) {
        setError('phone', PHONE_ERROR);
        return;
      }
      const account = store.findAccount(phone);
      if (!account) {
        setError('phone', 'Акаунт із цим номером не знайдено. Зареєструйтеся');
        return;
      }
      store.setUser(account);
      toast('Ви увійшли');
    }
    setTimeout(goBack, 600);
  });
}

export function initProfile() {
  const form = document.querySelector<HTMLFormElement>('[data-profile]');
  if (!form) return;
  const user = store.user();
  if (!user) {
    location.replace(window.RAKY.urls.login);
    return;
  }
  const { input, value, setError } = fieldsOf(form);

  // Підтвердження номера: або позначка, або кнопка Telegram.
  const verify = form.querySelector<HTMLElement>('[data-verify]');
  const paintVerify = () => {
    if (!verify) return;
    if (store.verified()) {
      verify.innerHTML = `<p class="ok">${icon('brand-telegram', 16)} Номер підтверджено в Telegram: замовлення й улюблене з сайту та застосунку синхронізуються.</p>`;
      return;
    }
    verify.innerHTML = '<p class="muted">Підтвердіть номер, щоб бачити тут і замовлення із застосунку.</p><div data-tg-verify></div>';
    telegramButton(verify.querySelector<HTMLElement>('[data-tg-verify]')!, 'Підтвердити через Telegram', ({ phone }) => {
      const current = store.user();
      if (current) store.setUser({ ...current, phone });
      input('phone')!.value = fullPhone(phone);
      toast('Номер підтверджено');
      paintVerify();
    });
  };
  paintVerify();

  input('name')!.value = user.name;
  input('phone')!.value = fullPhone(user.phone);
  input('email')!.value = user.email;

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    const errors = {
      name: value('name') ? '' : "Вкажіть ім'я",
      phone: validPhone(value('phone')) ? '' : PHONE_ERROR,
      email: validEmail(value('email')) ? '' : 'Перевірте email',
    };
    Object.entries(errors).forEach(([name, text]) => setError(name, text));
    if (errors.name || errors.phone || errors.email) return;
    const session = store.session();
    // Інший номер уже не підтверджений: синхронізацію вимикаємо, доки його не підтвердять.
    if (session && !samePhone(session.phone, value('phone'))) {
      api.logout(session.session).catch(() => undefined);
      store.setSession(null);
    }
    store.setUser({ name: value('name'), phone: value('phone'), email: value('email') });
    toast('Збережено');
    paintVerify();
  });

  const leave = (action: () => void, message: string) => {
    const session = store.session();
    if (session) api.logout(session.session).catch(() => undefined);
    action();
    toast(message);
    setTimeout(() => (location.href = window.RAKY.urls.home), 600);
  };
  form.querySelector('[data-signout]')?.addEventListener('click', () => leave(() => store.signOut(), 'Ви вийшли з акаунта'));
  const box = form.querySelector<HTMLElement>('[data-delete-box]');
  const confirm = form.querySelector<HTMLElement>('[data-delete-confirm]');
  form.querySelector('[data-delete]')?.addEventListener('click', () => {
    box!.hidden = true;
    confirm!.hidden = false;
  });
  form.querySelector('[data-delete-cancel]')?.addEventListener('click', () => {
    box!.hidden = false;
    confirm!.hidden = true;
  });
  form.querySelector('[data-delete-yes]')?.addEventListener('click', () => leave(() => store.deleteAccount(), 'Акаунт видалено'));
}
