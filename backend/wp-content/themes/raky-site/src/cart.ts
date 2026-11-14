/** Кошик (як CartScreen застосунку): вага, «Додатково», промокод, сума без доставки. */
import type { Catalog } from '@app/api';
import { isWeighed, money, quantity } from '@app/format';
import { api, ApiError } from './api';
import { applyPromo, PromoState, refreshPromo } from './promo';
import { orderItemsOf, ResolvedLine, resolveLines, store, subtotalOf } from './store';
import { esc, icon, loadingHtml, productUrl, track } from './ui';

export async function initCart() {
  const root = document.querySelector<HTMLElement>('[data-cart]');
  if (!root) return;
  let catalog: Catalog;
  try {
    catalog = await api.catalog();
  } catch (error) {
    root.innerHTML = `<div class="empty"><p>${esc(error instanceof ApiError ? error.message : 'Не вдалося завантажити меню.')}</p><button class="btn btn--sm" type="button" data-retry>Спробувати ще раз</button></div>`;
    root.querySelector('[data-retry]')?.addEventListener('click', () => {
      root.innerHTML = loadingHtml;
      initCart();
    });
    return;
  }

  let lines: ResolvedLine[] = [];
  let state: PromoState = { promo: null, error: '', isBonus: false };
  let code = store.promo() ?? '';
  let checking = false;
  let version = 0;

  const recheck = async (first = false) => {
    const mine = ++version;
    const next = await refreshPromo(orderItemsOf(lines), first);
    if (mine !== version) return;
    state = next;
    if (state.promo) code = state.promo.code;
    render();
  };

  const load = (first = false) => {
    lines = resolveLines(store.cart(), catalog);
    render();
    recheck(first);
  };

  const render = () => {
    if (!lines.length) {
      root.innerHTML = `<div class="empty">${icon('shopping-cart', 40)}<p>Кошик порожній</p><a class="btn btn--sm" href="${esc(window.RAKY.urls.home)}">До меню</a></div>`;
      return;
    }
    const subtotal = subtotalOf(lines);
    const discount = state.promo?.discount ?? 0;
    const focused = document.activeElement?.getAttribute('name') === 'promo';
    root.innerHTML = `
      <div class="cart-lines">
        ${lines.map(rowHtml).join('')}
        ${lines.some((line) => isWeighed(line.product.unit)) ? '<div class="warn">Точну вагу й суму менеджер підтвердить перед оплатою.</div>' : ''}
      </div>
      <aside class="summary panel">
        <form class="promo-form" data-promo>
          <label class="field__label" for="promo">Промокод</label>
          <div class="promo-form__row">
            <input id="promo" name="promo" placeholder="Введіть промокод" autocapitalize="characters" autocomplete="off" spellcheck="false" value="${esc(code)}">
            <button class="btn btn--sm" type="submit"${checking ? ' disabled' : ''}>Застосувати</button>
          </div>
          ${state.error ? `<p class="field__error">${esc(state.error)}</p>` : ''}
          ${state.promo && !state.error ? `<p class="ok">${state.isBonus ? 'Бонус за запрошеного друга застосовано' : 'Промокод застосовано'}</p>` : ''}
        </form>
        <div class="sum"><span class="muted">Доставка</span><span class="muted">при оформленні</span></div>
        ${state.promo ? `<div class="sum ok"><span>Знижка</span><span>−${esc(money(discount))}</span></div>` : ''}
        <div class="sum sum--total"><span>Разом без доставки</span><strong>${esc(money(subtotal - discount))}</strong></div>
        <a class="btn" href="${esc(window.RAKY.urls.checkout)}" data-go>Оформити замовлення</a>
      </aside>`;
    if (focused) {
      const input = root.querySelector<HTMLInputElement>('[name="promo"]');
      input?.focus();
      input?.setSelectionRange(input.value.length, input.value.length);
    }
  };

  root.addEventListener('click', (event) => {
    const target = (event.target as Element).closest<HTMLElement>('[data-action]');
    if (!target) return;
    const line = lines[Number(target.dataset.line)];
    if (!line) return;
    const { product } = line;
    switch (target.dataset.action) {
      case 'remove':
        store.removeLine(line);
        break;
      case 'less':
        store.updateLine(line, { quantity: Math.max(product.min, Math.round((line.quantity - product.step) * 1000) / 1000) });
        break;
      case 'more':
        store.updateLine(line, { quantity: Math.round((line.quantity + product.step) * 1000) / 1000 });
        break;
      case 'addon-more':
      case 'addon-less': {
        const name = target.dataset.addon ?? '';
        const current = line.addons?.[name] ?? 0;
        const next = target.dataset.action === 'addon-more' ? current + 1 : Math.max(0, current - 1);
        store.updateLine(line, { addons: { ...line.addons, [name]: next } });
        break;
      }
    }
    load();
  });

  root.addEventListener('input', (event) => {
    const input = event.target as HTMLInputElement;
    if (input.name !== 'promo') return;
    code = input.value;
    if (state.error) {
      state = { ...state, error: '' };
      root.querySelector('[data-promo] .field__error')?.remove();
    }
  });

  root.addEventListener('submit', async (event) => {
    event.preventDefault();
    checking = true;
    version++;
    state = await applyPromo(code, orderItemsOf(lines));
    checking = false;
    if (state.promo) code = state.promo.code;
    render();
  });

  root.addEventListener('click', (event) => {
    if (!(event.target as Element).closest('[data-go]')) return;
    track(
      'begin_checkout',
      subtotalOf(lines) - (state.promo?.discount ?? 0),
      lines.map((line) => ({ id: line.productId, name: line.product.name, price: line.unitPrice, quantity: line.quantity })),
    );
  });

  // Кошик змінили в іншій вкладці.
  window.addEventListener('storage', () => load());
  load(true);
}

function rowHtml(line: ResolvedLine, index: number): string {
  const { product } = line;
  const details = [product.caliber_hint, line.flavor.toLowerCase()].filter(Boolean).join(', ');
  const url = productUrl(product.id);
  return `
    <article class="cart-row">
      <a class="cart-row__pic" href="${esc(url)}" tabindex="-1" aria-hidden="true">${product.image ? `<img src="${esc(product.image)}" alt="" width="64" height="64" loading="lazy">` : icon('flame', 24)}</a>
      <div class="cart-row__body">
        <div class="cart-row__head">
          <a class="cart-row__name" href="${esc(url)}">${esc(product.name)}</a>
          <button type="button" class="icon-btn" data-action="remove" data-line="${index}" aria-label="Видалити: ${esc(product.name)}">${icon('trash', 18)}</button>
        </div>
        ${details ? `<p class="muted">${esc(details)}</p>` : ''}
        <div class="cart-row__amount">
          <button type="button" class="stepper__btn stepper__btn--sm" data-action="less" data-line="${index}" aria-label="Менше"${line.quantity <= product.min ? ' disabled' : ''}>${icon('minus', 16)}</button>
          <span class="cart-row__qty">${esc(quantity(line.quantity, product.unit))}</span>
          <button type="button" class="stepper__btn stepper__btn--sm" data-action="more" data-line="${index}" aria-label="Більше">${icon('plus', 16)}</button>
          <strong class="cart-row__total">${esc(money(line.total))}</strong>
        </div>
        ${
          line.extras.length
            ? `<div class="cart-row__extras">${line.extras
                .map(
                  (addon) => `
              <span class="extra">
                <span>${esc(addon.name)} +${esc(money(addon.price))}</span>
                <button type="button" class="extra__btn" data-action="addon-less" data-line="${index}" data-addon="${esc(addon.name)}" aria-label="Менше: ${esc(addon.name)}">${icon('minus', 14)}</button>
                <span class="extra__count">${addon.quantity}</span>
                <button type="button" class="extra__btn" data-action="addon-more" data-line="${index}" data-addon="${esc(addon.name)}" aria-label="Більше: ${esc(addon.name)}">${icon('plus', 14)}</button>
              </span>`,
                )
                .join('')}</div>`
            : ''
        }
      </div>
    </article>`;
}
