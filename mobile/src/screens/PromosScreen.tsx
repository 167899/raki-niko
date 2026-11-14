import * as Clipboard from 'expo-clipboard';
import { useRef } from 'react';
import { Pressable, ScrollView, ScrollViewInstance, StyleSheet, Text, View } from 'react-native';
import { BannerFace, BANNER_RATIO } from '../components/BannerFace';
import { BottomBar, Empty, Header, shared } from '../components/ui';
import { useNav } from '../nav';
import { useShop } from '../shop';
import { colors } from '../theme';

export function PromosScreen() {
  const nav = useNav();
  const { banners } = useShop();
  // Відкрили з банера на Головній — прокручуємо до цієї акції.
  const target = nav.route.name === 'promos' ? nav.route.bannerId : undefined;
  const scroller = useRef<ScrollViewInstance>(null);
  const scrolled = useRef(false);

  const copy = async (code: string) => {
    await Clipboard.setStringAsync(code);
    nav.showToast(`Скопійовано: ${code}`);
  };

  return (
    <View style={shared.fill}>
      <Header title="Акції" />
      <ScrollView ref={scroller} style={{ flex: 1 }} contentContainerStyle={shared.pad} showsVerticalScrollIndicator={false}>
        {banners.length ? (
          banners.map((banner) => {
            // Повний опис — тут, короткий — на банері.
            const about = banner.details?.trim() || banner.subtitle;
            return (
              <View
                key={banner.id}
                style={styles.card}
                onLayout={(event) => {
                  if (banner.id !== target || scrolled.current) return;
                  scrolled.current = true;
                  scroller.current?.scrollTo({ y: Math.max(0, event.nativeEvent.layout.y - 8), animated: true });
                }}
              >
                <BannerFace banner={banner} withSubtitle={false} style={banner.image ? { aspectRatio: BANNER_RATIO } : styles.head} />
                {(!!about || !!banner.code) && (
                  <View style={styles.foot}>
                    <Text style={styles.text}>
                      {about}
                      {!!about && !!banner.code && '\n'}
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
            );
          })
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
  foot: { flexDirection: 'row', alignItems: 'center', gap: 8, paddingVertical: 8, paddingHorizontal: 10 },
  text: { flex: 1, fontSize: 14, lineHeight: 19, color: colors.text },
  code: { color: colors.ink, fontWeight: '500' },
  copy: { height: 32, borderWidth: 1, borderColor: colors.border, borderRadius: 10, paddingHorizontal: 10, alignItems: 'center', justifyContent: 'center' },
  copyText: { fontSize: 14, color: colors.text },
});
