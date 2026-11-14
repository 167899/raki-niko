import { ScrollView, View } from 'react-native';
import { useContacts } from '../components/Overlays';
import { Header, Heading, Paragraph, RowButton, shared } from '../components/ui';
import { HOURS, PHONES } from '../content';
import { prettyPhone } from '../format';

export function HotlineScreen() {
  const contacts = useContacts();
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
            onPress={() => contacts.call(phone)}
          />
        ))}
        <Heading>Написати нам</Heading>
        <RowButton icon="message-circle" label="Viber" style={{ marginTop: 4 }} onPress={contacts.viber} />
        <RowButton icon="brand-telegram" label="Telegram" onPress={contacts.telegram} />
      </ScrollView>
    </View>
  );
}
