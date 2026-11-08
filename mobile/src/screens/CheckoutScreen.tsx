import { useEffect, useRef, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { api, ApiError, DeliveryQuote, PaymentMethod } from '../api';
import { ReadyPicker } from '../components/ReadyPicker';
import { Button, ErrorText, Field, FieldLabel, Header, Loading, Paragraph, RowButton, shared } from '../components/ui';
import { fullPhone, money, readyLabel, validEmail, validPhone } from '../format';
import { useNav } from '../nav';
import { useShop } from '../shop';
import { colors } from '../theme';

const QUOTE_DELAY_MS = 900;
const QUOTE_MIN_LENGTH = 4;

type Errors = { name?: string; phone?: string; email?: string; address?: string };

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
  // Порожнє значення означає «якнайшвидше».
  const [ready, setReady] = useState('');
  const [picking, setPicking] = useState(false);
  const [comment, setComment] = useState('');
  const [outletId, setOutletId] = useState<string | null>(null);
  const [errors, setErrors] = useState<Errors>({});
  const [failure, setFailure] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [quote, setQuote] = useState<DeliveryQuote | null>(null);
  const [quoting, setQuoting] = useState(false);
  const latestQuote = useRef(0);

  useEffect(() => {
    if (!outletId && config?.outlets[0]) setOutletId(config.outlets[0].id);
  }, [config, outletId]);

  // Вартість рахує сервер за адресою. Запит іде після паузи в наборі, застарілі відповіді відкидаємо.
  useEffect(() => {
    const quoteId = ++latestQuote.current;
    const text = address.trim();
    setQuote(null);
    setQuoting(false);
    if (pickup || text.length < QUOTE_MIN_LENGTH) return;
    setQuoting(true);
    const timer = setTimeout(async () => {
      const result = await api.quoteDelivery(text).catch((): DeliveryQuote => ({ found: false }));
      if (quoteId !== latestQuote.current) return;
      setQuote(result);
      setQuoting(false);
    }, QUOTE_DELAY_MS);
    return () => clearTimeout(timer);
  }, [address, pickup]);

  if (!config) return <Loading />;

  const discount = promo?.discount ?? 0;
  const fee = !pickup && quote?.found ? quote.fee : 0;
  const total = subtotal - discount + fee;
  const feeKnown = pickup || !!quote?.found;

  const validate = (): Errors => {
    const found: Errors = {};
    if (!name.trim()) found.name = "Вкажіть ім'я";
    if (!validPhone(phone)) found.phone = 'Вкажіть номер із 10 цифр';
    if (!validEmail(email)) found.email = 'Перевірте email';
    if (!pickup && !address.trim()) found.address = 'Вкажіть адресу доставки';
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
      // Сума має збігтися із серверною, тому перед надсиланням беремо свіжу вартість доставки.
      const latest = pickup ? null : await api.quoteDelivery(address.trim()).catch(() => null);
      const finalFee = latest?.found ? latest.fee : 0;
      const order = await api.createOrder({
        name: name.trim(),
        phone: cleanPhone,
        email: email.trim(),
        address: address.trim(),
        comment: comment.trim(),
        delivery_type: pickup ? 'pickup' : 'delivery',
        payment_method: payment,
        ready_at: ready,
        ...(pickup ? { outlet_id: outletId ?? '' } : {}),
        items: orderItems,
        promo: promo?.code ?? '',
        expected_total: Math.round((subtotal - discount + finalFee) * 100) / 100,
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
      <ScrollView style={{ flex: 1 }} keyboardShouldPersistTaps="handled" showsVerticalScrollIndicator={false}>
        <View style={styles.sectionFirst}>
          <Field
            labelStyle={styles.label}
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
            labelStyle={styles.label}
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
            labelStyle={styles.label}
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
        </View>
        <View style={styles.section}>
          <FieldLabel style={styles.label}>Як отримати</FieldLabel>
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
              <FieldLabel style={styles.label}>Точка самовивозу</FieldLabel>
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
                labelStyle={styles.label}
                label="Адреса доставки *"
                value={address}
                placeholder="Вулиця, будинок, квартира"
                error={errors.address}
                onChangeText={(text) => {
                  setAddress(text);
                  clearError('address');
                }}
              />
              {!quoting && quote?.found === false && (
                <Paragraph style={{ marginTop: 4 }}>Не вдалося точно знайти адресу. Вартість доставки уточнить менеджер.</Paragraph>
              )}
            </>
          )}
        </View>
        <View style={styles.section}>
          <FieldLabel style={styles.label}>Готуємо на</FieldLabel>
          <Toggles
            value={ready ? 'scheduled' : 'asap'}
            options={[
              { id: 'asap', label: 'Якнайшвидше' },
              { id: 'scheduled', label: 'Дата й час' },
            ]}
            onChange={(id) => {
              if (id === 'asap') setReady('');
              else setPicking(true);
            }}
          />
          {!!ready && <RowButton icon="calendar-event" label={readyLabel(ready)} onPress={() => setPicking(true)} />}
        </View>
        <View style={styles.section}>
          <Field
            labelStyle={styles.label}
            label="Коментар"
            value={comment}
            multiline
            maxLength={500}
            placeholder="Побажання до замовлення"
            style={styles.comment}
            onChangeText={setComment}
          />
        </View>
        <View style={styles.section}>
          <FieldLabel style={styles.label}>Спосіб оплати</FieldLabel>
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
        </View>
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
            <Text style={styles.small}>{quoting ? 'розраховуємо…' : feeKnown ? money(fee) : 'уточнить менеджер'}</Text>
          </View>
        )}
        <View style={[shared.sum, { marginBottom: 8 }]}>
          <Text style={styles.totalLabel}>{feeKnown ? 'Разом' : 'Разом без доставки'}</Text>
          <Text style={styles.total}>{money(total)}</Text>
        </View>
        {!!failure && <ErrorText>{failure}</ErrorText>}
        <Button title="Надіслати замовлення" loading={submitting} style={failure ? { marginTop: 8 } : null} onPress={submit} />
      </View>
      {picking && (
        <ReadyPicker
          value={ready}
          onCancel={() => setPicking(false)}
          onDone={(value) => {
            setReady(value);
            setPicking(false);
          }}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  // Блоки розділені широкою смугою, щоб особисті дані, адреса, час і оплата не зливалися.
  sectionFirst: { paddingHorizontal: 14, paddingTop: 6, paddingBottom: 16 },
  section: { paddingHorizontal: 14, paddingTop: 6, paddingBottom: 16, borderTopWidth: 8, borderTopColor: '#F2F2F2' },
  label: { fontSize: 15 },
  toggles: { flexDirection: 'row', gap: 6 },
  toggle: { flex: 1, height: 38, borderRadius: 10, borderWidth: 1, borderColor: colors.border, alignItems: 'center', justifyContent: 'center' },
  toggleOn: { backgroundColor: colors.red, borderColor: colors.red },
  toggleText: { fontSize: 15, color: colors.text },
  toggleTextOn: { color: colors.white },
  comment: { height: 76 },
  small: { fontSize: 15, color: colors.text },
  totalLabel: { fontSize: 16, fontWeight: '500', color: colors.ink },
  total: { fontSize: 19, fontWeight: '500', color: colors.ink },
});
