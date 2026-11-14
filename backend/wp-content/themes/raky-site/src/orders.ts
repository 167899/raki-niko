/** Мої замовлення (як OrdersScreen застосунку): статус, реквізити після підтвердження, повтор. */
import type { Order, OrderStatusCode } from '@app/api';
import { linesFromOrder } from '@app/orderLines';
import { dateTimeLabel, fullPhone, money, quantity, readyLabel } from '@app/format';
import { api, ApiError } from './api';
import { store } from './store';
import { esc, icon, toast } from './ui';

const POLL_MS = 30_000;
const STEPS = 5;
const STATUS: Record<OrderStatusCode, { label: string; tone: string; step: number }> = {
  awaiting_confirmation: { label: 'Очікує підтвердження', tone: 'red', step: 1 },
  awaiting_payment: { label: 'Очікує оплату', tone: 'red', step: 2 },
  preparing: { label: 'Готуємо замовлення', tone: 'red', step: 3 },
  delivering: { label: 'Передано в доставку', tone: 'red', step: 4 },
  completed: { label: 'Виконано', tone: 'green', step: 0 },
  cancelled: { label: 'Скасовано', tone: 'muted', step: 0 },
  unknown: { label: 'Статус уточнюється', tone: 'muted', step: 0 },
};

function orderText(order: Order): string {
  const items = order.items.map((item) =>
    [item.name, item.flavor.toLowerCase(), quantity(item.quantity, item.unit), ...(item.addons ?? []).map((addon) => `${addon.name.toLowerCase()} × ${addon.quantity}`)]
      .filter(Boolean)
      .join(', '),
  );
  if (order.delivery_type === 'pickup') items.push(`самовивіз, ${order.address}`);
  else if (order.address) items.push(`доставка: ${order.address}`);
  return items.join('; ');
}

function cardHtml(order: Order, open: boolean, repeatable: boolean): string {
  const status = STATUS[order.status.code] ?? STATUS.unknown;
  const feePending = order.delivery_type === 'delivery' && order.shipping === 0;
  const checks = [order.has_weighted && 'вагу', feePending && 'вартість доставки'].filter(Boolean).join(' й ');
  return `
    <article class="order" data-order="${order.id}">
      <div class="order__head"><strong>№ ${order.id}</strong><strong>${esc(money(order.total))}</strong></div>
      <p class="muted">${esc(dateTimeLabel(order.created_at))}</p>
      <p class="order__status order__status--${status.tone}">${esc(status.label)}</p>
      ${status.step > 0 ? `<div class="progress" aria-hidden="true">${Array.from({ length: STEPS }, (_, index) => `<span${index < status.step ? ' class="on"' : ''}></span>`).join('')}</div>` : ''}
      <p class="muted">${esc(orderText(order))}</p>
      <p class="muted">Оплата: ${order.payment_method === 'cash' ? 'готівкою при отриманні' : 'за реквізитами'}</p>
      ${order.ready_at ? `<p class="muted">Готуємо на: ${esc(readyLabel(order.ready_at))}</p>` : ''}
      ${order.comment ? `<p class="muted">Коментар: ${esc(order.comment)}</p>` : ''}
      ${order.status.code === 'awaiting_confirmation' && checks ? `<div class="warn">Сума попередня: менеджер уточнить ${checks} перед оплатою.</div>` : ''}
      ${
        order.requisites
          ? `${open ? `<pre class="requisites">${esc(order.requisites)}\nПризначення: замовлення № ${order.id}</pre>` : ''}
             <button type="button" class="btn btn--sm" data-requisites="${order.id}">${open ? 'Сховати реквізити' : 'Показати реквізити'}</button>`
          : ''
      }
      ${order.status.code === 'completed' && repeatable ? `<button type="button" class="btn btn--sm btn--outline" data-repeat="${order.id}">Повторити замовлення</button>` : ''}
    </article>`;
}

export function initOrders() {
  const root = document.querySelector<HTMLElement>('[data-orders]');
  if (!root) return;
  const saved = store.orders();
  const opened = new Set<number>();
  let orders: Order[] = [];

  const fresh = new URLSearchParams(window.location.search).get('new');
  if (fresh) {
    toast('Замовлення надіслано');
    history.replaceState(null, '', window.location.pathname + window.location.search.replace(/[?&]new=\d+/, '').replace(/^&/, '?'));
  }

  const render = () => {
    root.innerHTML = orders.length
      ? orders.map((order) => cardHtml(order, opened.has(order.id), repeatLines(order).length > 0)).join('')
      : `<div class="empty">${icon('receipt', 40)}<p>Тут будуть ваші замовлення</p><a class="btn btn--sm" href="${esc(window.RAKY.urls.home)}">До меню</a></div>`;
  };

  // Склад кошика: збережений у цьому браузері, а для замовлень із застосунку — з відповіді сервера.
  const repeatLines = (order: Order) => {
    const local = saved.find((item) => item.id === order.id)?.lines ?? [];
    return local.length ? local : linesFromOrder(order);
  };
  const hint = document.querySelector<HTMLElement>('[data-orders-hint]');
  const paintHint = () => {
    if (!hint) return;
    const session = store.session();
    hint.innerHTML = session
      ? `Замовлення з сайту й застосунку за номером ${esc(fullPhone(session.phone))}.`
      : `Тут видно замовлення, оформлені на сайті в цьому браузері. <a href="${esc(window.RAKY.urls.login)}">Увійдіть через Telegram</a>, щоб бачити й замовлення із застосунку.`;
  };
  paintHint();

  const refresh = async () => {
    const session = store.session();
    let synced: Order[] = [];
    if (session) {
      try {
        synced = (await api.myOrders(session.session)).orders;
      } catch (error) {
        // Сесію відкликали чи вона застаріла — працюємо як раніше, без синхронізації.
        if (error instanceof ApiError && error.status === 401) {
          store.setSession(null);
          paintHint();
        }
      }
    }
    const known = new Set(synced.map((order) => order.id));
    const local = saved.filter((order) => !known.has(order.id));
    const results = await Promise.all(local.map((order) => api.order(order.id, order.key).catch(() => null)));
    // Якщо зв'язку немає, лишаємо те, що вже показано.
    const found = [...synced, ...results.filter((order): order is Order => order !== null)].sort((a, b) => b.id - a.id);
    if (found.length || !orders.length) orders = found;
    render();
  };

  root.addEventListener('click', (event) => {
    const target = event.target as Element;
    const requisites = target.closest<HTMLElement>('[data-requisites]');
    if (requisites) {
      const id = Number(requisites.dataset.requisites);
      if (opened.has(id)) opened.delete(id);
      else opened.add(id);
      render();
    }
    const repeat = target.closest<HTMLElement>('[data-repeat]');
    if (repeat) {
      const order = orders.find((item) => item.id === Number(repeat.dataset.repeat));
      if (order) repeatLines(order).forEach((line) => store.addToCart(line));
      window.location.assign(window.RAKY.urls.cart);
    }
  });

  if (!saved.length && !store.session()) {
    render();
    return;
  }
  refresh();
  setInterval(() => !document.hidden && refresh(), POLL_MS);
}
