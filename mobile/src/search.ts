/**
 * Пошук, що прощає помилки: однакові на слух літери, розкладка клавіатури, пропущена чи зайва літера.
 * Кожне слово запиту має знайтися в назві товару, його підкатегорії чи категорії.
 */

// Схожі літери української та російської: «рыба», «риба», «ріба» шукаються однаково.
const SAME: Record<string, string> = {
  ы: 'и',
  і: 'и',
  ї: 'и',
  й: 'и',
  ё: 'е',
  є: 'е',
  э: 'е',
  ъ: '',
  ь: '',
  ґ: 'г',
  щ: 'ш',
};

// Набрано в англійській розкладці: «hfrb» → «раки».
const LATIN = "qwertyuiop[]asdfghjkl;'zxcvbnm,.";
const CYRILLIC = 'йцукенгшщзхїфівапролджєячсмитьбю';

function fromLatin(text: string): string {
  return [...text].map((char) => CYRILLIC[LATIN.indexOf(char)] ?? char).join('');
}

function normalize(text: string): string {
  return [...text.toLowerCase().replace(/['’ʼ`]/g, '')]
    .map((char) => SAME[char] ?? char)
    .join('')
    .replace(/[^a-zа-я0-9]+/g, ' ')
    .trim();
}

/** Кількість правок, щоб з одного слова отримати інше (вставка, видалення, заміна, перестановка сусідніх). */
function distance(a: string, b: string): number {
  const rows = a.length + 1;
  const cols = b.length + 1;
  const d: number[][] = Array.from({ length: rows }, (_, i) => Array.from({ length: cols }, (_, j) => (i === 0 ? j : j === 0 ? i : 0)));
  for (let i = 1; i < rows; i++) {
    for (let j = 1; j < cols; j++) {
      const cost = a[i - 1] === b[j - 1] ? 0 : 1;
      d[i][j] = Math.min(d[i - 1][j] + 1, d[i][j - 1] + 1, d[i - 1][j - 1] + cost);
      if (i > 1 && j > 1 && a[i - 1] === b[j - 2] && a[i - 2] === b[j - 1]) d[i][j] = Math.min(d[i][j], d[i - 2][j - 2] + 1);
    }
  }
  return d[a.length][b.length];
}

/** Скільки помилок пробачаємо слову такої довжини. */
function allowed(length: number): number {
  return length < 4 ? 0 : length < 7 ? 1 : 2;
}

function wordMatches(query: string, word: string): boolean {
  if (word.includes(query)) return true;
  const limit = allowed(query.length);
  if (!limit) return false;
  // Слово ще не дописане: порівнюємо з початком слова тієї ж довжини (±1 літера).
  for (const size of [query.length - 1, query.length, query.length + 1]) {
    if (size > 0 && size <= word.length && distance(query, word.slice(0, size)) <= limit) return true;
  }
  return distance(query, word) <= limit;
}

function matchesNormalized(query: string, text: string): boolean {
  const words = text.split(' ');
  return query.split(' ').every((part) => words.some((word) => wordMatches(part, word)));
}

/** Чи підходить текст під запит. Порожній запит підходить усьому. */
export function fuzzyMatch(query: string, text: string): boolean {
  const lowered = query.toLowerCase().trim();
  if (!lowered) return true;
  const haystack = normalize(text);
  const direct = normalize(lowered);
  if (direct && matchesNormalized(direct, haystack)) return true;
  // Лише латиниця: можливо, забули перемкнути розкладку.
  if (/^[a-z[\];',. ]+$/.test(lowered)) {
    const switched = normalize(fromLatin(lowered));
    return !!switched && matchesNormalized(switched, haystack);
  }
  return false;
}
