import { Linking, ScrollView, View } from 'react-native';
import { Header, Heading, Paragraph, RowButton, shared } from '../components/ui';
import { HOURS, PHONES } from '../content';
import { prettyPhone } from '../format';
import { useNav } from '../nav';

export function HotlineScreen() {
  const nav = useNav();
  const stub = () => nav.showToast('Посилання буде додано');
  return (
    <View style={shared.fill}>
      <Header title="Гаряча лінія" />
      <ScrollView style={{ flex: 1 }} contentContainerStyle={shared.pad} showsVerticalScrollIndicator={false}>
        <Paragraph>Щодня {HOURS}. Натисніть номер, щоб зателефонувати.</Paragraph>
        <Heading>Зателефонувати</Heading>
        {PHONES.map((phone, index) => (
          <RowButton
            key={phone}
            icon="phone"
            label={prettyPhone(phone)}
            style={index === 0 ? { marginTop: 4 } : null}
            onPress={() => Linking.openURL(`tel:${phone}`)}
          />
        ))}
        <Heading>Написати нам</Heading>
        <RowButton icon="message-circle" label="Viber" style={{ marginTop: 4 }} onPress={stub} />
        <RowButton icon="brand-telegram" label="Telegram" onPress={stub} />
      </ScrollView>
    </View>
  );
}
