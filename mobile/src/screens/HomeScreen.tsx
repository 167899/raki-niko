import { useEffect, useMemo, useRef, useState } from 'react';
import { LayoutChangeEvent, NativeScrollEvent, NativeSyntheticEvent, Pressable, ScrollView, ScrollViewInstance, StyleSheet, Text, View } from 'react-native';
import { Banner, Product, Subcategory } from '../api';
import { BannerFace, useBannerRatios } from '../components/BannerFace';
import { Badge, BottomBar, Button, Chip, Empty, Heart, Icon, IconButton, Input, Loading, Logo, ProductPicture, shared } from '../components/ui';
import { unitPrice } from '../format';
import { useNav } from '../nav';
import { fuzzyMatch } from '../search';
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
  // Висота слайдера — за найвищою картинкою, щоб жодну не обрізати; банери без картинки — 106.
  const ratios = useBannerRatios(banners);
  const height = Math.max(106, ...banners.map((banner) => (banner.image && ratios[banner.id] && width ? width / ratios[banner.id] : 0)));

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
            <Pressable
              key={banner.id}
              accessibilityRole="button"
              accessibilityLabel={`Акція: ${banner.title}`}
              onPress={() => nav.go({ name: 'promos', bannerId: banner.id })}
            >
              <BannerFace banner={banner} style={{ width, height }} />
            </Pressable>
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

/**
 * Картка веде на товар; для підкатегорії це найдешевший калібр, інші обираються в товарі.
 * folder — картка папки («Каліфорнія»): відкриває екран з її картками.
 */
export type Card = { product: Product; title: string; fromPrice: boolean; folder?: Subcategory };

/** Одна картка на підкатегорію, як на сайті. */
function buildCards(products: Product[]): Card[] {
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

/**
 * Картки розділу чи папки в порядку товарів: папка — одна картка на всі свої товари, решта — як buildCards.
 * folderId 0 — сам розділ.
 */
export function buildCardsAt(products: Product[], subcategories: Subcategory[], folderId = 0): Card[] {
  const entries = new Map<string, Product[]>();
  for (const product of products) {
    let path = product.path ?? [];
    if (folderId) {
      const at = path.indexOf(folderId);
      if (at < 0) continue;
      path = path.slice(at + 1);
    }
    const key = path.length ? `folder-${path[0]}` : product.group ? `group-${product.group}` : `product-${product.id}`;
    entries.set(key, [...(entries.get(key) ?? []), product]);
  }
  return [...entries.entries()].map(([key, items]) => {
    const folder = key.startsWith('folder-') ? subcategories.find((sub) => `folder-${sub.id}` === key) : undefined;
    if (!folder) return buildCards(items)[0];
    const cheapest = items.reduce((best, item) => (item.price < best.price ? item : best));
    return { product: cheapest, title: folder.name, fromPrice: items.length > 1, folder };
  });
}

export function ProductCard({ card: { product, title, fromPrice, folder } }: { card: Card }) {
  const nav = useNav();
  const { favorites } = useShop();
  return (
    <View style={styles.card}>
      <ProductPicture product={product} size={44} style={styles.pic} />
      {!folder && (
        <View style={styles.cardHeart}>
          <Heart id={product.id} on={favorites.includes(product.id)} />
        </View>
      )}
      <View style={styles.cardBody}>
        <Text style={styles.cardName}>{title}</Text>
        <Text style={styles.cardPrice}>
          {fromPrice ? 'від ' : ''}
          {unitPrice(product.price, product.unit)}
        </Text>
        <Button size="sm" title="Обрати" style={styles.cardButton} onPress={() => nav.go(folder ? { name: 'folder', folderId: folder.id } : { name: 'product', productId: product.id })}
        />
      </View>
    </View>
  );
}

/** Меню як на сайті: усі категорії підряд, стрічка категорій гортає до розділу й підсвічує той, що зараз видно. */
type Section = { id: number; name: string; cards: Card[] };
const JUMP_LOCK_MS = 700;

export function HomeScreen() {
  const nav = useNav();
  const { catalog, config, banners, favorites, unread, loadError, reload } = useShop();
  const [search, setSearch] = useState('');
  const [active, setActive] = useState<number | null>(null);
  const page = useRef<ScrollViewInstance>(null);
  const strip = useRef<ScrollViewInstance>(null);
  const stripWidth = useRef(0);
  const barHeight = useRef(0);
  const sectionY = useRef<Record<number, number>>({});
  const chipX = useRef<Record<string, { x: number; width: number }>>({});
  const jumping = useRef(0);

  const sections = useMemo<Section[]>(() => {
    const subcategories = catalog?.subcategories ?? [];
    const folderNames = (product: Product) => (product.path ?? []).map((id) => subcategories.find((sub) => sub.id === id)?.name ?? '');
    const products = (catalog?.products ?? []).filter((product) => product.in_stock);
    return (catalog?.categories ?? []).flatMap((category) => {
      const found = products.filter(
        (product) =>
          product.category_id === category.id &&
          fuzzyMatch(search, [product.name, product.group, ...folderNames(product), category.name].filter(Boolean).join(' ')),
      );
      if (!found.length) return [];
      // Під час пошуку знайдені товари видно одразу, без заходу в папку.
      const cards = search.trim() ? buildCards(found) : buildCardsAt(found, subcategories);
      return [{ id: category.id, name: category.name, cards }];
    });
  }, [catalog, search]);

  // Активна категорія завжди видна в стрічці: стрічка сама гортається вліво чи вправо.
  const highlight = (id: number | null) => {
    setActive((current) => {
      if (current !== id) {
        const chip = chipX.current[String(id ?? 'all')];
        if (chip) strip.current?.scrollTo({ x: Math.max(0, chip.x - (stripWidth.current - chip.width) / 2), animated: true });
      }
      return id;
    });
  };

  const jump = (id: number | null) => {
    jumping.current = Date.now();
    highlight(id);
    const y = id === null ? 0 : (sectionY.current[id] ?? 0) - barHeight.current;
    page.current?.scrollTo({ y: Math.max(0, y), animated: true });
  };

  const onScroll = (event: NativeSyntheticEvent<NativeScrollEvent>) => {
    if (Date.now() - jumping.current < JUMP_LOCK_MS) return;
    const y = event.nativeEvent.contentOffset.y;
    if (y < 40) return highlight(null);
    // Поточний розділ — останній, чий заголовок уже під стрічкою категорій (з запасом у третину екрана).
    const line = y + barHeight.current + event.nativeEvent.layoutMeasurement.height * 0.3;
    let current: number | null = null;
    for (const section of sections) {
      if ((sectionY.current[section.id] ?? Infinity) <= line) current = section.id;
    }
    highlight(current);
  };

  const chip = (key: string, label: string, id: number | null) => (
    <View
      key={key}
      onLayout={(event: LayoutChangeEvent) => {
        chipX.current[key] = { x: event.nativeEvent.layout.x, width: event.nativeEvent.layout.width };
      }}
    >
      <Chip label={label} on={active === id} onPress={() => jump(id)} />
    </View>
  );

  return (
    <View style={shared.fill}>
      <View style={styles.top}>
        <Logo width={30} height={36} />
        <Text style={styles.brand}>Raki Niko</Text>
        <IconButton label="Мої замовлення" onPress={() => nav.go({ name: 'orders' })}>
          <Icon name="receipt" />
        </IconButton>
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
          style={{ height: 36, paddingRight: search ? 30 : 0 }}
        />
        {!!search && (
          <Pressable accessibilityRole="button" accessibilityLabel="Очистити пошук" hitSlop={8} style={styles.searchClear} onPress={() => setSearch('')}>
            <Icon name="x" size={18} color={colors.text} />
          </Pressable>
        )}
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
        // Категорії — другий дочірній елемент: під час прокрутки вони прилипають до верху.
        // Розділи — прямі дочірні елементи, тож їхні y з onLayout рахуються від початку списку.
        <ScrollView
          ref={page}
          showsVerticalScrollIndicator={false}
          keyboardShouldPersistTaps="handled"
          stickyHeaderIndices={[1]}
          scrollEventThrottle={64}
          onScroll={onScroll}
        >
          <View>{config?.banners_on !== false && <Banners banners={banners} />}</View>
          <View
            style={styles.catsBar}
            onLayout={(event: LayoutChangeEvent) => {
              barHeight.current = event.nativeEvent.layout.height;
            }}
          >
            <ScrollView
              ref={strip}
              horizontal
              showsHorizontalScrollIndicator={false}
              contentContainerStyle={styles.cats}
              keyboardShouldPersistTaps="handled"
              onLayout={(event: LayoutChangeEvent) => {
                stripWidth.current = event.nativeEvent.layout.width;
              }}
            >
              {chip('all', 'Усі', null)}
              {sections.map((section) => chip(String(section.id), section.name, section.id))}
            </ScrollView>
          </View>
          {sections.map((section) => (
            <View
              key={section.id}
              style={styles.section}
              onLayout={(event: LayoutChangeEvent) => {
                sectionY.current[section.id] = event.nativeEvent.layout.y;
              }}
            >
              <Text accessibilityRole="header" style={styles.sectionTitle}>
                {section.name}
              </Text>
              <View style={styles.grid}>
                {section.cards.map((card) => (
                  <ProductCard key={card.folder ? `folder-${card.folder.id}` : card.product.id} card={card} />
                ))}
              </View>
            </View>
          ))}
          {!sections.length && <Text style={styles.nothing}>Нічого не знайдено</Text>}
          <View style={{ height: 10 }} />
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
  searchClear: { position: 'absolute', right: gutter + 8, top: 9, zIndex: 1 },
  bannerFrame: { borderRadius: 12, overflow: 'hidden' },
  dots: { flexDirection: 'row', justifyContent: 'center', gap: 5, paddingVertical: 6 },
  dotHit: { width: 18, height: 18, alignItems: 'center', justifyContent: 'center' },
  dot: { width: 8, height: 8, borderRadius: 4, backgroundColor: '#AAAAAA' },
  dotOn: { backgroundColor: colors.red },
  // Фон потрібен, щоб під прилиплими категоріями не просвічували картки.
  catsBar: { flexGrow: 0, backgroundColor: colors.white },
  cats: { flexDirection: 'row', gap: 6, paddingHorizontal: gutter, paddingVertical: 4 },
  // Заметний проміжок між категоріями: розділи не зливаються в один список.
  section: { paddingTop: 22 },
  sectionTitle: { fontSize: 20, lineHeight: 25, fontWeight: '600', color: colors.ink, paddingHorizontal: gutter, marginBottom: 10 },
  grid: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'space-between', rowGap: 8, paddingHorizontal: gutter, paddingBottom: 6 },
  card: { width: '48.8%', borderWidth: 1, borderColor: colors.line, borderRadius: 12, overflow: 'hidden' },
  // Квадратне фото: висота дорівнює ширині картки.
  pic: { aspectRatio: 1 },
  cardHeart: { position: 'absolute', zIndex: 1, top: 3, right: 3 },
  // Картки в ряду однакової висоти, а «Обрати» притиснута до низу картки.
  cardBody: { flex: 1, paddingTop: 7, paddingHorizontal: 8, paddingBottom: 8 },
  cardButton: { marginTop: 'auto' },
  // Місце під два рядки назви: тоді фото в сусідніх картках однакові.
  cardName: { minHeight: 36, fontSize: 14, lineHeight: 18, fontWeight: '600', color: colors.ink },
  cardPrice: { fontSize: 15, fontWeight: '500', color: colors.ink, marginTop: 3, marginBottom: 5 },
  nothing: { textAlign: 'center', fontSize: 15, color: colors.text, paddingVertical: 24 },
});

/** Сітка карток — та сама на екрані папки. */
export const cardGrid = styles.grid;
