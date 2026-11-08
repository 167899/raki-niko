import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { Empty, Header, Paragraph, shared } from '../components/ui';
import { dayLabel } from '../format';
import { useShop } from '../shop';
import { colors } from '../theme';

export function NotificationsScreen() {
  const { notes, readIds, markRead } = useShop();
  return (
    <View style={shared.fill}>
      <Header title="Повідомлення" />
      <ScrollView style={{ flex: 1 }} contentContainerStyle={shared.pad} showsVerticalScrollIndicator={false}>
        {notes.length ? (
          notes.map((note) => (
            <Pressable key={note.id} accessibilityRole="button" style={styles.row} onPress={() => markRead(note.id)}>
              <View style={[styles.dot, readIds.includes(note.id) && styles.dotRead]} />
              <View style={{ flex: 1 }}>
                <Text style={styles.title}>{note.title}</Text>
                {!!note.text && <Paragraph>{note.text}</Paragraph>}
                <Text style={styles.date}>{dayLabel(note.date)}</Text>
              </View>
            </Pressable>
          ))
        ) : (
          <Empty icon="bell" text="Поки що повідомлень немає" />
        )}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', gap: 9, paddingVertical: 9, paddingHorizontal: 2, borderBottomWidth: 1, borderBottomColor: '#EEEEEE' },
  dot: { width: 8, height: 8, borderRadius: 4, backgroundColor: colors.red, marginTop: 5 },
  dotRead: { backgroundColor: 'transparent' },
  title: { fontSize: 15, fontWeight: '500', color: colors.ink },
  date: { fontSize: 13, color: colors.text, marginTop: 2 },
});
