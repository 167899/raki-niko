/** Дрібниці інтерфейсу: тост, іконки, створення елементів, аналітика. */
import { outlineIcons } from '@app/icons';

const TOAST_MS = 2200;
let toastTimer: ReturnType<typeof setTimeout> | undefined;

export function toast(text: string) {
  const box = document.querySelector<HTMLElement>('[data-toast]');
  if (!box) return;
  box.textContent = text;
  box.hidden = false;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => {
    box.hidden = true;
  }, TOAST_MS);
}

export function icon(name: keyof typeof outlineIcons, size = 20): string {
  const paths = outlineIcons[name].map((d) => `<path d="${d}"/>`).join('');
  return `<svg class="icon" width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">${paths}</svg>`;
}

const ESCAPES: Record<string, string> = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };

/** Екранування тексту для вставки в HTML. */
export const esc = (value: unknown) => String(value ?? '').replace(/[&<>"']/g, (char) => ESCAPES[char]);

export async function copy(text: string) {
  try {
    await navigator.clipboard.writeText(text);
  } catch {
    const field = document.createElement('textarea');
    field.value = text;
    document.body.append(field);
    field.select();
    document.execCommand('copy');
    field.remove();
  }
  toast(`Скопійовано: ${text}`);
}

/**
 * Події для реклами: Google (dataLayer, GA4/Ads через GTM) і Meta Pixel, якщо їх підключено на сайті.
 * Без підключених лічильників нічого не робить.
 */
export function track(event: 'view_item' | 'add_to_cart' | 'begin_checkout' | 'purchase', value: number, items: { id: number; name: string; price: number; quantity: number }[], extra: Record<string, unknown> = {}) {
  const ecommerce = {
    currency: 'UAH',
    value: Math.round(value * 100) / 100,
    items: items.map((item) => ({ item_id: String(item.id), item_name: item.name, price: item.price, quantity: item.quantity })),
    ...extra,
  };
  if (window.dataLayer) {
    window.dataLayer.push({ ecommerce: null });
    window.dataLayer.push({ event, ecommerce });
  }
  const meta = { view_item: 'ViewContent', add_to_cart: 'AddToCart', begin_checkout: 'InitiateCheckout', purchase: 'Purchase' }[event];
  window.fbq?.('track', meta, { currency: 'UAH', value: ecommerce.value, content_ids: ecommerce.items.map((item) => item.item_id), content_type: 'product' });
}

export const productUrl = (id: number) => window.RAKY.links[String(id)] ?? window.RAKY.urls.home;

export const loadingHtml = '<div class="loading" aria-label="Завантаження"></div>';
