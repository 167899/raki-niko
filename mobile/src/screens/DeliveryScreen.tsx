import { ScrollView, StyleSheet, Text, View } from 'react-native';
import { ServiceMap } from '../components/ServiceMap';
import { BottomBar, Header, Heading, InfoRow, Paragraph, shared } from '../components/ui';
import { CASH_NOTE, PAY_STEPS } from '../content';
import { money } from '../format';
import { useShop } from '../shop';
import { colors } from '../theme';

export function DeliveryScreen() {
  const { config } = useShop();
  const fees = config?.zones.map((zone) => zone.fee) ?? [];
  return (
    <View style={shared.fill}>
      <Header title="Оплата і доставка" />
      <ScrollView style={{ flex: 1 }} contentContainerStyle={styles.body} showsVerticalScrollIndicator={false}>
        {config && (
          <>
            <Heading>Зони доставки</Heading>
            <View style={styles.map}>
              <ServiceMap config={config} />
            </View>
            {fees.length > 0 && (
              <Paragraph style={{ marginTop: 6 }}>
                Вартість від {money(Math.min(...fees))} до {money(Math.max(...fees))} залежно від району. Торкніться району на карті, щоб побачити ціну.
              </Paragraph>
            )}
            <Paragraph style={{ marginTop: 4 }}>Доставляє партнерська кур'єрська служба по Миколаєву.</Paragraph>
            <Heading>Самовивіз</Heading>
            {config.outlets.map((outlet) => (
              <InfoRow key={outlet.id} left={outlet.name} right={outlet.hours.toLowerCase()} />
            ))}
          </>
        )}
        <Heading>Оплата</Heading>
        {PAY_STEPS.map((step, index) => (
          <View key={step} style={styles.step}>
            <View style={styles.number}>
              <Text style={styles.numberText}>{index + 1}</Text>
            </View>
            <Text style={styles.stepText}>{step}</Text>
          </View>
        ))}
        <Paragraph style={{ marginTop: 4 }}>{CASH_NOTE}</Paragraph>
      </ScrollView>
      <BottomBar active="delivery" />
    </View>
  );
}

const styles = StyleSheet.create({
  body: { paddingTop: 2, paddingHorizontal: 12, paddingBottom: 8 },
  map: { height: 190, borderRadius: 10, overflow: 'hidden', backgroundColor: '#F3F3F3' },
  step: { flexDirection: 'row', gap: 8, alignItems: 'flex-start', paddingVertical: 3 },
  number: { width: 20, height: 20, borderRadius: 10, backgroundColor: colors.soft, alignItems: 'center', justifyContent: 'center' },
  numberText: { fontSize: 13, color: colors.softInk },
  stepText: { flex: 1, fontSize: 14, lineHeight: 19, color: colors.text },
});
