import { useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { BottomBar, Button, Empty, ErrorText, Header, Icon, Input, Paragraph, ProductPicture, shared, Warn } from '../components/ui';
import { isWeighed, money, quantity } from '../format';
import { useNav } from '../nav';
import { ResolvedLine, useShop } from '../shop';
import { colors } from '../theme';

function CartRow({ line }: { line: ResolvedLine }) {
  const { removeLine, setLineQuantity } = useShop();
  const { product } = line;
  const details = [product.caliber_hint, line.flavor.toLowerCase()].filter(Boolean).join(', ');
  return (
    <View style={styles.row}>
      <ProductPicture product={product} size={24} style={styles.thumb} />
      <View style={{ flex: 1, minWidth: 0 }}>
        <View style={styles.nameRow}>
          <Text style={styles.name}>{product.name}</Text>
          <Pressable accessibilityRole="button" accessibilityLabel="Видалити" style={styles.remove} onPress={() => removeLine(line)}>
            <Icon name="trash" size={18} color={colors.text} />
          </Pressable>
        </View>
        {!!details && <Paragraph style={{ marginBottom: 6 }}>{details}</Paragraph>}
        <View style={styles.amountRow}>
          <Pressable
            accessibilityRole="button"
            accessibilityLabel="Менше"
            style={styles.step}
            onPress={() => setLineQuantity(line, Math.max(product.min, line.quantity - product.step))}
          >
            <Icon name="minus" size={16} />
          </Pressable>
          <Text style={styles.amount}>{quantity(line.quantity, product.unit)}</Text>
          <Pressable
            accessibilityRole="button"
            accessibilityLabel="Більше"
            style={styles.step}
            onPress={() => setLineQuantity(line, line.quantity + product.step)}
          >
            <Icon name="plus" size={16} />
          </Pressable>
          <Text style={styles.lineTotal}>{money(line.total)}</Text>
        </View>
      </View>
    </View>
  );
}

export function CartScreen() {
  const nav = useNav();
  const { lines, subtotal, promo, promoError, applyPromo, clearPromoMessage } = useShop();
  const [code, setCode] = useState(promo?.code ?? '');
  const [checking, setChecking] = useState(false);

  if (!lines.length) {
    return (
      <View style={shared.fill}>
        <Header title="Кошик" />
        <View style={{ flex: 1 }}>
          <Empty icon="shopping-cart" text="Кошик порожній">
            <Button size="sm" title="До меню" style={{ width: 150, marginTop: 14 }} onPress={() => nav.tab('home')} />
          </Empty>
        </View>
        <BottomBar active="cart" />
      </View>
    );
  }

  const discount = promo?.discount ?? 0;

  const submitCode = async () => {
    setChecking(true);
    await applyPromo(code);
    setChecking(false);
  };

  return (
    <View style={shared.fill}>
      <Header title="Кошик" />
      <ScrollView style={{ flex: 1 }} contentContainerStyle={shared.pad} showsVerticalScrollIndicator={false} keyboardShouldPersistTaps="handled">
        {lines.map((line) => (
          <CartRow key={`${line.productId}:${line.flavor}`} line={line} />
        ))}
        {lines.some((line) => isWeighed(line.product.unit)) && <Warn text="Точну вагу й суму менеджер підтвердить перед оплатою." />}
      </ScrollView>
      <View style={styles.summary}>
        <Text style={styles.promoLabel}>Промокод</Text>
        <View style={styles.promoRow}>
          <Input
            style={{ flex: 1 }}
            placeholder="Введіть промокод"
            accessibilityLabel="Промокод"
            autoCapitalize="characters"
            autoCorrect={false}
            value={code}
            onChangeText={(text) => {
              setCode(text);
              clearPromoMessage();
            }}
          />
          <Pressable accessibilityRole="button" disabled={checking} style={styles.apply} onPress={submitCode}>
            <Text style={styles.applyText}>Застосувати</Text>
          </Pressable>
        </View>
        {!!promoError && <ErrorText>{promoError}</ErrorText>}
        {!!promo && !promoError && <ErrorText color={colors.green}>Промокод застосовано</ErrorText>}
        <View style={[shared.sum, { marginTop: 9, marginBottom: 3 }]}>
          <Text style={styles.small}>Доставка</Text>
          <Text style={styles.small}>при оформленні</Text>
        </View>
        {!!promo && (
          <View style={[shared.sum, { marginBottom: 3 }]}>
            <Text style={[styles.small, { color: colors.green }]}>Знижка</Text>
            <Text style={[styles.small, { color: colors.green }]}>−{money(discount)}</Text>
          </View>
        )}
        <View style={[shared.sum, { marginBottom: 8 }]}>
          <Text style={styles.totalLabel}>Разом без доставки</Text>
          <Text style={styles.total}>{money(subtotal - discount)}</Text>
        </View>
        <Button title="Оформити замовлення" onPress={() => nav.go({ name: 'checkout' })} />
      </View>
      <BottomBar active="cart" />
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', gap: 9, borderWidth: 1, borderColor: colors.line, borderRadius: 12, padding: 9, marginBottom: 8 },
  thumb: { width: 58, height: 58, borderRadius: 9 },
  nameRow: { flexDirection: 'row', gap: 4, alignItems: 'flex-start' },
  name: { flex: 1, fontSize: 15, lineHeight: 19, fontWeight: '500', color: colors.ink },
  remove: { width: 30, height: 30, alignItems: 'center', justifyContent: 'center' },
  amountRow: { flexDirection: 'row', alignItems: 'center', gap: 5 },
  step: { width: 30, height: 30, borderWidth: 1, borderColor: colors.border, borderRadius: 10, alignItems: 'center', justifyContent: 'center' },
  amount: { minWidth: 44, textAlign: 'center', fontSize: 15, color: colors.ink },
  lineTotal: { flex: 1, textAlign: 'right', fontSize: 16, fontWeight: '500', color: colors.ink },
  summary: { paddingTop: 8, paddingHorizontal: 14, paddingBottom: 12, borderTopWidth: 1, borderTopColor: colors.line },
  promoLabel: { fontSize: 14, color: colors.text, marginBottom: 4 },
  promoRow: { flexDirection: 'row', gap: 6 },
  apply: { height: 38, borderRadius: 10, backgroundColor: colors.red, paddingHorizontal: 12, alignItems: 'center', justifyContent: 'center' },
  applyText: { color: colors.white, fontSize: 15, fontWeight: '500' },
  small: { fontSize: 15, color: colors.text },
  totalLabel: { fontSize: 16, fontWeight: '500', color: colors.ink },
  total: { fontSize: 19, fontWeight: '500', color: colors.ink },
});

