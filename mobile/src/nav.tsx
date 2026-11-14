import { createContext, ReactNode, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { BackHandler } from 'react-native';

export type Route =
  | { name: 'home' }
  | { name: 'product'; productId: number }
  | { name: 'cart' }
  | { name: 'favorites' }
  | { name: 'checkout' }
  | { name: 'promos'; bannerId?: number }
  | { name: 'delivery' }
  | { name: 'about' }
  | { name: 'orders' }
  | { name: 'hotline' }
  | { name: 'privacy' }
  | { name: 'offer' }
  | { name: 'invite' }
  | { name: 'notifications' }
  | { name: 'login' }
  | { name: 'profile' };

export type Tab = 'home' | 'promos' | 'cart' | 'delivery';
type Overlay = 'menu' | 'ask' | null;

const TOAST_MS = 2200;

function useNavState() {
  const [stack, setStack] = useState<Route[]>([{ name: 'home' }]);
  const [overlay, setOverlay] = useState<Overlay>(null);
  const [toast, setToast] = useState('');
  const timer = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);

  const go = useCallback((route: Route) => {
    setOverlay(null);
    setStack((current) => {
      const top = current[current.length - 1];
      return JSON.stringify(top) === JSON.stringify(route) ? current : [...current, route];
    });
  }, []);

  const tab = useCallback((name: Tab) => {
    setOverlay(null);
    setStack(name === 'home' ? [{ name: 'home' }] : [{ name: 'home' }, { name }]);
  }, []);

  const back = useCallback(() => setStack((current) => (current.length > 1 ? current.slice(0, -1) : current)), []);

  /** Прибирає поточний екран і показує наступний: кнопка «Назад» не повертає на вхід. */
  const replace = useCallback((route: Route) => setStack((current) => [...current.slice(0, -1), route]), []);

  const reset = useCallback((routes: Route[]) => {
    setOverlay(null);
    setStack(routes);
  }, []);

  const showToast = useCallback((text: string) => {
    setToast(text);
    clearTimeout(timer.current);
    timer.current = setTimeout(() => setToast(''), TOAST_MS);
  }, []);

  useEffect(() => {
    const subscription = BackHandler.addEventListener('hardwareBackPress', () => {
      if (overlay) {
        setOverlay(null);
        return true;
      }
      if (stack.length > 1) {
        back();
        return true;
      }
      return false;
    });
    return () => subscription.remove();
  }, [overlay, stack.length, back]);

  return useMemo(
    () => ({ route: stack[stack.length - 1], go, tab, back, replace, reset, overlay, setOverlay, toast, showToast }),
    [stack, go, tab, back, replace, reset, overlay, toast, showToast],
  );
}

export type Nav = ReturnType<typeof useNavState>;

const NavContext = createContext<Nav | null>(null);

export function NavProvider({ children }: { children: ReactNode }) {
  const nav = useNavState();
  return <NavContext.Provider value={nav}>{children}</NavContext.Provider>;
}

export function useNav(): Nav {
  const nav = useContext(NavContext);
  if (!nav) throw new Error('useNav outside NavProvider');
  return nav;
}
