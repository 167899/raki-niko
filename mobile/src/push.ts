import * as Device from 'expo-device';
import * as Notifications from 'expo-notifications';
import { useEffect, useRef } from 'react';
import { Platform } from 'react-native';
import { colors } from './theme';

/**
 * Push-сповіщення: статус замовлення й новини приходять, навіть коли застосунок закрито.
 * Телефон отримує токен Expo і передає його серверу (POST /push/register), розсилає сервер (raky-shop/includes/push.php).
 * У вебверсії сповіщень немає.
 */

const PROJECT_ID = 'aa4057dc-12c1-419e-b634-92ecec1a132b'; // app.json → extra.eas.projectId
const CHANNEL = 'default'; // той самий channelId надсилає сервер

export type PushData = { type?: 'order' | 'news'; id?: number };

const supported = Platform.OS !== 'web';

if (supported) {
  // Застосунок відкрито — сповіщення однаково показуємо зверху.
  Notifications.setNotificationHandler({
    handleNotification: async () => ({ shouldShowBanner: true, shouldShowList: true, shouldPlaySound: true, shouldSetBadge: false }),
  });
}

/** Просить дозвіл і повертає токен телефону. null — дозволу немає, емулятор чи немає інтернету. */
export async function getPushToken(): Promise<string | null> {
  if (!supported || !Device.isDevice) return null;
  try {
    // На Android 13+ канал потрібен до запиту токена, інакше система не покаже запит дозволу.
    if (Platform.OS === 'android') {
      await Notifications.setNotificationChannelAsync(CHANNEL, {
        name: 'Замовлення та акції',
        importance: Notifications.AndroidImportance.HIGH,
        lightColor: colors.red,
        sound: 'default',
      });
    }
    let { status } = await Notifications.getPermissionsAsync();
    if (status !== 'granted') status = (await Notifications.requestPermissionsAsync()).status;
    if (status !== 'granted') return null;
    return (await Notifications.getExpoPushTokenAsync({ projectId: PROJECT_ID })).data;
  } catch {
    return null;
  }
}

const dataOf = (notification: Notifications.Notification) => (notification.request.content.data ?? {}) as PushData;

/** onOpen — клієнт натиснув на сповіщення (і коли застосунок був закритий); onReceive — сповіщення прийшло, поки застосунок відкрито. */
export function usePush(onOpen: (data: PushData) => void, onReceive: (data: PushData) => void) {
  const handlers = useRef({ onOpen, onReceive });
  handlers.current = { onOpen, onReceive };
  const last = supported ? Notifications.useLastNotificationResponse() : null;

  useEffect(() => {
    if (!last || last.actionIdentifier !== Notifications.DEFAULT_ACTION_IDENTIFIER) return;
    handlers.current.onOpen(dataOf(last.notification));
    Notifications.clearLastNotificationResponseAsync().catch(() => undefined);
  }, [last]);

  useEffect(() => {
    if (!supported) return;
    const subscription = Notifications.addNotificationReceivedListener((notification) => handlers.current.onReceive(dataOf(notification)));
    return () => subscription.remove();
  }, []);
}
