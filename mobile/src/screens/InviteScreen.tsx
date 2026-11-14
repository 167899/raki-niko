import * as Clipboard from 'expo-clipboard';
import { useEffect } from 'react';
import { ScrollView, Share, StyleSheet, Text, View } from 'react-native';
import { Button, Header, Heading, Loading, Paragraph, shared, Warn } from '../components/ui';
import { useNav } from '../nav';
import { useShop } from '../shop';
import { colors } from '../theme';

export function InviteScreen() {
  const nav = useNav();
  const { config, user, referral, refreshReferral } = useShop();
  const percent = referral?.percent ?? config?.referral.percent ?? 5;
  const enabled = config?.referral.enabled === true;

  useEffect(() => {
    refreshReferral();
  }, [refreshReferral]);

  const code = referral?.code;
  const message = `Тримай −${percent}% на перше замовлення в Raki Niko. Введи мій код ${code} у кошику. Раки в Миколаєві: rakiniko.com.ua`;

  const copy = async () => {
    if (!code) return;
    await Clipboard.setStringAsync(code);
    nav.showToast(`Скопійовано: ${code}`);
  };

  const share = () => {
    Share.share({ message }).catch(() => undefined);
  };

  const body = () => {
    if (!enabled) return <Warn text="Запрошення друзів зараз не діє." style={{ marginTop: 12 }} />;
    if (!user) {
      return (
        <>
          <Warn text="Увійдіть, щоб отримати особистий код." style={{ marginTop: 12 }} />
          <Button title="Увійти" style={{ marginTop: 12 }} onPress={() => nav.go({ name: 'login' })} />
        </>
      );
    }
    if (!code) return <Loading />;
    return (
      <>
        <Heading>Ваш код</Heading>
        <View style={styles.codeBox}>
          <Text selectable style={styles.code}>
            {code}
          </Text>
        </View>
        <View style={styles.actions}>
          <Button title="Поділитися" style={{ flex: 1 }} onPress={share} />
          <Button title="Копіювати" kind="outline" style={{ flex: 1 }} onPress={copy} />
        </View>
        {referral.bonuses.length > 0 && (
          <View style={styles.bonus}>
            <Text style={styles.bonusTitle}>
              У вас {referral.bonuses.length > 1 ? `${referral.bonuses.length} бонуси` : 'бонус'} −{referral.bonuses[0].percent}%
            </Text>
            <Text style={styles.bonusText}>Знижка сама підставиться в кошику на наступне замовлення.</Text>
          </View>
        )}
        {referral.friends > 0 && (
          <Paragraph>
            Друзів, які вже замовили за вашим кодом: {referral.friends}.
          </Paragraph>
        )}
      </>
    );
  };

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
          1. Надішліть другу свій код.{'\n'}2. Друг вводить код у кошику під час першого замовлення й отримує −{percent}%.{'\n'}3. Коли
          замовлення друга виконано, ви отримуєте −{percent}% на одне наступне замовлення. Знижка сама з'явиться в кошику.
        </Paragraph>
        {body()}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  card: { backgroundColor: colors.red, borderRadius: 12, padding: 14 },
  title: { fontSize: 19, lineHeight: 23, fontWeight: '500', color: colors.white },
  text: { fontSize: 14, lineHeight: 19, color: colors.white, marginTop: 3 },
  codeBox: { borderWidth: 1, borderColor: colors.border, borderStyle: 'dashed', borderRadius: 12, paddingVertical: 12, alignItems: 'center' },
  code: { fontSize: 24, letterSpacing: 2, fontWeight: '600', color: colors.ink },
  actions: { flexDirection: 'row', gap: 8, marginTop: 10 },
  bonus: { borderRadius: 12, backgroundColor: colors.soft, padding: 12, marginTop: 12 },
  bonusTitle: { fontSize: 16, fontWeight: '500', color: colors.softInk },
  bonusText: { fontSize: 14, lineHeight: 19, color: colors.softInk, marginTop: 2 },
});
