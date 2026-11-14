/** Сторінка товару: смак, «Додатково», вага й додавання в кошик (як ProductScreen застосунку). */
import type { Addon, Flavor } from '@app/api';
import type { Unit } from '@app/format';
import { kgPerUnit, money, quantity, unitPrice } from '@app/format';
import { store } from './store';
import { toast, track } from './ui';

type ProductData = {
  id: number;
  name: string;
  price: number;
  unit: Unit;
  step: number;
  min: number;
  per_kg: number;
  flavors: Flavor[];
  addons: Addon[];
  category: string;
};

export function initProduct() {
  const root = document.querySelector<HTMLElement>('[data-product]');
  if (!root) return;
  const product = JSON.parse(root.dataset.product ?? '{}') as ProductData;
  let count = product.min;
  let flavor = product.flavors[0]?.name ?? '';
  const addons: Record<string, number> = {};

  const amount = root.querySelector<HTMLElement>('[data-amount]');
  const total = root.querySelector<HTMLElement>('[data-total]');
  const price = root.querySelector<HTMLElement>('[data-unit-price]');
  const pieces = root.querySelector<HTMLElement>('[data-pieces]');
  const add = root.querySelector<HTMLButtonElement>('[data-add]');
  const after = root.querySelector<HTMLElement>('[data-after]');

  const unitCost = () => product.price + (product.flavors.find((item) => item.name === flavor)?.extra ?? 0);
  const addonsCost = () => product.addons.reduce((sum, addon) => sum + addon.price * (addons[addon.name] ?? 0), 0);

  const paint = () => {
    if (amount) amount.textContent = quantity(count, product.unit);
    if (price) price.textContent = unitPrice(unitCost(), product.unit);
    if (total) total.textContent = money(unitCost() * count + addonsCost());
    if (pieces) {
      const show = product.per_kg > 0 && kgPerUnit(product.unit) > 0;
      pieces.hidden = !show;
      if (show) pieces.textContent = `Приблизно ${Math.round(count * kgPerUnit(product.unit) * product.per_kg)} шт.`;
    }
    root.querySelectorAll<HTMLElement>('[data-addon]').forEach((box) => {
      const value = addons[box.dataset.addon ?? ''] ?? 0;
      const badge = box.querySelector<HTMLElement>('[data-addon-count]');
      const minus = box.querySelector<HTMLElement>('[data-addon-minus]');
      box.classList.toggle('is-on', value > 0);
      if (badge) {
        badge.hidden = value === 0;
        badge.textContent = String(value);
      }
      if (minus) minus.hidden = value === 0;
    });
  };

  // Будь-яка зміна вибору знову показує «Додати в кошик».
  const changed = () => {
    if (add) add.hidden = false;
    if (after) after.hidden = true;
    paint();
  };

  root.querySelectorAll<HTMLButtonElement>('[data-flavor]').forEach((chip) =>
    chip.addEventListener('click', () => {
      flavor = chip.dataset.flavor ?? '';
      root.querySelectorAll<HTMLButtonElement>('[data-flavor]').forEach((other) => {
        other.classList.toggle('is-on', other === chip);
        other.setAttribute('aria-checked', String(other === chip));
      });
      changed();
    }),
  );

  root.querySelectorAll<HTMLElement>('[data-addon]').forEach((box) => {
    const name = box.dataset.addon ?? '';
    box.querySelector('[data-addon-add]')?.addEventListener('click', () => {
      addons[name] = (addons[name] ?? 0) + 1;
      changed();
    });
    box.querySelector('[data-addon-minus]')?.addEventListener('click', () => {
      addons[name] = Math.max(0, (addons[name] ?? 0) - 1);
      changed();
    });
  });

  root.querySelectorAll<HTMLButtonElement>('[data-step]').forEach((button) =>
    button.addEventListener('click', () => {
      const next = button.dataset.step === '1' ? count + product.step : count - product.step;
      // Кроки на кшталт 0,5 кг: прибираємо похибку дробів.
      count = Math.max(product.min, Math.round(next * 1000) / 1000);
      changed();
    }),
  );

  add?.addEventListener('click', () => {
    const picked = Object.fromEntries(Object.entries(addons).filter(([, value]) => value > 0));
    store.addToCart({ productId: product.id, flavor, quantity: count, addons: picked });
    add.hidden = true;
    if (after) after.hidden = false;
    toast('Додано в кошик');
    track('add_to_cart', unitCost() * count + addonsCost(), [{ id: product.id, name: product.name, price: unitCost(), quantity: count }]);
  });

  initZoom(root);
  paint();
  track('view_item', product.price * product.min, [{ id: product.id, name: product.name, price: product.price, quantity: product.min }]);
}

/** Фото на весь екран. Без скрипта посилання просто відкриває картинку. */
function initZoom(root: HTMLElement) {
  const link = root.querySelector<HTMLAnchorElement>('[data-zoom]');
  if (!link) return;
  link.addEventListener('click', (event) => {
    event.preventDefault();
    const viewer = document.createElement('div');
    viewer.className = 'viewer';
    viewer.setAttribute('role', 'dialog');
    viewer.setAttribute('aria-modal', 'true');
    viewer.setAttribute('aria-label', 'Фото товару');
    viewer.innerHTML = `<img src="${link.href}" alt="${link.querySelector('img')?.alt ?? ''}"><button type="button" class="viewer__close" aria-label="Закрити фото">×</button>`;
    const close = () => {
      viewer.remove();
      document.removeEventListener('keydown', onKey);
      link.focus();
    };
    const onKey = (key: KeyboardEvent) => key.key === 'Escape' && close();
    viewer.addEventListener('click', close);
    document.addEventListener('keydown', onKey);
    document.body.append(viewer);
    viewer.querySelector<HTMLButtonElement>('button')?.focus();
  });
}
