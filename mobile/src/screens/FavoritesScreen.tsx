import { ScrollView, StyleSheet, Text, View } from 'react-native';
import { BottomBar, Button, Empty, Header, Heart, ProductPicture, shared } from '../components/ui';
import { unitPrice } from '../format';
import { useNav } from '../nav';
import { useShop } from '../shop';
import { colors } from '../theme';

export function FavoritesScreen() {
  const nav = useNav();
  const { favorites, productById } = useShop();
  const products = favorites.flatMap((id) => {
    const product = productById.get(id);
    return product?.in_stock ? [product] : [];
  });
  return (
    <View style={shared.fill}>
      <Header title="Улюблені" />
      <ScrollView style={{ flex: 1 }} contentContainerStyle={shared.pad} showsVerticalScrollIndicator={false}>
        {products.length ? (
          products.map((product) => (
            <View key={product.id} style={styles.row}>
              <ProductPicture product={product} size={24} style={styles.thumb} />
              <View style={{ flex: 1, minWidth: 0 }}>
                <View style={{ flexDirection: 'row', gap: 4 }}>
                  <Text style={styles.name}>{product.name}</Text>
                  <Heart id={product.id} on />
                </View>
                <Text style={styles.price}>{unitPrice(product.price, product.unit)}</Text>
                <Button size="sm" title="Обрати" onPress={() => nav.go({ name: 'product', productId: product.id })} />
              </View>
            </View>
          ))
        ) : (
          <Empty icon="heart" text="Тут будуть ваші улюблені товари" />
        )}
      </ScrollView>
      <BottomBar />
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', gap: 9, borderWidth: 1, borderColor: colors.line, borderRadius: 12, padding: 9, marginBottom: 8 },
  thumb: { width: 58, height: 58, borderRadius: 9 },
  name: { flex: 1, fontSize: 15, lineHeight: 19, fontWeight: '500', color: colors.ink },
  price: { fontSize: 15, fontWeight: '500', color: colors.ink, marginTop: 2, marginBottom: 6 },
});
