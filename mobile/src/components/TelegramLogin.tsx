import { useEffect, useRef, useState } from 'react';
import { ActivityIndicator, Linking, Pressable, StyleSheet, Text, View } from 'react-native';
import { api, ApiError } from '../api';
import { useShop } from '../shop';
import { colors } from '../theme';
import { ErrorText, Icon, Paragraph } from './ui';

const POLL_MS = 2000;
const TELEGRAM_BLUE = '#229ED9';

/**
 * «Увійти через Telegram»: бот магазину просить поділитися номером, після підтвердження
 * застосунок бачить усі замовлення з цим номером, зокрема оформлені на сайті.
 * Якщо не вийшло — клієнт просто лишається без синхронізації, як раніше.
 */
/** onDone отримує підтверджений номер. */
export function TelegramLogin({ label, onDone }: { label: string; onDone: (phone: string) => void }) {
  const { confirmTelegram } = useShop();
  const [url, setUrl] = useState('');
  const [error, setError] = useState('');
  const [starting, setStarting] = useState(false);
  const token = useRef('');
  const timer = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);

  useEffect(() => () => clearTimeout(timer.current), []);

  const stop = (message = '') => {
    clearTimeout(timer.current);
    token.current = '';
    setUrl('');
    setError(message);
  };

  const poll = async () => {
    const current = token.current;
    if (!current) return;
    try {
      const status = await api.authStatus(current);
      if (current !== token.current) return;
      if (status.status === 'confirmed') {
        stop();
        confirmTelegram(status);
        onDone(status.phone);
        return;
      }
    } catch (failure) {
      if (current !== token.current) return;
      if (failure instanceof ApiError && failure.status === 404) {
        stop(failure.message);
        return;
      }
      // Зв'язок пропав на мить — пробуємо далі.
    }
    timer.current = setTimeout(poll, POLL_MS);
  };

  const start = async () => {
    setStarting(true);
    setError('');
    try {
      const started = await api.authStart();
      token.current = started.token;
      setUrl(started.url);
      Linking.openURL(started.url).catch(() => undefined);
      timer.current = setTimeout(poll, POLL_MS);
    } catch (failure) {
      setError(failure instanceof ApiError ? failure.message : 'Не вдалося почати вхід. Спробуйте ще раз.');
    } finally {
      setStarting(false);
    }
  };

  if (url) {
    return (
      <View style={styles.wait} accessibilityLiveRegion="polite">
        <ActivityIndicator color={colors.red} />
        <Text style={styles.waitTitle}>Підтвердіть номер у Telegram</Text>
        <Paragraph style={{ textAlign: 'center' }}>У боті Raki Niko натисніть «Поділитися номером» і поверніться сюди.</Paragraph>
        <Pressable accessibilityRole="button" style={[styles.button, { marginTop: 4 }]} onPress={() => Linking.openURL(url).catch(() => undefined)}>
          <Icon name="brand-telegram" size={18} color={colors.white} />
          <Text style={styles.buttonText}>Відкрити Telegram</Text>
        </Pressable>
        <Pressable accessibilityRole="button" hitSlop={8} onPress={() => stop()}>
          <Text style={styles.cancel}>Скасувати</Text>
        </Pressable>
      </View>
    );
  }

  return (
    <View>
      <Pressable accessibilityRole="button" accessibilityState={{ busy: starting }} disabled={starting} style={styles.button} onPress={start}>
        {starting ? <ActivityIndicator color={colors.white} /> : <Icon name="brand-telegram" size={18} color={colors.white} />}
        <Text style={styles.buttonText}>{label}</Text>
      </Pressable>
      {!!error && <ErrorText>{error}</ErrorText>}
    </View>
  );
}

const styles = StyleSheet.create({
  button: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 9,
    height: 44,
    borderRadius: 8,
    marginTop: 8,
    backgroundColor: TELEGRAM_BLUE,
    alignSelf: 'stretch',
  },
  buttonText: { fontSize: 16, color: colors.white },
  wait: { alignItems: 'center', gap: 6, marginTop: 8, padding: 12, borderWidth: 1, borderColor: colors.line, borderRadius: 12 },
  waitTitle: { fontSize: 16, fontWeight: '500', color: colors.ink },
  cancel: { fontSize: 15, color: colors.red, marginTop: 4 },
});
