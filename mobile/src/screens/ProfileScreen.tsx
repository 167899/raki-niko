import { useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { TelegramLogin } from '../components/TelegramLogin';
import { Button, Field, Header, Icon, Paragraph, shared, Warn } from '../components/ui';
import { fullPhone, validEmail, validPhone } from '../format';
import { useNav } from '../nav';
import { useShop } from '../shop';
import { colors } from '../theme';

export function ProfileScreen() {
  const nav = useNav();
  const { user, updateProfile, signOut, deleteAccount, verified } = useShop();
  const [name, setName] = useState(user?.name ?? '');
  const [phone, setPhone] = useState(user ? fullPhone(user.phone) : '');
  const [email, setEmail] = useState(user?.email ?? '');
  const [errors, setErrors] = useState({ name: '', phone: '', email: '' });
  const [confirming, setConfirming] = useState(false);

  const save = () => {
    const found = {
      name: name.trim() ? '' : "Вкажіть ім'я",
      phone: validPhone(phone) ? '' : 'Вкажіть український номер із 10 цифр',
      email: validEmail(email) ? '' : 'Перевірте email',
    };
    setErrors(found);
    if (found.name || found.phone || found.email) return;
    updateProfile({ name: name.trim(), phone: phone.trim(), email: email.trim() });
    nav.showToast('Збережено');
  };

  const leave = (action: () => void, message: string) => {
    action();
    nav.reset([{ name: 'home' }]);
    nav.showToast(message);
  };

  return (
    <View style={shared.fill}>
      <Header title="Профіль" />
      <ScrollView style={{ flex: 1 }} contentContainerStyle={styles.body} keyboardShouldPersistTaps="handled" showsVerticalScrollIndicator={false}>
        <View style={{ alignItems: 'center' }}>
          <Icon name="user-circle" size={44} color={colors.red} />
        </View>
        <Field label="Ім'я *" value={name} error={errors.name} onChangeText={setName} />
        <Field label="Телефон" value={phone} placeholder="050 123 45 67" keyboardType="phone-pad" error={errors.phone} onChangeText={setPhone} />
        <Field label="Email (за бажанням)" value={email} keyboardType="email-address" autoCapitalize="none" error={errors.email} onChangeText={setEmail} />
        <View style={{ marginTop: 12 }}>
          {verified ? (
            <View style={styles.verified}>
              <Icon name="brand-telegram" size={16} color={colors.green} />
              <Text style={styles.verifiedText}>Номер підтверджено в Telegram: замовлення й улюблене із застосунку та сайту синхронізуються.</Text>
            </View>
          ) : (
            <>
              <Paragraph>Підтвердіть номер, щоб бачити тут і замовлення, оформлені на сайті.</Paragraph>
              <TelegramLogin
                label="Підтвердити через Telegram"
                onDone={(confirmed) => {
                  // Підтвердили інший номер, ніж був у полі, — показуємо саме його.
                  setPhone(fullPhone(confirmed));
                  nav.showToast('Номер підтверджено');
                }}
              />
            </>
          )}
        </View>
        <Button title="Зберегти" style={{ marginTop: 12, height: 40 }} onPress={save} />
        <Button kind="outline" title="Вийти" style={{ marginTop: 8, height: 40 }} onPress={() => leave(signOut, 'Ви вийшли з акаунта')} />
        <View style={{ marginTop: 16 }}>
          {confirming ? (
            <>
              <Warn text="Акаунт, адреси й історію замовлень буде видалено. Цю дію не можна скасувати." />
              <View style={styles.confirm}>
                <Button size="sm" kind="outline" title="Скасувати" style={{ flex: 1 }} onPress={() => setConfirming(false)} />
                <Button size="sm" title="Видалити" style={{ flex: 1 }} onPress={() => leave(deleteAccount, 'Акаунт видалено')} />
              </View>
            </>
          ) : (
            <Pressable accessibilityRole="button" style={{ alignItems: 'center' }} onPress={() => setConfirming(true)}>
              <Text style={styles.link}>Видалити акаунт</Text>
            </Pressable>
          )}
        </View>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  body: { paddingTop: 10, paddingHorizontal: 14, paddingBottom: 14 },
  confirm: { flexDirection: 'row', gap: 6, marginTop: 8 },
  link: { fontSize: 15, color: colors.red },
  verified: { flexDirection: 'row', gap: 6, alignItems: 'flex-start' },
  verifiedText: { flex: 1, fontSize: 14, lineHeight: 19, color: colors.green },
});
