import { useEffect, useMemo, useRef, useState } from 'react';
import { LayoutChangeEvent, Pressable, ScrollView, ScrollViewInstance, StyleSheet, Text, View } from 'react-native';
import { Banner, Product } from '../api';
import { BannerFace } from '../components/BannerFace';
import { Badge, BottomBar, Button, Chip, Empty, Heart, Icon, IconButton, Input, Loading, Logo, ProductPicture, shared } from '../components/ui';
import { unitPrice } from '../format';
import { useNav } from '../nav';
import { useShop } from '../shop';
import { colors, gutter } from '../theme';

const AUTO_SLIDE_MS = 4000;
const PAUSE_AFTER_TOUCH_MS = 6000;

function Banners({ banners }: { banners: Banner[] }) {
  const nav = useNav();
  const scroller = useRef<ScrollViewInstance>(null);
  const touched = useRef(0);
  const [width, setWidth] = useState(0);
  const [active, setActive] = useState(0);
  const blocked = nav.overlay !== null;

  useEffect(() => {
    if (banners.length < 2 || !width || blocked) return;
    const timer = setInterval(() => {
      if (Date.now() - touched.current < PAUSE_AFTER_TOUCH_MS) return;
      setActive((current) => {
        const next = (current + 1) % banners.length;
        scroller.current?.scrollTo({ x: next * width, animated: true });
        return next;
      });
    }, AUTO_SLIDE_MS);
    return () => clearInterval(timer);
  }, [banners.length, width, blocked]);

  if (!banners.length) return null;

  const show = (index: number) => {
    touched.current = Date.now();
    scroller.current?.scrollTo({ x: index * width, animated: true });
    setActive(index);
  };

  return (
    <View style={{ paddingHorizontal: gutter }}>
      <View style={styles.bannerFrame} onLayout={(event: LayoutChangeEvent) => setWidth(event.nativeEvent.layout.width)}>
        <ScrollView
          ref={scroller}
          horizontal
          pagingEnabled
          showsHorizontalScrollIndicator={false}
          scrollEventThrottle={32}
          onScrollBeginDrag={() => {
            touched.current = Date.now();
          }}
          onScroll={(event) => {
            if (width && Date.now() - touched.current < PAUSE_AFTER_TOUCH_MS) {
              setActive(Math.round(event.nativeEvent.contentOffset.x / width));
            }
          }}
        >
          {banners.map((banner) => (
            <BannerFace key={banner.id} banner={banner} style={[styles.banner, { width }]} />
          ))}
        </ScrollView>
      </View>
      <View style={styles.dots}>
        {banners.map((banner, index) => (
          <Pressable key={banner.id} accessibilityRole="button" accessibilityLabel={`Банер ${index + 1}`} style={styles.dotHit} onPress={() => show(index)}>
            <View style={[styles.dot, index === active && styles.dotOn]} />
          </Pressable>
        ))}
      </View>
    </View>
  );
}

/** Картка веде на товар; для підкатегорії це найдешевший калібр, інші обираються в товарі. */
type Card = { product: Product; title: string; fromPrice: boolean };

/** Усі: кожен товар окремо. Категорія: одна картка на підкатегорію. */
function buildCards(products: Product[], grouped: boolean): Card[] {
  if (!grouped) return products.map((product) => ({ product, title: product.name, fromPrice: false }));
  const groups = new Map<string, Product[]>();
  for (const product of products) {
    const key = product.group || `product-${product.id}`;
    groups.set(key, [...(groups.get(key) ?? []), product]);
  }
  return [...groups.values()].map((items) => {
    const cheapest = items.reduce((best, item) => (item.price < best.price ? item : best));
    return { product: cheapest, title: cheapest.group || cheapest.name, fromPrice: items.length > 1 };
  });
}

function ProductCard({ card: { product, title, fromPrice } }: { card: Card }) {
  const nav = useNav();
  const { favorites } = useShop();
  return (
    <View style={styles.card}>
      <ProductPicture product={product} size={44} style={styles.pic} />
      <View style={styles.cardHeart}>
        <Heart id={product.id} on={favorites.includes(product.id)} />
      </View>
      <View style={styles.cardBody}>
        <Text style={styles.cardName}>{title}</Text>
        <Text style={styles.cardPrice}>
          {fromPrice ? 'від ' : ''}
          {unitPrice(product.price, product.unit)}
        </Text>
        <Button size="sm" title="Обрати" onPress={() => nav.go({ name: 'product', productId: product.id })} />
      </View>
    </View>
  );
}

export function HomeScreen() {
  const nav = useNav();
  const { catalog, config, banners, favorites, unread, loadError, reload } = useShop();
  const [search, setSearch] = useState('');
  const [category, setCategory] = useState<number | null>(null);

  const cards = useMemo(() => {
    const query = search.trim().toLowerCase();
    const found = (catalog?.products ?? []).filter(
      (product) =>
        product.in_stock && (category === null || product.category_id === category) && product.name.toLowerCase().includes(query),
    );
    return buildCards(found, category !== null);
  }, [catalog, search, category]);

  return (
    <View style={shared.fill}>
      <View style={styles.top}>
        <Logo width={30} height={36} />
        <Text style={styles.brand}>Raki Niko</Text>
        <IconButton label="Улюблене" onPress={() => nav.go({ name: 'favorites' })}>
          <Icon name="heart" />
          <Badge count={favorites.length} />
        </IconButton>
        <IconButton label="Сповіщення" onPress={() => nav.go({ name: 'notifications' })}>
          <Icon name="bell" />
          <Badge count={unread} />
        </IconButton>
        <IconButton label="Відкрити меню" style={styles.profile} onPress={() => nav.setOverlay('menu')}>
          <Icon name="user-circle" />
          <Icon name="chevron-down" size={14} />
        </IconButton>
      </View>
      <View style={styles.search}>
        <View style={styles.searchIcon} pointerEvents="none">
          <Icon name="search" size={18} color={colors.text} />
        </View>
        <Input
          accessibilityLabel="Швидкий пошук"
          placeholder="Швидкий пошук"
          value={search}
          onChangeText={setSearch}
          inset={34}
          style={{ height: 36 }}
        />
      </View>
      {!catalog ? (
        loadError ? (
          <View style={{ flex: 1, paddingHorizontal: gutter }}>
            <Empty icon="info-circle" text={loadError}>
              <Button size="sm" title="Спробувати ще раз" onPress={reload} style={{ width: 170, marginTop: 14 }} />
            </Empty>
          </View>
        ) : (
          <Loading />
        )
      ) : (
        <ScrollView showsVerticalScrollIndicator={false} keyboardShouldPersistTaps="handled">
          {config?.banners_on !== false && <Banners banners={banners} />}
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.cats} keyboardShouldPersistTaps="handled">
            <Chip label="Усі" on={category === null} onPress={() => setCategory(null)} />
            {catalog.categories.map((item) => (
              <Chip key={item.id} label={item.name} on={item.id === category} onPress={() => setCategory(item.id)} />
            ))}
          </ScrollView>
          {cards.length ? (
            <View style={styles.grid}>
              {cards.map((card) => (
                <ProductCard key={card.product.id} card={card} />
              ))}
            </View>
          ) : (
            <Text style={styles.nothing}>Нічого не знайдено</Text>
          )}
        </ScrollView>
      )}
      <BottomBar active="home" />
    </View>
  );
}

const styles = StyleSheet.create({
  top: { flexDirection: 'row', alignItems: 'center', gap: 4, paddingVertical: 9, paddingHorizontal: gutter },
  brand: { flex: 1, fontSize: 17, fontWeight: '500', color: colors.ink },
  profile: { width: 'auto', flexDirection: 'row', gap: 1, paddingHorizontal: 2 },
  search: { paddingHorizontal: gutter, paddingBottom: 8 },
  searchIcon: { position: 'absolute', left: gutter + 10, top: 9, zIndex: 1 },
  bannerFrame: { borderRadius: 12, overflow: 'hidden' },
  banner: { height: 106 },
  dots: { flexDirection: 'row', justifyContent: 'center', gap: 5, paddingVertical: 6 },
  dotHit: { width: 18, height: 18, alignItems: 'center', justifyContent: 'center' },
  dot: { width: 8, height: 8, borderRadius: 4, backgroundColor: '#AAAAAA' },
  dotOn: { backgroundColor: colors.red },
  cats: { flexDirection: 'row', gap: 6, paddingHorizontal: gutter, paddingBottom: 8 },
  grid: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'space-between', rowGap: 8, paddingHorizontal: gutter, paddingBottom: 10 },
  card: { width: '48.8%', borderWidth: 1, borderColor: colors.line, borderRadius: 12, overflow: 'hidden' },
  // Квадратне фото: висота дорівнює ширині картки.
  pic: { aspectRatio: 1 },
  cardHeart: { position: 'absolute', zIndex: 1, top: 3, right: 3 },
  cardBody: { paddingTop: 7, paddingHorizontal: 8, paddingBottom: 8 },
  // Місце під два рядки назви: тоді фото в сусідніх картках однакові.
  cardName: { minHeight: 36, fontSize: 14, lineHeight: 18, fontWeight: '600', color: colors.ink },
  cardPrice: { fontSize: 15, fontWeight: '500', color: colors.ink, marginTop: 3, marginBottom: 5 },
  nothing: { textAlign: 'center', fontSize: 15, color: colors.text, paddingVertical: 24 },
});
