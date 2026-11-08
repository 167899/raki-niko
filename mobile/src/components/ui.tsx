import { ReactNode, useState } from 'react';
import {
  ActivityIndicator,
  Image,
  Platform,
  Pressable,
  StyleProp,
  StyleSheet,
  Text,
  TextInput,
  TextInputProps,
  TextStyle,
  View,
  ViewStyle,
} from 'react-native';
import Svg, { Path } from 'react-native-svg';
import { Product } from '../api';
import { filledIcons, IconName, outlineIcons } from '../icons';
import { Tab, useNav } from '../nav';
import { useShop } from '../shop';
import { colors, gutter } from '../theme';

export function Icon({ name, size = 20, color = colors.ink }: { name: IconName; size?: number; color?: string }) {
  const filled = name in filledIcons;
  const paths: readonly string[] = filled
    ? filledIcons[name as keyof typeof filledIcons]
    : outlineIcons[name as keyof typeof outlineIcons];
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24">
      {paths.map((d) =>
        filled ? (
          <Path key={d} d={d} fill={color} />
        ) : (
          <Path key={d} d={d} fill="none" stroke={color} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" />
        ),
      )}
    </Svg>
  );
}

export const Logo = ({ width, height }: { width: number; height: number }) => (
  <Image
    source={require('../../assets/brand/logo.png')}
    accessibilityLabel="Логотип Raki Niko"
    style={{ width, height }}
    resizeMode="contain"
  />
);

export function Header({ title, right, back = true }: { title: string; right?: ReactNode; back?: boolean }) {
  const nav = useNav();
  return (
    <View style={styles.header}>
      {back && (
        <IconButton label="Назад" onPress={nav.back}>
          <Icon name="arrow-left" />
        </IconButton>
      )}
      <Text style={styles.headerTitle} numberOfLines={1}>
        {title}
      </Text>
      {right}
    </View>
  );
}

export function IconButton({
  label,
  onPress,
  children,
  style,
}: {
  label: string;
  onPress: () => void;
  children: ReactNode;
  style?: StyleProp<ViewStyle>;
}) {
  return (
    <Pressable accessibilityRole="button" accessibilityLabel={label} hitSlop={6} onPress={onPress} style={[styles.iconButton, style]}>
      {children}
    </Pressable>
  );
}

export const Badge = ({ count }: { count: number }) =>
  count > 0 ? (
    <View style={styles.badge}>
      <Text style={styles.badgeText}>{count}</Text>
    </View>
  ) : null;

export function Heart({ on, id, size = 21 }: { on: boolean; id: number; size?: number }) {
  const { toggleFavorite } = useShop();
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={on ? 'Прибрати з улюбленого' : 'В улюблене'}
      accessibilityState={{ selected: on }}
      onPress={() => toggleFavorite(id)}
      style={styles.fav}
    >
      {on ? <Icon name="heart-filled" size={24} color={colors.red} /> : <Icon name="heart" size={size} color={colors.text} />}
    </Pressable>
  );
}

const TABS: { id: Tab; icon: IconName; label: string }[] = [
  { id: 'home', icon: 'home', label: 'Головна' },
  { id: 'promos', icon: 'discount', label: 'Акції' },
  { id: 'cart', icon: 'shopping-cart', label: 'Кошик' },
  { id: 'delivery', icon: 'truck-delivery', label: 'Доставка' },
];

export function BottomBar({ active }: { active?: Tab }) {
  const nav = useNav();
  const { lines } = useShop();
  return (
    <View style={styles.bar}>
      {TABS.map((tab) => {
        const on = tab.id === active;
        const color = on ? colors.red : colors.text;
        return (
          <Pressable key={tab.id} accessibilityRole="button" onPress={() => nav.tab(tab.id)} style={styles.nav}>
            {tab.id === 'cart' ? (
              <View style={styles.cart}>
                <Icon name="shopping-cart" size={23} color={colors.white} />
                {lines.length > 0 && (
                  <View style={styles.cartCount} accessibilityLabel={`Товарів у кошику: ${lines.length}`}>
                    <Text style={styles.cartCountText}>{lines.length}</Text>
                  </View>
                )}
              </View>
            ) : (
              <View style={styles.navIcon}>
                <Icon name={tab.icon} color={color} />
              </View>
            )}
            <Text style={[styles.navText, { color }]}>{tab.label}</Text>
          </Pressable>
        );
      })}
      <Pressable accessibilityRole="button" onPress={() => nav.setOverlay('ask')} style={styles.nav}>
        <View style={styles.navIcon}>
          <Icon name="message-circle" color={colors.text} />
        </View>
        <Text style={styles.navText}>Запитати</Text>
      </Pressable>
    </View>
  );
}

export function Button({
  title,
  onPress,
  size = 'lg',
  kind = 'primary',
  loading,
  style,
}: {
  title: string;
  onPress: () => void;
  size?: 'lg' | 'sm';
  kind?: 'primary' | 'outline';
  loading?: boolean;
  style?: StyleProp<ViewStyle>;
}) {
  const primary = kind === 'primary';
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{ disabled: !!loading, busy: !!loading }}
      disabled={loading}
      onPress={onPress}
      style={[styles.button, size === 'sm' ? styles.buttonSm : styles.buttonLg, !primary && styles.buttonOutline, style]}
    >
      {loading ? (
        <ActivityIndicator color={colors.white} />
      ) : (
        <Text
          style={[
            size === 'sm' ? styles.buttonTextSm : styles.buttonTextLg,
            primary ? styles.buttonTextPrimary : styles.buttonTextOutline,
          ]}
        >
          {title}
        </Text>
      )}
    </Pressable>
  );
}

export function Chip({ label, on, onPress, style }: { label: string; on: boolean; onPress: () => void; style?: StyleProp<ViewStyle> }) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{ selected: on }}
      onPress={onPress}
      style={[styles.chip, on && styles.chipOn, style]}
    >
      <Text numberOfLines={1} style={[styles.chipText, on && styles.chipTextOn]}>
        {label}
      </Text>
    </Pressable>
  );
}

/**
 * Рамка й тінь фокусу живуть на обгортці: на Android зміна рамки чи тіні самого TextInput
 * замінює його фон, і відступи тексту скидаються.
 */
type InputProps = Omit<TextInputProps, 'style'> & { style?: StyleProp<ViewStyle>; inset?: number };

export function Input({ style, inset = 10, onFocus, onBlur, ...props }: InputProps) {
  const [focused, setFocused] = useState(false);
  return (
    <View style={[styles.inputBox, focused && styles.inputFocused, style]}>
      <TextInput
        placeholderTextColor={colors.text}
        {...props}
        onFocus={(event) => {
          setFocused(true);
          onFocus?.(event);
        }}
        onBlur={(event) => {
          setFocused(false);
          onBlur?.(event);
        }}
        style={[styles.input, { paddingLeft: inset }, props.multiline && styles.inputMultiline]}
      />
    </View>
  );
}

export function Field({ label, error, labelStyle, ...props }: InputProps & { label: string; error?: string; labelStyle?: StyleProp<TextStyle> }) {
  return (
    <View>
      <Text style={[styles.fieldLabel, labelStyle]}>{label}</Text>
      <Input accessibilityLabel={label} {...props} />
      {!!error && <Text style={styles.error}>{error}</Text>}
    </View>
  );
}

export const FieldLabel = ({ children, style }: { children: ReactNode; style?: StyleProp<TextStyle> }) => (
  <Text style={[styles.fieldLabel, style]}>{children}</Text>
);

export const ErrorText = ({ children, color }: { children: ReactNode; color?: string }) => (
  <Text style={[styles.error, color ? { color } : null]}>{children}</Text>
);

export const Warn = ({ text, style }: { text: string; style?: StyleProp<ViewStyle> }) => (
  <View style={[styles.warn, style]}>
    <Text style={styles.warnText}>{text}</Text>
  </View>
);

/** Біла кнопка-рядок з іконкою: точки самовивозу, телефони, месенджери. */
export function RowButton({
  icon,
  label,
  on,
  onPress,
  style,
}: {
  icon: IconName;
  label: string;
  on?: boolean;
  onPress: () => void;
  style?: StyleProp<ViewStyle>;
}) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{ selected: !!on }}
      onPress={onPress}
      style={[styles.rowButton, on && styles.rowButtonOn, style]}
    >
      <Icon name={icon} size={18} color={colors.red} />
      <Text style={styles.rowButtonText}>{label}</Text>
    </Pressable>
  );
}

export const Heading = ({ children, style }: { children: ReactNode; style?: StyleProp<TextStyle> }) => (
  <Text accessibilityRole="header" style={[styles.heading, style]}>
    {children}
  </Text>
);

export const Paragraph = ({ children, style }: { children: ReactNode; style?: StyleProp<TextStyle> }) => (
  <Text style={[styles.paragraph, style]}>{children}</Text>
);

export const InfoRow = ({ left, right }: { left: ReactNode; right: string }) => (
  <View style={styles.infoRow}>
    <Text style={styles.infoText}>{left}</Text>
    <Text style={styles.infoText}>{right}</Text>
  </View>
);

export function Empty({ icon, text, children }: { icon: IconName; text: string; children?: ReactNode }) {
  return (
    <View style={styles.empty}>
      <Icon name={icon} size={40} color={colors.red} />
      <Text style={styles.emptyText}>{text}</Text>
      {children}
    </View>
  );
}

export function Loading() {
  return (
    <View style={styles.loading}>
      <ActivityIndicator color={colors.red} />
    </View>
  );
}

/** Фото товару або іконка на м'якому фоні, поки фото немає. */
export function ProductPicture({ product, size, style }: { product: Product; size: number; style: StyleProp<ViewStyle> }) {
  const { catalog } = useShop();
  const slug = catalog?.categories.find((category) => category.id === product.category_id)?.slug;
  if (product.image) {
    return (
      <View style={[styles.picture, style]}>
        <Image source={{ uri: product.image }} style={StyleSheet.absoluteFill} resizeMode="cover" accessibilityIgnoresInvertColors />
      </View>
    );
  }
  return (
    <View style={[styles.picture, style]}>
      <Icon name={slug === 'zhyvi' ? 'droplet' : 'flame'} size={size} color={colors.red} />
    </View>
  );
}

export const shared = StyleSheet.create({
  fill: { flex: 1, backgroundColor: colors.white },
  pad: { paddingVertical: 10, paddingHorizontal: gutter },
  foot: { paddingTop: 10, paddingHorizontal: 14, paddingBottom: 14, borderTopWidth: 1, borderTopColor: colors.line, backgroundColor: colors.white },
  row: { flexDirection: 'row', alignItems: 'center' },
  sum: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  card: { borderWidth: 1, borderColor: colors.line, borderRadius: 12 },
});

const styles = StyleSheet.create({
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 9,
    paddingHorizontal: gutter,
    borderBottomWidth: 1,
    borderBottomColor: colors.line,
    backgroundColor: colors.white,
  },
  headerTitle: { flex: 1, minWidth: 0, fontSize: 17, fontWeight: '500', color: colors.ink, paddingVertical: 6, paddingHorizontal: 2 },
  iconButton: { width: 30, height: 30, alignItems: 'center', justifyContent: 'center' },
  badge: {
    position: 'absolute',
    top: 1,
    right: 1,
    minWidth: 16,
    borderRadius: 8,
    backgroundColor: colors.red,
    paddingHorizontal: 2,
  },
  badgeText: { color: colors.white, fontSize: 13, lineHeight: 16, textAlign: 'center' },
  fav: { width: 32, height: 32, alignItems: 'center', justifyContent: 'center' },
  bar: {
    flexDirection: 'row',
    alignItems: 'flex-end',
    borderTopWidth: 1,
    borderTopColor: colors.barLine,
    backgroundColor: colors.white,
    paddingHorizontal: 2,
    zIndex: 2,
  },
  nav: { flex: 1, minWidth: 0, alignItems: 'center', paddingTop: 6, paddingBottom: 9 },
  navIcon: { marginBottom: 2 },
  navText: { fontSize: 13, color: colors.text, textAlign: 'center' },
  cart: {
    width: 46,
    height: 46,
    borderRadius: 23,
    backgroundColor: colors.red,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: -20,
    marginBottom: 3,
    borderWidth: 3,
    borderColor: colors.white,
  },
  cartCount: {
    position: 'absolute',
    top: -7,
    right: -7,
    minWidth: 21,
    height: 21,
    borderRadius: 11,
    borderWidth: 1.5,
    borderColor: colors.red,
    backgroundColor: colors.white,
    paddingHorizontal: 3,
    alignItems: 'center',
    justifyContent: 'center',
  },
  cartCountText: { fontSize: 13, lineHeight: 16, fontWeight: '500', color: colors.red },
  button: { backgroundColor: colors.red, borderRadius: 8, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 8 },
  buttonLg: { height: 46 },
  buttonSm: { height: 32 },
  buttonOutline: { backgroundColor: colors.white, borderWidth: 1, borderColor: colors.border },
  buttonTextLg: { fontSize: 16, fontWeight: '500' },
  buttonTextSm: { fontSize: 15 },
  buttonTextPrimary: { color: colors.white, fontWeight: '500' },
  buttonTextOutline: { color: colors.text, fontWeight: '400' },
  chip: {
    height: 32,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.white,
    paddingHorizontal: 13,
    alignItems: 'center',
    justifyContent: 'center',
  },
  chipOn: { backgroundColor: colors.red, borderColor: colors.red },
  chipText: { fontSize: 15, color: colors.text },
  chipTextOn: { color: colors.white },
  inputBox: { height: 38, borderWidth: 1, borderColor: colors.border, borderRadius: 10, backgroundColor: colors.white },
  input: {
    flex: 1,
    paddingRight: 10,
    paddingVertical: 0,
    textAlignVertical: 'center',
    includeFontPadding: false,
    fontSize: 16,
    color: colors.ink,
    // Веб-перегляд: прибираємо рамку браузера, фокус показує власна тінь.
    ...(Platform.OS === 'web' ? ({ outlineStyle: 'none' } as object) : null),
  },
  inputMultiline: { textAlignVertical: 'top', paddingVertical: 8 },
  inputFocused: {
    borderColor: colors.focus,
    boxShadow: '0 0 0 3px rgba(217,43,32,0.08), 0 2px 10px rgba(217,43,32,0.14)',
  },
  fieldLabel: { fontSize: 14, color: colors.text, marginTop: 10, marginBottom: 4 },
  error: { fontSize: 14, color: colors.error, marginTop: 3 },
  warn: { backgroundColor: colors.warnBg, borderRadius: 10, paddingVertical: 9, paddingHorizontal: 10 },
  warnText: { color: colors.warnInk, fontSize: 14, lineHeight: 19 },
  rowButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    height: 44,
    borderWidth: 1,
    borderColor: colors.barLine,
    borderRadius: 10,
    paddingHorizontal: 12,
    marginTop: 8,
    backgroundColor: colors.white,
  },
  rowButtonOn: { borderColor: colors.red },
  rowButtonText: { fontSize: 16, color: colors.text },
  heading: { fontSize: 16, fontWeight: '500', color: colors.ink, marginTop: 12, marginBottom: 5 },
  paragraph: { fontSize: 14, color: colors.text, lineHeight: 20 },
  infoRow: { flexDirection: 'row', justifyContent: 'space-between', gap: 8, paddingVertical: 3 },
  infoText: { fontSize: 14, color: colors.text, flexShrink: 1 },
  empty: { alignItems: 'center', paddingTop: 120, paddingHorizontal: 20 },
  emptyText: { fontSize: 16, color: colors.text, marginTop: 8, textAlign: 'center' },
  loading: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  picture: { backgroundColor: colors.soft, alignItems: 'center', justifyContent: 'center', overflow: 'hidden' },
});
