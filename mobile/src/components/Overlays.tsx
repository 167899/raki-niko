import { Linking, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { PHONES } from '../content';
import { fullPhone } from '../format';
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

export const SOCIALS: IconName[] = ['brand-instagram', 'brand-facebook', 'brand-tiktok', 'brand-telegram'];

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
        <View style={styles.socials}>
          {SOCIALS.map((icon) => (
            <Icon key={icon} name={icon} size={22} color={colors.red} />
          ))}
        </View>
      </ScrollView>
    </View>
  );
}

function Ask() {
  const nav = useNav();
  const stub = () => nav.showToast('Посилання буде додано');
  return (
    <View style={styles.sheet}>
      <View style={shared.sum}>
        <Text style={styles.sheetTitle}>Запитати</Text>
        <IconButton label="Закрити" onPress={() => nav.setOverlay(null)}>
          <Icon name="x" />
        </IconButton>
      </View>
      <RowButton icon="phone" label="Зателефонувати" onPress={() => Linking.openURL(`tel:${PHONES[0]}`)} />
      <RowButton icon="message-circle" label="Написати у Viber" onPress={stub} />
      <RowButton icon="brand-telegram" label="Написати в Telegram" onPress={stub} />
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
  whoName: { fontSize: 14, fontWeight: '500', color: colors.softInk },
  whoHint: { fontSize: 12, color: colors.softInk },
  item: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingVertical: 7, paddingHorizontal: 14 },
  itemText: { fontSize: 13, color: colors.text },
  socials: {
    flexDirection: 'row',
    justifyContent: 'center',
    gap: 18,
    padding: 10,
    borderTopWidth: 1,
    borderTopColor: colors.line,
  },
  sheet: { position: 'absolute', left: 0, right: 0, bottom: 0, backgroundColor: colors.white, borderTopLeftRadius: 16, borderTopRightRadius: 16, padding: 14 },
  sheetTitle: { fontSize: 15, fontWeight: '500', color: colors.ink },
});
