import { ScrollView, Text, View } from 'react-native';
import { BottomBar, Header, shared } from '../components/ui';
import { useShop } from '../shop';
import { colors } from '../theme';
import { buildCardsAt, cardGrid, ProductCard } from './HomeScreen';

/** Папка в категорії («Суші та роли» → «Каліфорнія»): її картки, а вкладені папки — теж картками. */
export function FolderScreen({ folderId }: { folderId: number }) {
  const { catalog } = useShop();
  const subcategories = catalog?.subcategories ?? [];
  const folder = subcategories.find((sub) => sub.id === folderId);
  const products = (catalog?.products ?? []).filter((product) => product.in_stock && product.category_id === folder?.category_id);
  const cards = folder ? buildCardsAt(products, subcategories, folder.id) : [];
  return (
    <View style={shared.fill}>
      <Header title={folder?.name ?? 'Меню'} />
      <ScrollView style={{ flex: 1 }} contentContainerStyle={{ paddingTop: 12 }} showsVerticalScrollIndicator={false}>
        {cards.length ? (
          <View style={cardGrid}>
            {cards.map((card) => (
              <ProductCard key={card.folder ? `folder-${card.folder.id}` : card.product.id} card={card} />
            ))}
          </View>
        ) : (
          <Text style={{ padding: 16, fontSize: 15, color: colors.text }}>Зараз тут немає товарів у наявності.</Text>
        )}
      </ScrollView>
      <BottomBar active="home" />
    </View>
  );
}
