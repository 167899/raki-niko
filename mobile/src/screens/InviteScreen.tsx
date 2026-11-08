import { ScrollView, StyleSheet, Text, View } from 'react-native';
import { Header, Heading, Paragraph, shared, Warn } from '../components/ui';
import { useShop } from '../shop';
import { colors } from '../theme';

export function InviteScreen() {
  const { config } = useShop();
  const percent = config?.referral.percent ?? 5;
  return (
    <View style={shared.fill}>
      <Header title="Запросити друзів" />
      <ScrollView style={{ flex: 1 }} contentContainerStyle={shared.pad} showsVerticalScrollIndicator={false}>
        <View style={styles.card}>
          <Text style={styles.title}>−{percent}% вам і другу</Text>
          <Text style={styles.text}>Разова знижка кожному учаснику.</Text>
        </View>
        <Heading>Як це працює</Heading>
        <Paragraph>
          1. Надішліть посилання другу.{'\n'}2. Друг реєструється і вводить код під час першого замовлення.{'\n'}3. Кожен отримує знижку.
        </Paragraph>
        <Warn text="Особистий код з'явиться після запуску програми." style={{ marginTop: 12 }} />
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  card: { backgroundColor: colors.red, borderRadius: 12, padding: 14 },
  title: { fontSize: 19, lineHeight: 23, fontWeight: '500', color: colors.white },
  text: { fontSize: 14, lineHeight: 19, color: colors.white, marginTop: 3 },
});
