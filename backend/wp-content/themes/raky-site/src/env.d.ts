// mobile/src/api.ts читає змінні Expo; на сайті з нього беремо лише типи.
declare const process: { env: Record<string, string | undefined> };
declare function require(name: string): any;

interface Window {
  /** Дані від теми (inc/assets.php). */
  RAKY: {
    api: string;
    page: string;
    urls: { home: string; cart: string; checkout: string; orders: string; invite: string; login: string; profile: string };
    news: number[];
    links: Record<string, string>;
    referral: { enabled: boolean; percent: number };
    phone: string;
    label: string;
    track: boolean;
  };
  /** Google Tag Manager / Google Analytics, якщо підключені. */
  dataLayer?: unknown[];
  /** Піксель Meta, якщо підключений. */
  fbq?: (...args: unknown[]) => void;
}

declare module '*.css';
