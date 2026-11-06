import { createElement, useEffect, useMemo, useRef } from 'react';
import { buildMapHtml } from '../map/mapHtml';
import type { ServiceMapProps } from './ServiceMap';

export function ServiceMap({ config, onPick, initial }: ServiceMapProps) {
  const html = useMemo(() => buildMapHtml(config, !!onPick, initial), [config]); // eslint-disable-line react-hooks/exhaustive-deps
  const frame = useRef<HTMLIFrameElement>(null);

  useEffect(() => {
    const listener = (event: MessageEvent) => {
      if (event.source !== frame.current?.contentWindow || typeof event.data !== 'string') return;
      const message = JSON.parse(event.data);
      if (message.type === 'raky-pick') onPick?.(message.lat, message.lng);
    };
    window.addEventListener('message', listener);
    return () => window.removeEventListener('message', listener);
  }, [onPick]);

  return createElement('iframe', {
    ref: frame,
    srcDoc: html,
    title: 'Карта зон доставки',
    style: { border: 0, width: '100%', height: '100%' },
  });
}
