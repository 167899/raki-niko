/**
 * Оформлення (як CheckoutScreen застосунку). Вартість доставки за адресою, знижку й підсумок рахує сервер;
 * сайт надсилає expected_total, і якщо ціни змінилися, сервер відповідає 409 — тоді оновлюємо кошик.
 */
import type { Catalog, DeliveryQuote, PaymentMethod } from '@app/api';
import { fullPhone, money, quantity, validEmail, validPhone } from '@app/format';
import { buildReadyDates, defaultReady, joinReady, READY_HOURS, READY_MINUTES, readyProblem } from '@app/slots';
import { api, ApiError } from './api';
import { PromoState, refreshPromo } from './promo';
import { orderItemsOf, ResolvedLine, resolveLines, store, subtotalOf } from './store';
import { esc, icon, track } from './ui';

const QUOTE_DELAY_MS = 900;
const QUOTE_MIN_LENGTH = 4;

type Field = 'name' | 'phone' | 'email' | 'address' | 'ready';

export async function initCheckout() {
  const form = document.querySelector<HTMLFormElement>('[data-checkout]');
  const summary = form?.querySelector<HTMLElement>('[data-summary]');
  if (!form || !summary) return;

  let catalog: Catalog;
  try {
    catalog = await api.catalog();
  } catch (error) {
    summary.innerHTML = `<p class="field__error">${esc(error instanceof ApiError ? error.message : 'Не вдалося завантажити меню.')}</p>`;
    return;
  }

  let lines: ResolvedLine[] = resolveLines(store.cart(), catalog);
  if (!lines.length) {
    form.outerHTML = `<div class="empty">${icon('shopping-cart', 40)}<p>Кошик порожній</p><a class="btn btn--sm" href="${esc(window.RAKY.urls.home)}">До меню</a></div>`;
    return;
  }

  const field = <T extends HTMLElement = HTMLInputElement>(name: string) => form.querySelector<T>(`[name="${name}"]`)!;
  const value = (name: string) => (field(name) as HTMLInputElement).value;
  const checked = (name: string) => form.querySelector<HTMLInputElement>(`[name="${name}"]:checked`)?.value ?? '';

  // Контакти з попереднього замовлення в цьому браузері.
  const user = store.user();
  if (user) {
    field('name').value = user.name;
    field('phone').value = fullPhone(user.phone);
    field('email').value = user.email;
  }

  let promo: PromoState = { promo: null, error: '', isBonus: false };
  let quote: DeliveryQuote | null = null;
  let quoting = false;
  let latestQuote = 0;
  let failure = '';
  let submitting = false;
  let quoteTimer: ReturnType<typeof setTimeout> | undefined;

  const pickup = () => checked('delivery_type') === 'pickup';

  const fillReady = () => {
    const now = new Date();
    const fallback = defaultReady(now);
    const date = field<HTMLSelectElement>('ready_date');
    const hour = field<HTMLSelectElement>('ready_hour');
    const minute = field<HTMLSelectElement>('ready_minute');
    date.innerHTML = buildReadyDates(now).map((item) => `<option value="${item.key}">${esc(item.label)}</option>`).join('');
    hour.innerHTML = READY_HOURS.map((item) => `<option>${item}</option>`).join('');
    minute.innerHTML = READY_MINUTES.map((item) => `<option>${item}</option>`).join('');
    date.value = fallback.date;
    hour.value = fallback.hour;
    minute.value = fallback.minute;
  };
  fillReady();

  const readyAt = () =>
    checked('when') === 'scheduled' ? joinReady({ date: value('ready_date'), hour: value('ready_hour'), minute: value('ready_minute') }) : '';

  const setError = (name: Field, text = '') => {
    const box = form.querySelector<HTMLElement>(`[data-error="${name}"]`);
    if (box) box.textContent = text;
    const input = form.querySelector<HTMLElement>(`[name="${name}"]`);
    if (text) input?.setAttribute('aria-invalid', 'true');
    else input?.removeAttribute('aria-invalid');
  };

  const validate = () => {
    const found: Partial<Record<Field, string>> = {};
    if (!value('name').trim()) found.name = "Вкажіть ім'я";
    if (!validPhone(value('phone'))) found.phone = 'Вкажіть номер із 10 цифр';
    if (!validEmail(value('email'))) found.email = 'Перевірте email';
    if (!pickup() && !value('address').trim()) found.address = 'Вкажіть адресу доставки';
    if (checked('when') === 'scheduled') {
      const problem = readyProblem({ date: value('ready_date'), hour: value('ready_hour'), minute: value('ready_minute') }, new Date());
      if (problem) found.ready = problem;
    }
    (['name', 'phone', 'email', 'address', 'ready'] as Field[]).forEach((name) => setError(name, found[name]));
    return found;
  };

  const renderSummary = () => {
    const subtotal = subtotalOf(lines);
    const discount = promo.promo?.discount ?? 0;
    const fee = !pickup() && quote?.found ? quote.fee : 0;
    const feeKnown = pickup() || !!quote?.found;
    summary.innerHTML = `
      <h2 class="panel__title">Ваше замовлення</h2>
      <ul class="summary__lines">
        ${lines
          .map(
            // Назва — напівжирним, смак, вага й «Додатково» — звичайним шрифтом того ж розміру.
            (line) => `<li>
              <span class="summary__item">
                <span class="summary__name">${esc(line.product.name)}</span>
                <span class="summary__meta">${[line.flavor ? esc(line.flavor) : '', esc(quantity(line.quantity, line.product.unit))].filter(Boolean).join(' · ')}</span>
                ${line.extras.map((addon) => `<span class="summary__meta">${esc(addon.name)} × ${addon.quantity}</span>`).join('')}
              </span>
              <span class="summary__price">${esc(money(line.total))}</span></li>`,
          )
          .join('')}
      </ul>
      <a class="muted summary__edit" href="${esc(window.RAKY.urls.cart)}">Змінити кошик</a>
      ${discount > 0 ? `<div class="sum ok"><span>Знижка${promo.promo ? ` (${esc(promo.promo.code)})` : ''}</span><span>−${esc(money(discount))}</span></div>` : ''}
      ${!pickup() ? `<div class="sum"><span class="muted">Доставка</span><span class="muted">${quoting ? 'розраховуємо…' : feeKnown ? esc(money(fee)) : 'уточнить менеджер'}</span></div>` : ''}
      <div class="sum sum--total"><span>${feeKnown ? 'Разом' : 'Разом без доставки'}</span><strong>${esc(money(subtotal - discount + fee))}</strong></div>
      ${lines.some((line) => ['g', 'kg', '100g'].includes(line.product.unit)) ? '<p class="summary__note">Точну вагу й суму менеджер підтвердить перед оплатою.</p>' : ''}
      ${failure ? `<p class="field__error" role="alert">${esc(failure)}</p>` : ''}
      <button class="btn" type="submit"${submitting ? ' disabled aria-busy="true"' : ''}>${submitting ? 'Надсилаємо…' : 'Надіслати замовлення'}</button>`;
    const note = form.querySelector<HTMLElement>('[data-quote-note]');
    if (note) note.hidden = pickup() || quoting || quote?.found !== false;
  };

  const requestQuote = () => {
    const quoteId = ++latestQuote;
    const text = value('address').trim();
    clearTimeout(quoteTimer);
    quote = null;
    quoting = false;
    if (!pickup() && text.length >= QUOTE_MIN_LENGTH) {
      quoting = true;
      quoteTimer = setTimeout(async () => {
        const result = await api.quoteDelivery(text).catch((): DeliveryQuote => ({ found: false }));
        if (quoteId !== latestQuote) return;
        quote = result;
        quoting = false;
        renderSummary();
      }, QUOTE_DELAY_MS);
    }
    renderSummary();
  };

  form.addEventListener('change', (event) => {
    const target = event.target as HTMLInputElement;
    if (target.name === 'delivery_type') {
      form.querySelectorAll<HTMLElement>('[data-when]').forEach((box) => (box.hidden = box.dataset.when !== checked('delivery_type')));
      requestQuote();
    }
    if (target.name === 'when') {
      const box = form.querySelector<HTMLElement>('[data-ready]');
      if (box) box.hidden = checked('when') !== 'scheduled';
      setError('ready');
    }
    if (target.name.startsWith('ready_')) setError('ready');
    if (target.name === 'payment_method') {
      const note = form.querySelector<HTMLElement>('[data-pay-note]');
      if (note)
        note.textContent =
          checked('payment_method') === 'cash' ? 'Оплата при отриманні замовлення.' : "Реквізити з'являться в замовленні після підтвердження менеджером.";
    }
  });

  form.addEventListener('input', (event) => {
    const target = event.target as HTMLInputElement;
    if (['name', 'phone', 'email', 'address'].includes(target.name)) setError(target.name as Field);
    if (target.name === 'address') requestQuote();
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (submitting) return;
    failure = '';
    const found = validate();
    if (Object.keys(found).length) {
      renderSummary();
      form.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus();
      return;
    }
    submitting = true;
    renderSummary();
    try {
      const phone = value('phone').replace(/[\s()-]/g, '');
      const address = value('address').trim();
      // Сума має збігтися із серверною, тому перед надсиланням беремо свіжу вартість доставки.
      const latest = pickup() ? null : await api.quoteDelivery(address).catch(() => null);
      const fee = latest?.found ? latest.fee : 0;
      const discount = promo.promo?.discount ?? 0;
      const subtotal = subtotalOf(lines);
      const total = Math.round((subtotal - discount + fee) * 100) / 100;
      const order = await api.createOrder({
        name: value('name').trim(),
        phone,
        email: value('email').trim(),
        address,
        comment: (field<HTMLTextAreaElement>('comment').value ?? '').trim(),
        delivery_type: pickup() ? 'pickup' : 'delivery',
        payment_method: (checked('payment_method') || 'requisites') as PaymentMethod,
        ready_at: readyAt(),
        ...(pickup() ? { outlet_id: checked('outlet_id') } : {}),
        items: orderItemsOf(lines),
        promo: promo.promo?.code ?? '',
        expected_total: total,
      });
      store.rememberOrder({ id: order.id, key: order.key, lines: store.cart() });
      store.setUser({ name: value('name').trim(), phone, email: value('email').trim() });
      track(
        'purchase',
        order.total,
        lines.map((line) => ({ id: line.productId, name: line.product.name, price: line.unitPrice, quantity: line.quantity })),
        { transaction_id: String(order.id), shipping: order.shipping, coupon: promo.promo?.code ?? undefined },
      );
      store.clearCart();
      const next = new URL(window.RAKY.urls.orders);
      next.searchParams.set('new', String(order.id));
      window.location.assign(next.toString());
    } catch (error) {
      const apiError = error instanceof ApiError ? error : null;
      if (apiError?.status === 409) {
        // Ціни змінилися: беремо свіжий каталог і перераховуємо знижку.
        catalog = await api.catalog().catch(() => catalog);
        lines = resolveLines(store.cart(), catalog);
        promo = await refreshPromo(orderItemsOf(lines));
      }
      failure = apiError?.message ?? 'Не вдалося оформити замовлення.';
      submitting = false;
      renderSummary();
    }
  });

  renderSummary();
  promo = await refreshPromo(orderItemsOf(lines), true);
  renderSummary();
}
