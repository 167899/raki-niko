import { ScrollView, View } from 'react-native';
import { Header, Heading, Paragraph, shared, Warn } from '../components/ui';
import { OFFER, PRIVACY } from '../content';

const PAGES = {
  privacy: { title: 'Політика конфіденційності', note: 'Макет. Остаточний текст має перевірити юрист.', sections: PRIVACY },
  offer: { title: 'Публічна оферта', note: 'Макет. Остаточний текст має підготувати юрист.', sections: OFFER },
};

export function LegalScreen({ kind }: { kind: keyof typeof PAGES }) {
  const page = PAGES[kind];
  return (
    <View style={shared.fill}>
      <Header title={page.title} />
      <ScrollView style={{ flex: 1 }} contentContainerStyle={shared.pad} showsVerticalScrollIndicator={false}>
        <Warn text={page.note} />
        {page.sections.map(([title, text]) => (
          <View key={title}>
            <Heading>{title}</Heading>
            <Paragraph>{text}</Paragraph>
          </View>
        ))}
      </ScrollView>
    </View>
  );
}
