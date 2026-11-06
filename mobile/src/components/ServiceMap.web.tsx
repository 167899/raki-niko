import { createElement, useMemo } from 'react';
import { buildMapHtml } from '../map/mapHtml';
import type { ServiceMapProps } from './ServiceMap';

export function ServiceMap({ config }: ServiceMapProps) {
  const html = useMemo(() => buildMapHtml(config), [config]);
  return createElement('iframe', {
    srcDoc: html,
    title: 'Карта зон доставки',
    style: { border: 0, width: '100%', height: '100%' },
  });
}
