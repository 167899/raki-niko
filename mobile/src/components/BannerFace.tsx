import { Image, StyleProp, StyleSheet, Text, View, ViewStyle } from 'react-native';
import type { Banner } from '../api';
import { colors } from '../theme';

/** Пропорції картинки банера: в адмінці радимо 1200×340. */
export const BANNER_RATIO = 1200 / 340;

/**
 * Лицьова сторона банера: колір або картинка й ті написи, що ввімкнені в адмінці.
 * withSubtitle={false} — опис показується під банером, як у розділі «Акції».
 */
export function BannerFace({ banner, style, withSubtitle = true }: { banner: Banner; style?: StyleProp<ViewStyle>; withSubtitle?: boolean }) {
  const show = { title: true, label: true, subtitle: true, ...banner.show };
  const label = show.label && !!banner.label;
  const title = show.title && !!banner.title;
  const subtitle = withSubtitle && show.subtitle && !!banner.subtitle;
  const text = label || title || subtitle;
  return (
    <View style={[styles.face, { backgroundColor: banner.color || colors.red }, style]}>
      {banner.image && <Image source={{ uri: banner.image }} style={StyleSheet.absoluteFill} resizeMode="cover" />}
      {/* Затемнення, щоб білий текст читався на будь-якій картинці. */}
      {banner.image && text && <View style={[StyleSheet.absoluteFill, styles.shade]} />}
      {label && <Text style={styles.small}>{banner.label}</Text>}
      {title && <Text style={styles.title}>{banner.title}</Text>}
      {subtitle && <Text style={styles.small}>{banner.subtitle}</Text>}
    </View>
  );
}

const styles = StyleSheet.create({
  face: { paddingVertical: 12, paddingHorizontal: 14, justifyContent: 'center', overflow: 'hidden' },
  shade: { backgroundColor: 'rgba(0,0,0,0.25)' },
  small: { fontSize: 14, color: colors.white },
  title: { fontSize: 19, lineHeight: 23, fontWeight: '500', color: colors.white },
});
