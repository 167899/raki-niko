import { useState } from 'react';
import { Linking, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { PHONES, TELEGRAM_URL, VIBER_URL } from '../content';
import { Social } from '../api';
import { fullPhone, prettyPhone } from '../format';
import { IconName } from '../icons';
import { Route, Tab, useNav } from '../nav';
import { useShop } from '../shop';
import { colors } from '../theme';
import { Icon, IconButton, RowButton, shared } from './ui';

type Item = { icon: IconName; label: string; route: Route['name'] };

const ITEMS: Item[] = [
  { icon: 'home', label: 'Головна', route: 'home' },
  { icon: 'receipt', label: 'Мої замовлення', route: 'orders' },
  { icon: 'info-circle', label: 'Про компанію', route: 'about' },
  { icon: 'shopping-cart', label: 'Кошик', route: 'cart' },
  { icon: 'headset', label: 'Гаряча лінія', route: 'hotline' },
  { icon: 'credit-card', label: 'Оплата і доставка', route: 'delivery' },
  { icon: 'map-pin', label: 'Контакти', route: 'about' },
  { icon: 'shield-lock', label: 'Політика конфіденційності', route: 'privacy' },
  { icon: 'file-text', label: 'Публічна оферта', route: 'offer' },
  { icon: 'gift', label: 'Запросити друзів', route: 'invite' },
  { icon: 'bell', label: 'Повідомлення', route: 'notifications' },
];

const TAB_ROUTES: Tab[] = ['home', 'cart', 'delivery'];

/** Іконки соцмереж. Viber іконки не має: він лише в «Написати нам». */
const SOCIAL_ICONS: Partial<Record<Social['network'], IconName>> = {
  telegram: 'brand-telegram',
  instagram: 'brand-instagram',
  facebook: 'brand-facebook',
  tiktok: 'brand-tiktok',
};

const FALLBACK_SOCIALS: Social[] = [{ network: 'telegram', label: 'Telegram', url: TELEGRAM_URL }];

/** Телефони й соцмережі зі сторінки «Сайт» в адмінці (спільні із сайтом); поки сервер не відповів — запасні. */
export function useContacts() {
  const nav = useNav();
  const { config } = useShop();
  const phones = config?.phones?.length ? config.phones : PHONES;
  const socials = config?.socials ?? FALLBACK_SOCIALS;
  const link = (network: Social['network'], fallback: string) =>
    config?.socials ? (socials.find((item) => item.network === network)?.url ?? '') : fallback;
  const open = (url: string) => {
    if (url) Linking.openURL(url).catch(() => nav.showToast('Не вдалося відкрити посилання'));
    else nav.showToast('Посилання буде додано');
  };
  const viberUrl = link('viber', VIBER_URL);
  const telegramUrl = link('telegram', TELEGRAM_URL);
  return {
    phones,
    socials,
    call: (phone: string) => open(`tel:${phone}`),
    /** Без посилання кнопку не показуємо. */
    hasViber: !!viberUrl,
    hasTelegram: !!telegramUrl,
    viber: () => open(viberUrl),
    telegram: () => open(telegramUrl),
    open,
  };
}

export function SocialIcons({ size, style }: { size: number; style: object }) {
  const contacts = useContacts();
  const icons = contacts.socials.flatMap((item) => {
    const icon = SOCIAL_ICONS[item.network];
    return icon ? [{ ...item, icon }] : [];
  });
  if (!icons.length) return null;
  return (
    <View style={style}>
      {icons.map((item, index) => (
        <Pressable key={`${item.network}-${index}`} accessibilityRole="link" accessibilityLabel={item.label} hitSlop={6} onPress={() => contacts.open(item.url)}>
          <Icon name={item.icon} size={size} color={colors.red} />
        </Pressable>
      ))}
    </View>
  );
}

function Menu() {
  const nav = useNav();
  const { user } = useShop();

  const open = (route: Route['name']) => {
    const asTab = TAB_ROUTES.find((tab) => tab === route);
    if (asTab) nav.tab(asTab);
    else nav.go({ name: route } as Route);
  };

  return (
    <View style={styles.dropdown}>
      <ScrollView style={{ flexGrow: 0 }} showsVerticalScrollIndicator={false}>
        <View style={styles.who}>
          <Icon name="user-circle" size={28} color={colors.red} />
          <Pressable accessibilityRole="button" style={{ flex: 1 }} onPress={() => nav.go({ name: user ? 'profile' : 'login' })}>
            <Text style={styles.whoName}>{user ? user.name : 'Гість'}</Text>
            <Text style={styles.whoHint}>{user ? fullPhone(user.phone) : 'Увійти або зареєструватися'}</Text>
          </Pressable>
          <IconButton label="Закрити" onPress={() => nav.setOverlay(null)}>
            <Icon name="x" />
          </IconButton>
        </View>
        <View style={{ paddingVertical: 4 }}>
          {ITEMS.map((item) => (
            <Pressable key={item.label} accessibilityRole="button" style={styles.item} onPress={() => open(item.route)}>
              <Icon name={item.icon} size={17} color={colors.red} />
              <Text style={styles.itemText}>{item.label}</Text>
            </Pressable>
          ))}
        </View>
        <SocialIcons size={22} style={styles.socials} />
      </ScrollView>
    </View>
  );
}

function Ask() {
  const nav = useNav();
  const contacts = useContacts();
  const [phones, setPhones] = useState(false);
  return (
    <View style={styles.sheet}>
      <View style={shared.sum}>
        <Text style={styles.sheetTitle}>Запитати</Text>
        <IconButton label="Закрити" onPress={() => nav.setOverlay(null)}>
          <Icon name="x" />
        </IconButton>
      </View>
      {phones ? (
        <>
          <Text style={styles.sheetHint}>Оберіть номер</Text>
          {contacts.phones.map((phone) => (
            <RowButton key={phone} icon="phone" label={prettyPhone(phone)} onPress={() => contacts.call(phone)} />
          ))}
        </>
      ) : (
        <RowButton icon="phone" label="Зателефонувати" onPress={() => setPhones(true)} />
      )}
      {contacts.hasViber && <RowButton icon="message-circle" label="Написати у Viber" onPress={contacts.viber} />}
      {contacts.hasTelegram && <RowButton icon="brand-telegram" label="Написати в Telegram" onPress={contacts.telegram} />}
    </View>
  );
}

export function Overlays() {
  const nav = useNav();
  if (!nav.overlay) return null;
  return (
    <View style={styles.layer}>
      <Pressable accessibilityLabel="Закрити" style={StyleSheet.absoluteFill} onPress={() => nav.setOverlay(null)} />
      {nav.overlay === 'menu' ? <Menu /> : <Ask />}
    </View>
  );
}

const styles = StyleSheet.create({
  layer: { position: 'absolute', top: 0, right: 0, bottom: 0, left: 0, zIndex: 10, backgroundColor: colors.overlay },
  dropdown: { position: 'absolute', top: 48, right: 8, width: 260, maxHeight: '90%', overflow: 'hidden', borderRadius: 12, backgroundColor: colors.white },
  who: { flexDirection: 'row', gap: 9, alignItems: 'center', paddingVertical: 10, paddingHorizontal: 12, backgroundColor: colors.soft },
  whoName: { fontSize: 16, fontWeight: '500', color: colors.softInk },
  whoHint: { fontSize: 14, color: colors.softInk },
  item: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingVertical: 7, paddingHorizontal: 14 },
  itemText: { fontSize: 15, color: colors.text },
  socials: {
    flexDirection: 'row',
    justifyContent: 'center',
    gap: 18,
    padding: 10,
    borderTopWidth: 1,
    borderTopColor: colors.line,
  },
  sheet: { position: 'absolute', left: 0, right: 0, bottom: 0, backgroundColor: colors.white, borderTopLeftRadius: 16, borderTopRightRadius: 16, padding: 14 },
  sheetTitle: { fontSize: 17, fontWeight: '500', color: colors.ink },
  sheetHint: { fontSize: 14, color: colors.text, marginBottom: 4 },
});
