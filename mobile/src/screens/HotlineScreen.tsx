import { ScrollView, View } from 'react-native';
import { useContacts } from '../components/Overlays';
import { Header, Heading, Paragraph, RowButton, shared } from '../components/ui';
import { HOURS } from '../content';
import { prettyPhone } from '../format';
import { useShop } from '../shop';

export function HotlineScreen() {
  const contacts = useContacts();
  const { config } = useShop();
  return (
    <View style={shared.fill}>
      <Header title="Гаряча лінія" />
      <ScrollView style={{ flex: 1 }} contentContainerStyle={shared.pad} showsVerticalScrollIndicator={false}>
        <Paragraph>Щодня {config?.hours || HOURS}. Натисніть номер, щоб зателефонувати.</Paragraph>
        <Heading>Зателефонувати</Heading>
        {contacts.phones.map((phone, index) => (
          <RowButton
            key={phone}
            icon="phone"
            label={prettyPhone(phone)}
            style={index === 0 ? { marginTop: 4 } : null}
            onPress={() => contacts.call(phone)}
          />
        ))}
        {(contacts.hasViber || contacts.hasTelegram) && <Heading>Написати нам</Heading>}
        {contacts.hasViber && <RowButton icon="message-circle" label="Viber" style={{ marginTop: 4 }} onPress={contacts.viber} />}
        {contacts.hasTelegram && (
          <RowButton icon="brand-telegram" label="Telegram" style={contacts.hasViber ? null : { marginTop: 4 }} onPress={contacts.telegram} />
        )}
      </ScrollView>
    </View>
  );
}
