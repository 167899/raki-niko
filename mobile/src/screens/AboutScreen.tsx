import { Linking, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SOCIALS } from '../components/Overlays';
import { Header, Heading, Icon, InfoRow, Logo, Paragraph, shared } from '../components/ui';
import { HOURS, PHONES } from '../content';
import { prettyPhone } from '../format';
import { useShop } from '../shop';
import { colors } from '../theme';

export function AboutScreen() {
  const { config } = useShop();
  return (
    <View style={shared.fill}>
      <Header title="Про компанію" />
      <ScrollView style={{ flex: 1 }} contentContainerStyle={styles.body} showsVerticalScrollIndicator={false}>
        <View style={{ alignItems: 'center' }}>
          <Logo width={78} height={93} />
        </View>
        <Heading>Хто ми</Heading>
        <Paragraph>Raki Niko продає раків у Миколаєві.</Paragraph>
        <Heading>Телефони</Heading>
        <View style={styles.box}>
          {PHONES.map((phone) => (
            <Pressable key={phone} accessibilityRole="link" style={styles.contact} onPress={() => Linking.openURL(`tel:${phone}`)}>
              <Icon name="phone" size={18} color={colors.red} />
              <Text style={styles.contactText}>{prettyPhone(phone)}</Text>
            </Pressable>
          ))}
        </View>
        <Heading>Адреси</Heading>
        <View style={styles.box}>
          {config?.outlets.map((outlet) => (
            <View key={outlet.id} style={styles.contact}>
              <Icon name="map-pin" size={18} color={colors.red} />
              <Text style={styles.contactText}>{outlet.name}</Text>
            </View>
          ))}
        </View>
        <Heading>Робочі години</Heading>
        <InfoRow left="Щодня" right={HOURS} />
        <View style={styles.socials}>
          {SOCIALS.map((icon) => (
            <Icon key={icon} name={icon} size={24} color={colors.red} />
          ))}
        </View>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  body: { paddingTop: 10, paddingHorizontal: 14, paddingBottom: 14 },
  box: { borderWidth: 1, borderColor: colors.line, borderRadius: 12, paddingVertical: 6, paddingHorizontal: 11 },
  contact: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingVertical: 6 },
  contactText: { fontSize: 15, color: colors.text },
  socials: { flexDirection: 'row', gap: 18, paddingTop: 10 },
});
