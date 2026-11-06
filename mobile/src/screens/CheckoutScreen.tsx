import { useCallback, useEffect, useRef, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { api, ApiError, DeliveryCheck, PaymentMethod } from '../api';
import { ServiceMap } from '../components/ServiceMap';
import { Button, ErrorText, Field, FieldLabel, Header, Loading, Paragraph, RowButton, shared } from '../components/ui';
import { fullPhone, money, validEmail, validPhone } from '../format';
import { useNav } from '../nav';
import { useShop } from '../shop';
import { colors } from '../theme';

type Errors = { name?: string; phone?: string; email?: string; address?: string; map?: string };

function Toggles<T extends string>({
  value,
  options,
  onChange,
}: {
  value: T;
  options: { id: T; label: string }[];
  onChange: (id: T) => void;
}) {
  return (
    <View style={styles.toggles}>
      {options.map((option) => (
        <Pressable
          key={option.id}
          accessibilityRole="button"
          accessibilityState={{ selected: value === option.id }}
          style={[styles.toggle, value === option.id && styles.toggleOn]}
          onPress={() => onChange(option.id)}
        >
          <Text style={[styles.toggleText, value === option.id && styles.toggleTextOn]}>{option.label}</Text>
        </Pressable>
      ))}
    </View>
  );
}

export function CheckoutScreen() {
  const nav = useNav();
  const shop = useShop();
  const { config, cart, lines, subtotal, orderItems, promo, user } = shop;

  const [name, setName] = useState(user?.name ?? '');
  const [phone, setPhone] = useState(user ? fullPhone(user.phone) : '');
  const [email, setEmail] = useState(user?.email ?? '');
  const [address, setAddress] = useState('');
  const [pickup, setPickup] = useState(false);
  const [payment, setPayment] = useState<PaymentMethod>('requisites');
  const [outletId, setOutletId] = useState<string | null>(null);
  const [point, setPoint] = useState<{ lat: number; lng: number } | null>(null);
  const [delivery, setDelivery] = useState<DeliveryCheck | null>(null);
  const [errors, setErrors] = useState<Errors>({});
  const [failure, setFailure] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const latestPick = useRef(0);

  useEffect(() => {
    if (!outletId && config?.outlets[0]) setOutletId(config.outlets[0].id);
  }, [config, outletId]);

  const pick = useCallback(async (lat: number, lng: number) => {
    const pickId = ++latestPick.current;
    setPoint({ lat, lng });
    setDelivery(null);
    setErrors((current) => ({ ...current, map: undefined }));
    try {
      const result = await api.checkDelivery(lat, lng);
      // Клієнт міг переставити мітку, поки йшов запит: застарілу відповідь ігноруємо.
      if (pickId === latestPick.current) setDelivery(result);
    } catch (error) {
      if (pickId === latestPick.current) {
        setErrors((current) => ({ ...current, map: error instanceof ApiError ? error.message : 'Не вдалося перевірити адресу.' }));
      }
    }
  }, []);

  if (!config) return <Loading />;

  const zone = !pickup && delivery?.in_zone ? delivery : null;
  const fee = zone?.fee ?? 0;
  const discount = promo?.discount ?? 0;
  const total = subtotal - discount + fee;

  const validate = (): Errors => {
    const found: Errors = {};
    if (!name.trim()) found.name = "Вкажіть ім'я";
    if (!validPhone(phone)) found.phone = 'Вкажіть номер із 10 цифр';
    if (!validEmail(email)) found.email = 'Перевірте email';
    if (!pickup) {
      if (!address.trim()) found.address = 'Вкажіть адресу доставки';
      if (!point) found.map = 'Позначте адресу на карті';
      else if (delivery && !delivery.in_zone) found.map = 'Сюди ми поки не доставляємо. Оберіть точку в кольоровій зоні';
      else if (zone && subtotal < zone.min_order) found.map = `Мінімальне замовлення для цієї зони: ${money(zone.min_order)}`;
      else if (!zone) found.map = 'Перевіряємо адресу, зачекайте';
    }
    return found;
  };

  const submit = async () => {
    const found = validate();
    setErrors(found);
    setFailure('');
    if (Object.keys(found).length) return;
    if (!lines.length) {
      nav.showToast('Кошик порожній');
      return;
    }
    setSubmitting(true);
    try {
      const cleanPhone = phone.replace(/[\s()-]/g, '');
      const order = await api.createOrder({
        name: name.trim(),
        phone: cleanPhone,
        email: email.trim(),
        address: address.trim(),
        comment: '',
        delivery_type: pickup ? 'pickup' : 'delivery',
        payment_method: payment,
        ...(pickup ? { outlet_id: outletId ?? '' } : point!),
        items: orderItems,
        promo: promo?.code ?? '',
        expected_total: Math.round(total * 100) / 100,
      });
      shop.rememberOrder(order, cart);
      if (!user) shop.signIn({ name: name.trim(), phone: cleanPhone, email: email.trim() });
      shop.clearCart();
      nav.reset([{ name: 'home' }, { name: 'orders' }]);
      nav.showToast('Замовлення надіслано');
    } catch (error) {
      const apiError = error instanceof ApiError ? error : null;
      if (apiError?.status === 409) await shop.reload();
      setFailure(apiError?.message ?? 'Не вдалося оформити замовлення.');
    } finally {
      setSubmitting(false);
    }
  };

  const clearError = (key: keyof Errors) => setErrors((current) => ({ ...current, [key]: undefined }));

  return (
    <View style={shared.fill}>
      <Header title="Оформлення" />
      <ScrollView style={{ flex: 1 }} contentContainerStyle={styles.body} keyboardShouldPersistTaps="handled" showsVerticalScrollIndicator={false}>
        <Field
          label="Ім'я *"
          value={name}
          autoComplete="name"
          error={errors.name}
          onChangeText={(text) => {
            setName(text);
            clearError('name');
          }}
        />
        <Field
          label="Телефон *"
          value={phone}
          placeholder="050 123 45 67"
          keyboardType="phone-pad"
          autoComplete="tel"
          error={errors.phone}
          onChangeText={(text) => {
            setPhone(text);
            clearError('phone');
          }}
        />
        <Field
          label="Email (за бажанням)"
          value={email}
          keyboardType="email-address"
          autoCapitalize="none"
          autoComplete="email"
          error={errors.email}
          onChangeText={(text) => {
            setEmail(text);
            clearError('email');
          }}
        />
        <FieldLabel>Як отримати</FieldLabel>
        <Toggles
          value={pickup ? 'pickup' : 'delivery'}
          options={[
            { id: 'delivery', label: 'Доставка' },
            { id: 'pickup', label: 'Самовивіз' },
          ]}
          onChange={(id) => setPickup(id === 'pickup')}
        />
        {pickup ? (
          <>
            <FieldLabel>Точка самовивозу</FieldLabel>
            {config.outlets.map((outlet, index) => (
              <RowButton
                key={outlet.id}
                icon="map-pin"
                label={outlet.name}
                on={outlet.id === outletId}
                style={index === 0 ? { marginTop: 0 } : null}
                onPress={() => setOutletId(outlet.id)}
              />
            ))}
          </>
        ) : (
          <>
            <Field
              label="Адреса доставки *"
              value={address}
              placeholder="Вулиця, будинок, квартира"
              error={errors.address}
              onChangeText={(text) => {
                setAddress(text);
                clearError('address');
              }}
            />
            <FieldLabel>Позначте адресу на карті</FieldLabel>
            <View style={styles.map}>
              <ServiceMap config={config} onPick={pick} initial={point} />
            </View>
            {!!errors.map && <ErrorText>{errors.map}</ErrorText>}
            {!errors.map && zone && <ErrorText color={colors.green}>Зона «{zone.zone_name}», доставка {money(zone.fee)}</ErrorText>}
            {!errors.map && delivery && !delivery.in_zone && <ErrorText>Сюди ми поки не доставляємо. Оберіть точку в кольоровій зоні</ErrorText>}
          </>
        )}
        <FieldLabel>Спосіб оплати</FieldLabel>
        <Toggles
          value={payment}
          options={[
            { id: 'requisites', label: 'На реквізити' },
            { id: 'cash', label: 'Готівкою' },
          ]}
          onChange={setPayment}
        />
        <Paragraph style={{ marginTop: 6 }}>
          {payment === 'cash'
            ? 'Оплата при отриманні замовлення.'
            : "Реквізити з'являться в замовленні після підтвердження менеджером."}
        </Paragraph>
      </ScrollView>
      <View style={shared.foot}>
        {discount > 0 && (
          <View style={[shared.sum, { marginBottom: 3 }]}>
            <Text style={[styles.small, { color: colors.green }]}>Знижка</Text>
            <Text style={[styles.small, { color: colors.green }]}>−{money(discount)}</Text>
          </View>
        )}
        {!pickup && (
          <View style={[shared.sum, { marginBottom: 3 }]}>
            <Text style={styles.small}>Доставка</Text>
            <Text style={styles.small}>{zone ? money(fee) : 'після вибору адреси'}</Text>
          </View>
        )}
        <View style={[shared.sum, { marginBottom: 8 }]}>
          <Text style={styles.totalLabel}>Разом</Text>
          <Text style={styles.total}>{money(total)}</Text>
        </View>
        {!!failure && <ErrorText>{failure}</ErrorText>}
        <Button title="Надіслати замовлення" loading={submitting} style={failure ? { marginTop: 8 } : null} onPress={submit} />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  body: { paddingTop: 4, paddingHorizontal: 14, paddingBottom: 12 },
  toggles: { flexDirection: 'row', gap: 6 },
  toggle: { flex: 1, height: 38, borderRadius: 10, borderWidth: 1, borderColor: colors.border, alignItems: 'center', justifyContent: 'center' },
  toggleOn: { backgroundColor: colors.red, borderColor: colors.red },
  toggleText: { fontSize: 13, color: colors.text },
  toggleTextOn: { color: colors.white },
  map: { height: 190, borderRadius: 10, overflow: 'hidden', backgroundColor: '#F3F3F3' },
  small: { fontSize: 13, color: colors.text },
  totalLabel: { fontSize: 14, fontWeight: '500', color: colors.ink },
  total: { fontSize: 17, fontWeight: '500', color: colors.ink },
});
