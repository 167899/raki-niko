import { useMemo } from 'react';
import { WebView } from 'react-native-webview';
import { Config } from '../api';
import { buildMapHtml } from '../map/mapHtml';

export type ServiceMapProps = {
  config: Config;
  onPick?: (lat: number, lng: number) => void;
  initial?: { lat: number; lng: number } | null;
};

export function ServiceMap({ config, onPick, initial }: ServiceMapProps) {
  // HTML будується один раз: маркер рухається всередині карти без перезавантаження.
  const html = useMemo(() => buildMapHtml(config, !!onPick, initial), [config]); // eslint-disable-line react-hooks/exhaustive-deps
  return (
    <WebView
      originWhitelist={['*']}
      source={{ html, baseUrl: 'https://localhost' }}
      nestedScrollEnabled
      onMessage={(event) => {
        const message = JSON.parse(event.nativeEvent.data);
        if (message.type === 'raky-pick') onPick?.(message.lat, message.lng);
      }}
    />
  );
}
