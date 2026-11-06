import { useEffect, useState } from 'react';
import { ScrollView, StyleSheet, Text, View } from 'react-native';
import { Order, OrderStatusCode } from '../api';
import { Button, Empty, Header, Loading, Paragraph, shared, Warn } from '../components/ui';
import { dateTimeLabel, money, quantity, readyLabel } from '../format';
import { useNav } from '../nav';
import { useShop } from '../shop';
import { colors } from '../theme';

const STEPS = 5;

const STATUS: Record<OrderStatusCode, { label: string; color: string; step: number }> = {
  awaiting_confirmation: { label: 'Очікує підтвердження', color: colors.red, step: 1 },
  awaiting_payment: { label: 'Очікує оплату', color: colors.red, step: 2 },
  preparing: { label: 'Готуємо замовлення', color: colors.red, step: 3 },
  delivering: { label: 'Передано в доставку', color: colors.red, step: 4 },
  completed: { label: 'Виконано', color: colors.green, step: 0 },
  cancelled: { label: 'Скасовано', color: colors.text, step: 0 },
  unknown: { label: 'Статус уточнюється', color: colors.text, step: 0 },
};

function orderText(order: Order): string {
  const items = order.items.map((item) =>
    [item.name, item.flavor.toLowerCase(), quantity(item.quantity, item.unit)].filter(Boolean).join(', '),
  );
  if (order.delivery_type === 'pickup') items.push(`самовивіз, ${order.address}`);
  return items.join('; ');
}

function OrderCard({ order }: { order: Order }) {
  const nav = useNav();
  const { repeatOrder } = useShop();
  const [showRequisites, setShowRequisites] = useState(false);
  const status = STATUS[order.status.code];
  const feePending = order.delivery_type === 'delivery' && order.shipping === 0;
  const checks = [order.has_weighted && 'вагу', feePending && 'вартість доставки'].filter(Boolean).join(' й ');
  return (
    <View style={styles.card}>
      <View style={styles.head}>
        <Text style={styles.headText}>№ {order.id}</Text>
        <Text style={styles.headText}>{money(order.total)}</Text>
      </View>
      <Paragraph>{dateTimeLabel(order.created_at)}</Paragraph>
      <Text style={[styles.status, { color: status.color }]}>{status.label}</Text>
      {status.step > 0 && (
        <View style={styles.progress}>
          {Array.from({ length: STEPS }, (_, index) => (
            <View key={index} style={[styles.segment, index < status.step && styles.segmentOn]} />
          ))}
        </View>
      )}
      <Paragraph style={{ marginTop: 4 }}>{orderText(order)}</Paragraph>
      <Paragraph>Оплата: {order.payment_method === 'cash' ? 'готівкою при отриманні' : 'за реквізитами'}</Paragraph>
      {!!order.ready_at && <Paragraph>Готуємо на: {readyLabel(order.ready_at)}</Paragraph>}
      {!!order.comment && <Paragraph>Коментар: {order.comment}</Paragraph>}
      {order.status.code === 'awaiting_confirmation' && (order.has_weighted || feePending) && (
        <Warn text={`Сума попередня: менеджер уточнить ${checks} перед оплатою.`} style={{ marginTop: 7 }} />
      )}
      {!!order.requisites && (
        <>
          {showRequisites && (
            <Text style={styles.requisites}>
              {order.requisites}
              {`\nПризначення: замовлення № ${order.id}`}
            </Text>
          )}
          <Button
            size="sm"
            title={showRequisites ? 'Сховати реквізити' : 'Показати реквізити'}
            style={{ marginTop: 7 }}
            onPress={() => setShowRequisites((current) => !current)}
          />
        </>
      )}
      {order.status.code === 'completed' && (
        <Button
          size="sm"
          kind="outline"
          title="Повторити замовлення"
          style={{ marginTop: 7 }}
          onPress={() => {
            repeatOrder(order.id);
            nav.tab('cart');
          }}
        />
      )}
    </View>
  );
}

export function OrdersScreen() {
  const { orders, ordersLoading, refreshOrders } = useShop();

  useEffect(() => {
    refreshOrders();
    // Оновлюємо лише при відкритті екрана: далі працює періодичне оновлення магазину.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return (
    <View style={shared.fill}>
      <Header title="Мої замовлення" />
      {ordersLoading && !orders.length ? (
        <Loading />
      ) : (
        <ScrollView style={{ flex: 1 }} contentContainerStyle={shared.pad} showsVerticalScrollIndicator={false}>
          {orders.length ? orders.map((order) => <OrderCard key={order.id} order={order} />) : <Empty icon="receipt" text="Тут будуть ваші замовлення" />}
        </ScrollView>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  card: { borderWidth: 1, borderColor: colors.line, borderRadius: 12, padding: 10, marginBottom: 8 },
  head: { flexDirection: 'row', justifyContent: 'space-between' },
  headText: { fontSize: 14, fontWeight: '500', color: colors.ink },
  status: { fontSize: 13, marginTop: 5 },
  progress: { flexDirection: 'row', gap: 3, marginTop: 7, marginBottom: 3 },
  segment: { flex: 1, height: 4, borderRadius: 2, backgroundColor: '#E2E2E2' },
  segmentOn: { backgroundColor: colors.red },
  requisites: {
    backgroundColor: colors.soft,
    color: colors.softInk,
    fontSize: 12,
    lineHeight: 18,
    borderRadius: 8,
    paddingVertical: 8,
    paddingHorizontal: 9,
    marginTop: 7,
    overflow: 'hidden',
  },
});
