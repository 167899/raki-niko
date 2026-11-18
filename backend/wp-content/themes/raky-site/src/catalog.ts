/** Головна й категорії: швидкий пошук, категорії, що прилипають до верху, банери. */
import { fuzzyMatch } from '@app/search';

const AUTO_SLIDE_MS = 4000;
const PAUSE_AFTER_TOUCH_MS = 6000;

export function initSearch() {
  const input = document.querySelector<HTMLInputElement>('[data-search]');
  const clear = document.querySelector<HTMLButtonElement>('[data-search-clear]');
  const nothing = document.querySelector<HTMLElement>('[data-nothing]');
  if (!input) return;
  const filter = () => {
    const query = input.value;
    let shown = 0;
    document.querySelectorAll<HTMLElement>('[data-section]').forEach((section) => {
      let inSection = 0;
      section.querySelectorAll<HTMLElement>('.card[data-search]').forEach((card) => {
        const match = fuzzyMatch(query, card.dataset.search ?? '');
        card.hidden = !match;
        if (match) inSection++;
      });
      section.hidden = inSection === 0 && !!query.trim();
      // Підзаголовок підрозділу без знайдених карток теж ховаємо.
      section.querySelectorAll<HTMLElement>('[data-sub]').forEach((sub) => {
        sub.hidden = !!query.trim() && !sub.querySelector('.card[data-search]:not([hidden])');
      });
      shown += inSection;
    });
    if (nothing) nothing.hidden = shown > 0 || !query.trim();
    if (clear) clear.hidden = !query;
  };
  input.addEventListener('input', filter);
  clear?.addEventListener('click', () => {
    input.value = '';
    filter();
    input.focus();
  });
}

/** На головній категорії гортають до свого розділу, а не відкривають окрему сторінку. */
export function initHomeChips() {
  const chips = [...document.querySelectorAll<HTMLAnchorElement>('[data-chip]')];
  if (!chips.length) return;
  const strip = chips[0].parentElement as HTMLElement;
  // Активна категорія завжди видна в стрічці: стрічка сама гортається вліво чи вправо.
  const reveal = (chip: HTMLElement) => {
    const left = chip.getBoundingClientRect().left - strip.getBoundingClientRect().left + strip.scrollLeft;
    strip.scrollTo({ left: left - (strip.clientWidth - chip.offsetWidth) / 2, behavior: prefersMotion() ? 'smooth' : 'auto' });
  };
  const setOn = (id: string) =>
    chips.forEach((chip) => {
      const on = chip.dataset.chip === id;
      if (on && !chip.classList.contains('is-on')) reveal(chip);
      chip.classList.toggle('is-on', on);
    });
  let jumping = 0;
  chips.forEach((chip) =>
    chip.addEventListener('click', (event) => {
      if (event.metaKey || event.ctrlKey || event.shiftKey) return;
      event.preventDefault();
      const id = chip.dataset.chip ?? 'all';
      setOn(id);
      jumping = Date.now();
      const target = id === 'all' ? document.body : document.getElementById(`cat-${id}`);
      const bar = document.querySelector<HTMLElement>('[data-catbar]');
      const top = id === 'all' ? 0 : (target?.getBoundingClientRect().top ?? 0) + window.scrollY - (bar?.offsetHeight ?? 0) - topOffset();
      window.scrollTo({ top, behavior: prefersMotion() ? 'smooth' : 'auto' });
    }),
  );
  // Під час прокрутки підсвічується категорія, яку зараз видно.
  const sections = [...document.querySelectorAll<HTMLElement>('[data-section]')];
  const observer = new IntersectionObserver(
    (entries) => {
      if (Date.now() - jumping < 900) return;
      const visible = entries.filter((entry) => entry.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)[0];
      if (visible) setOn((visible.target as HTMLElement).dataset.section ?? 'all');
    },
    { rootMargin: '-35% 0px -60% 0px' },
  );
  sections.forEach((section) => observer.observe(section));
  // Повернулися на самий верх — знову «Усі».
  window.addEventListener('scroll', () => {
    if (window.scrollY < 40 && Date.now() - jumping > 900) setOn('all');
  }, { passive: true });
}

/** Висота закріпленої шапки (і панелі адміністратора, якщо вона є). */
const topOffset = () =>
  (document.querySelector<HTMLElement>('.top')?.offsetHeight ?? 0) + (parseFloat(getComputedStyle(document.body).getPropertyValue('--ab')) || 0);
const prefersMotion = () => !window.matchMedia('(prefers-reduced-motion: reduce)').matches;

export function initSlider() {
  const slider = document.querySelector<HTMLElement>('[data-slider]');
  const track = slider?.querySelector<HTMLElement>('[data-slider-track]');
  if (!slider || !track) return;
  const dots = [...slider.querySelectorAll<HTMLButtonElement>('[data-slider-dot]')];
  const count = track.children.length;
  let active = 0;
  let touched = 0;
  const show = (index: number, smooth = true) => {
    active = (index + count) % count;
    track.scrollTo({ left: active * track.clientWidth, behavior: smooth && prefersMotion() ? 'smooth' : 'auto' });
    dots.forEach((dot, i) => (i === active ? dot.setAttribute('aria-current', 'true') : dot.removeAttribute('aria-current')));
  };
  dots.forEach((dot, index) =>
    dot.addEventListener('click', () => {
      touched = Date.now();
      show(index);
    }),
  );
  let settle: ReturnType<typeof setTimeout> | undefined;
  track.addEventListener(
    'scroll',
    () => {
      clearTimeout(settle);
      settle = setTimeout(() => {
        const index = Math.round(track.scrollLeft / track.clientWidth);
        if (index !== active) {
          active = index;
          dots.forEach((dot, i) => (i === active ? dot.setAttribute('aria-current', 'true') : dot.removeAttribute('aria-current')));
        }
      }, 80);
    },
    { passive: true },
  );
  for (const name of ['pointerdown', 'touchstart', 'wheel', 'focusin']) {
    track.addEventListener(name, () => (touched = Date.now()), { passive: true });
  }
  if (count < 2 || !prefersMotion()) return;
  setInterval(() => {
    if (document.hidden || Date.now() - touched < PAUSE_AFTER_TOUCH_MS || slider.matches(':hover')) return;
    show(active + 1);
  }, AUTO_SLIDE_MS);
}
