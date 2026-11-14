/** Серця «В улюблене» на картках і товарі та сторінка «Улюблене» (як FavoritesScreen застосунку). */
import { api, ApiError } from './api';
import { store } from './store';
import { toast } from './ui';

function paint() {
  const favorites = store.favorites();
  document.querySelectorAll<HTMLButtonElement>('[data-fav]').forEach((button) => {
    const on = favorites.includes(Number(button.dataset.fav));
    button.setAttribute('aria-pressed', String(on));
    button.setAttribute('aria-label', on ? 'Прибрати з улюбленого' : 'В улюблене');
  });
  const list = document.querySelector<HTMLElement>('[data-favorites]');
  if (list) {
    let shown = 0;
    list.querySelectorAll<HTMLElement>('[data-fav-card]').forEach((card) => {
      card.hidden = !favorites.includes(Number(card.dataset.favCard));
      if (!card.hidden) shown++;
    });
    const empty = list.querySelector<HTMLElement>('[data-favorites-empty]');
    if (empty) empty.hidden = shown > 0;
  }
}

/** З підтвердженим номером «Улюблене» живе на сервері: однакове на сайті й у застосунку. */
async function sync() {
  const session = store.session();
  if (!session) return;
  try {
    const { ids } = await api.favorites(session.session);
    if (store.session()?.session !== session.session) return;
    if (!session.mergeFavorites) {
      store.setFavorites(ids);
      return;
    }
    const merged = [...new Set([...ids, ...store.favorites()])];
    store.setFavorites(merged);
    store.setSession({ session: session.session, phone: session.phone });
    if (merged.length !== ids.length) await api.saveFavorites(session.session, merged);
  } catch (error) {
    // Сесію відкликали — далі «Улюблене» лише в цьому браузері, як раніше.
    if (error instanceof ApiError && error.status === 401) store.setSession(null);
  }
}

export function initFavorites() {
  document.addEventListener('click', (event) => {
    const button = (event.target as Element).closest<HTMLButtonElement>('[data-fav]');
    if (!button) return;
    event.preventDefault();
    const on = store.toggleFavorite(Number(button.dataset.fav));
    const session = store.session();
    if (session) api.saveFavorites(session.session, store.favorites()).catch(() => undefined);
    toast(on ? 'Додано в улюблене' : 'Прибрано з улюбленого');
  });
  paint();
  window.addEventListener('raky:mine', paint);
  window.addEventListener('storage', paint);
  sync();
  // Після входу через Telegram на цій же сторінці.
  let synced = store.session()?.session;
  window.addEventListener('raky:mine', () => {
    const now = store.session()?.session;
    if (now && now !== synced) {
      synced = now;
      sync();
    }
  });
}
