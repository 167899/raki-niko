/** Точка входу: спільна шапка й скрипт поточної сторінки (window.RAKY.page задає тема). */
import './style.css';
import { buildMapHtml } from '@app/map/mapHtml';
import type { Config } from '@app/api';
import { initLogin, initProfile } from './account';
import { initCart } from './cart';
import { initHomeChips, initSearch, initSlider } from './catalog';
import { initCheckout } from './checkout';
import { initFavorites } from './favorites';
import { initHeader } from './header';
import { initInvite } from './invite';
import { initOrders } from './orders';
import { initProduct } from './product';
import { store } from './store';
import { copy } from './ui';

initHeader();
initFavorites();

document.addEventListener('click', (event) => {
  const button = (event.target as Element).closest<HTMLElement>('[data-copy]');
  if (button?.dataset.copy) copy(button.dataset.copy);
});

// Карта зон — та сама сторінка Leaflet, що й у застосунку.
document.querySelectorAll<HTMLElement>('[data-map]').forEach((box) => {
  const frame = document.createElement('iframe');
  frame.title = 'Карта зон доставки';
  frame.loading = 'lazy';
  frame.srcdoc = buildMapHtml(JSON.parse(box.dataset.map ?? '{}') as Config);
  box.append(frame);
});

switch (window.RAKY.page) {
  case 'home':
    initSlider();
    initSearch();
    initHomeChips();
    break;
  case 'category':
    initSearch();
    break;
  case 'product':
    initProduct();
    break;
  case 'koshyk':
    initCart();
    break;
  case 'oformlennia':
    initCheckout();
    break;
  case 'zamovlennia':
    initOrders();
    break;
  case 'zaproshennia':
    initInvite();
    break;
  case 'vkhid':
    initLogin();
    break;
  case 'profil':
    initProfile();
    break;
  case 'novyny':
    store.markNewsRead();
    break;
}
