import * as Clipboard from 'expo-clipboard';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { BottomBar, Empty, Header, shared } from '../components/ui';
import { useNav } from '../nav';
import { useShop } from '../shop';
import { colors } from '../theme';

export function PromosScreen() {
  const nav = useNav();
  const { banners } = useShop();

  const copy = async (code: string) => {
    await Clipboard.setStringAsync(code);
    nav.showToast(`Скопійовано: ${code}`);
  };

  return (
    <View style={shared.fill}>
      <Header title="Акції" />
      <ScrollView style={{ flex: 1 }} contentContainerStyle={shared.pad} showsVerticalScrollIndicator={false}>
        {banners.length ? (
          banners.map((banner) => (
            <View key={banner.id} style={styles.card}>
              <View style={[styles.head, { backgroundColor: banner.color || colors.red }]}>
                {!!banner.label && <Text style={styles.label}>{banner.label}</Text>}
                <Text style={styles.title}>{banner.title}</Text>
              </View>
              {(!!banner.subtitle || !!banner.code) && (
                <View style={styles.foot}>
                  <Text style={styles.text}>
                    {banner.subtitle}
                    {!!banner.subtitle && !!banner.code && '\n'}
                    {!!banner.code && 'Код: '}
                    {!!banner.code && <Text style={styles.code}>{banner.code}</Text>}
                  </Text>
                  {!!banner.code && (
                    <Pressable accessibilityRole="button" style={styles.copy} onPress={() => copy(banner.code!)}>
                      <Text style={styles.copyText}>Копіювати</Text>
                    </Pressable>
                  )}
                </View>
              )}
            </View>
          ))
        ) : (
          <Empty icon="discount" text="Зараз акцій немає" />
        )}
      </ScrollView>
      <BottomBar active="promos" />
    </View>
  );
}

const styles = StyleSheet.create({
  card: { borderWidth: 1, borderColor: colors.line, borderRadius: 12, overflow: 'hidden', marginBottom: 8 },
  head: { paddingVertical: 10, paddingHorizontal: 12 },
  label: { fontSize: 12, color: colors.white },
  title: { fontSize: 15, lineHeight: 19, fontWeight: '500', color: colors.white },
  foot: { flexDirection: 'row', alignItems: 'center', gap: 8, paddingVertical: 8, paddingHorizontal: 10 },
  text: { flex: 1, fontSize: 12, lineHeight: 17, color: colors.text },
  code: { color: colors.ink, fontWeight: '500' },
  copy: { height: 32, borderWidth: 1, borderColor: colors.border, borderRadius: 10, paddingHorizontal: 10, alignItems: 'center', justifyContent: 'center' },
  copyText: { fontSize: 12, color: colors.text },
});
