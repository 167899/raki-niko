import { useMemo, useState } from 'react';
import { Image, Modal, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { Button, Chip, Empty, Header, Heart, Icon, IconButton, Paragraph, ProductPicture, shared, Warn } from '../components/ui';
import { capitalize, money, quantity, unitPrice } from '../format';
import { useNav } from '../nav';
import { useShop } from '../shop';
import { colors } from '../theme';

function PhotoViewer({ uri, onClose }: { uri: string; onClose: () => void }) {
  const insets = useSafeAreaInsets();
  return (
    <Modal visible animationType="fade" statusBarTranslucent onRequestClose={onClose}>
      <View style={styles.viewer}>
        <Image source={{ uri }} style={StyleSheet.absoluteFill} resizeMode="contain" accessibilityLabel="Фото товару" />
        <IconButton label="Закрити фото" style={[styles.viewerClose, { top: insets.top + 8 }]} onPress={onClose}>
          <Icon name="x" color={colors.white} />
        </IconButton>
      </View>
    </Modal>
  );
}

export function ProductScreen({ productId }: { productId: number }) {
  const nav = useNav();
  const { catalog, productById, favorites, addToCart } = useShop();
  const [selectedId, setSelectedId] = useState(productId);
  const [flavor, setFlavor] = useState<string | null>(null);
  const [amount, setAmount] = useState<number | null>(null);
  const [added, setAdded] = useState(false);
  const [viewing, setViewing] = useState(false);

  const product = productById.get(selectedId);

  const calibers = useMemo(() => {
    if (!product?.group) return [];
    return (catalog?.products ?? [])
      .filter((item) => item.group === product.group && item.in_stock)
      .sort((a, b) => a.price - b.price);
  }, [catalog, product?.group]);

  if (!product) {
    return (
      <View style={shared.fill}>
        <Header title="Товар" />
        <Empty icon="info-circle" text="Товар більше не доступний" />
      </View>
    );
  }

  const count = amount ?? product.min;
  const photo = product.image_large ?? product.image;
  const chosen = product.flavors.find((item) => item.name === flavor) ?? product.flavors[0];
  const unitCost = product.price + (chosen?.extra ?? 0);

  const change = (update: () => void) => {
    update();
    setAdded(false);
  };

  return (
    <View style={shared.fill}>
      <Header title="Товар" right={<Heart id={product.id} on={favorites.includes(product.id)} />} />
      <ScrollView showsVerticalScrollIndicator={false}>
        {photo ? (
          <Pressable accessibilityRole="button" accessibilityLabel="Відкрити фото на весь екран" style={styles.photo} onPress={() => setViewing(true)}>
            <Image source={{ uri: photo }} style={StyleSheet.absoluteFill} resizeMode="cover" />
            <View style={styles.zoom}>
              <Icon name="zoom-in" size={20} />
            </View>
          </Pressable>
        ) : (
          <ProductPicture product={product} size={48} style={styles.picture} />
        )}
        <View style={styles.body}>
          <Text style={styles.name}>{product.name}</Text>
          {!!product.description && <Paragraph style={{ marginTop: 3 }}>{product.description}</Paragraph>}

          {calibers.length > 1 && (
            <>
              <Text style={styles.label}>Калібр</Text>
              <View style={styles.chips}>
                {calibers.map((item) => (
                  <Chip
                    key={item.id}
                    label={[capitalize(item.caliber), item.caliber_hint].filter(Boolean).join(', ')}
                    on={item.id === product.id}
                    style={styles.chip}
                    onPress={() => change(() => setSelectedId(item.id))}
                  />
                ))}
              </View>
            </>
          )}

          {product.flavors.length > 0 && (
            <>
              <Text style={styles.label}>Смак</Text>
              <View style={styles.chips}>
                {product.flavors.map((item) => (
                  <Chip
                    key={item.name}
                    label={item.extra > 0 ? `${item.name} +${unitPrice(item.extra, product.unit)}` : item.name}
                    on={item.name === chosen?.name}
                    style={styles.chip}
                    onPress={() => change(() => setFlavor(item.name))}
                  />
                ))}
              </View>
            </>
          )}

          <Text style={styles.label}>{product.unit === 'kg' ? 'Вага' : 'Кількість'}</Text>
          <View style={styles.stepper}>
            <Pressable
              accessibilityRole="button"
              accessibilityLabel="Менше"
              style={styles.step}
              onPress={() => change(() => setAmount(Math.max(product.min, count - product.step)))}
            >
              <Icon name="minus" size={16} />
            </Pressable>
            <Text style={styles.amount}>{quantity(count, product.unit)}</Text>
            <Pressable
              accessibilityRole="button"
              accessibilityLabel="Більше"
              style={styles.step}
              onPress={() => change(() => setAmount(count + product.step))}
            >
              <Icon name="plus" size={16} />
            </Pressable>
          </View>
          {product.per_kg > 0 && <Paragraph style={{ marginTop: 8 }}>Приблизно {Math.round(count * product.per_kg)} шт.</Paragraph>}
          {product.unit === 'kg' && <Warn text="Менеджер уточнить вагу і суму перед оплатою." style={{ marginTop: 10 }} />}
        </View>
      </ScrollView>
      <View style={shared.foot}>
        <View style={[shared.sum, { marginBottom: 8 }]}>
          <Text style={styles.price}>
            {unitPrice(unitCost, product.unit)}
          </Text>
          <Text style={styles.total}>{money(unitCost * count)}</Text>
        </View>
        {added ? (
          <View style={styles.actions}>
            <Button kind="outline" title="Продовжити покупки" style={{ flex: 1 }} onPress={() => nav.tab('home')} />
            <Button title="Оформити" style={{ flex: 1 }} onPress={() => nav.go({ name: 'cart' })} />
          </View>
        ) : (
          <Button
            title="Додати в кошик"
            onPress={() => {
              addToCart({ productId: product.id, flavor: chosen?.name ?? '', quantity: count });
              setAdded(true);
              nav.showToast('Додано в кошик');
            }}
          />
        )}
      </View>
      {viewing && !!photo && <PhotoViewer uri={photo} onClose={() => setViewing(false)} />}
    </View>
  );
}

const styles = StyleSheet.create({
  picture: { height: 140 },
  photo: { height: 300, backgroundColor: colors.soft },
  zoom: {
    position: 'absolute',
    right: 10,
    bottom: 10,
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: 'rgba(255,255,255,0.9)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  viewer: { flex: 1, backgroundColor: '#000000' },
  viewerClose: { position: 'absolute', right: 12, width: 40, height: 40, borderRadius: 20, backgroundColor: 'rgba(0,0,0,0.5)' },
  body: { paddingVertical: 10, paddingHorizontal: 14 },
  name: { fontSize: 17, lineHeight: 22, fontWeight: '500', color: colors.ink },
  label: { fontSize: 12, color: colors.text, marginTop: 12, marginBottom: 6 },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: 6 },
  chip: { paddingHorizontal: 12 },
  stepper: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  step: {
    width: 34,
    height: 34,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  amount: { flex: 1, textAlign: 'center', fontSize: 15, fontWeight: '500', color: colors.ink },
  price: { fontSize: 13, color: colors.text },
  total: { fontSize: 17, fontWeight: '500', color: colors.ink },
  actions: { flexDirection: 'row', gap: 8 },
});
