import { useMemo } from 'react';
import { WebView } from 'react-native-webview';
import { Config } from '../api';
import { buildMapHtml } from '../map/mapHtml';

export type ServiceMapProps = { config: Config };

export function ServiceMap({ config }: ServiceMapProps) {
  const html = useMemo(() => buildMapHtml(config), [config]);
  return <WebView originWhitelist={['*']} source={{ html, baseUrl: 'https://localhost' }} nestedScrollEnabled />;
}
