import { useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { Button, Field, Header, Icon, Logo, Paragraph, shared } from '../components/ui';
import { validPhone } from '../format';
import { useNav } from '../nav';
import { useShop } from '../shop';
import { colors } from '../theme';

export function LoginScreen() {
  const nav = useNav();
  const { findAccount, signIn } = useShop();
  const [registering, setRegistering] = useState(false);
  const [name, setName] = useState('');
  const [phone, setPhone] = useState('');
  const [nameError, setNameError] = useState('');
  const [phoneError, setPhoneError] = useState('');

  const enter = () => {
    if (!validPhone(phone)) {
      setPhoneError('Вкажіть український номер із 10 цифр');
      return;
    }
    const account = findAccount(phone);
    if (!account) {
      setPhoneError('Акаунт із цим номером не знайдено. Зареєструйтеся');
      return;
    }
    signIn(account);
    nav.back();
    nav.showToast('Ви увійшли');
  };

  const register = () => {
    const badName = !name.trim();
    const badPhone = !validPhone(phone);
    setNameError(badName ? "Вкажіть ім'я" : '');
    setPhoneError(badPhone ? 'Вкажіть український номер із 10 цифр' : '');
    if (badName || badPhone) return;
    if (findAccount(phone)) {
      setPhoneError('Цей номер уже зареєстровано. Увійдіть');
      return;
    }
    signIn({ name: name.trim(), phone: phone.trim(), email: '' });
    nav.back();
    nav.showToast('Акаунт створено');
  };

  const social = (provider: string) => nav.showToast(`Вхід через ${provider} буде доступний незабаром`);

  return (
    <View style={shared.fill}>
      <Header title={registering ? 'Реєстрація' : 'Вхід'} />
      <ScrollView style={{ flex: 1 }} contentContainerStyle={styles.body} keyboardShouldPersistTaps="handled" showsVerticalScrollIndicator={false}>
        <View style={styles.logo}>
          <Logo width={78} height={93} />
        </View>
        <Paragraph style={styles.center}>
          {registering ? 'Створіть акаунт, щоб бачити замовлення й отримувати знижки.' : 'Увійдіть у свій акаунт.'}
        </Paragraph>
        {registering && (
          <Field
            label="Ім'я *"
            value={name}
            autoComplete="name"
            error={nameError}
            onChangeText={(text) => {
              setName(text);
              setNameError('');
            }}
          />
        )}
        <Field
          label={registering ? 'Телефон *' : 'Телефон'}
          value={phone}
          placeholder="050 123 45 67"
          keyboardType="phone-pad"
          autoComplete="tel"
          error={phoneError}
          onChangeText={(text) => {
            setPhone(text);
            setPhoneError('');
          }}
        />
        <Button title={registering ? 'Зареєструватися' : 'Увійти'} style={{ marginTop: 12 }} onPress={registering ? register : enter} />
        <View style={styles.or}>
          <View style={styles.orLine} />
          <Text style={styles.orText}>або</Text>
          <View style={styles.orLine} />
        </View>
        <Pressable accessibilityRole="button" style={styles.social} onPress={() => social('Google')}>
          <Icon name="brand-google" size={18} />
          <Text style={styles.socialText}>Увійти через Google</Text>
        </Pressable>
        <Pressable accessibilityRole="button" style={[styles.social, styles.socialDark]} onPress={() => social('Apple')}>
          <Icon name="brand-apple" size={18} color={colors.white} />
          <Text style={[styles.socialText, { color: colors.white }]}>Увійти через Apple</Text>
        </Pressable>
        <View style={styles.switchRow}>
          <Text style={styles.switchText}>{registering ? 'Вже є акаунт?' : 'Немає акаунта?'} </Text>
          <Pressable
            accessibilityRole="button"
            onPress={() => {
              setRegistering((current) => !current);
              setPhoneError('');
              setNameError('');
            }}
          >
            <Text style={styles.link}>{registering ? 'Увійти' : 'Зареєструватися'}</Text>
          </Pressable>
        </View>
        <Text style={styles.fine}>
          Продовжуючи, ви приймаєте{' '}
          <Text accessibilityRole="link" style={styles.fineLink} onPress={() => nav.go({ name: 'offer' })}>
            Публічну оферту
          </Text>{' '}
          і{' '}
          <Text accessibilityRole="link" style={styles.fineLink} onPress={() => nav.go({ name: 'privacy' })}>
            Політику конфіденційності
          </Text>
          .
        </Text>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  body: { paddingTop: 10, paddingHorizontal: 14, paddingBottom: 14 },
  logo: { alignItems: 'center', marginTop: 6, marginBottom: 4 },
  center: { textAlign: 'center', marginBottom: 4 },
  or: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 12, marginBottom: 4 },
  orLine: { flex: 1, height: 1, backgroundColor: colors.line },
  orText: { fontSize: 12, color: colors.text },
  social: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 9,
    height: 44,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.border,
    marginTop: 8,
  },
  socialDark: { backgroundColor: colors.ink, borderColor: colors.ink },
  socialText: { fontSize: 14, color: colors.ink },
  switchRow: { flexDirection: 'row', justifyContent: 'center', marginTop: 14 },
  switchText: { fontSize: 13, color: colors.text },
  link: { fontSize: 13, color: colors.red },
  fine: { fontSize: 12, lineHeight: 17, color: colors.text, textAlign: 'center', marginTop: 12 },
  fineLink: { color: colors.red },
});
