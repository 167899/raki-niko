import { useCallback, useMemo, useRef, useState } from 'react';
import { Modal, Pressable, ScrollView, ScrollViewInstance, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { buildReadyDates, defaultReady, joinReady, READY_HOURS, READY_MINUTES, ReadyParts, readyProblem, splitReady } from '../slots';
import { colors } from '../theme';
import { Button, ErrorText } from './ui';

const ROW = 40;
const VISIBLE_ROWS = 5;
const EDGE = ROW * Math.floor(VISIBLE_ROWS / 2);

/** Колесо вибору: прокручується пальцем, вибір фіксується рядком посередині. */
function Wheel({
  label,
  items,
  index,
  onChange,
  wide,
}: {
  label: string;
  items: string[];
  index: number;
  onChange: (index: number) => void;
  wide?: boolean;
}) {
  const scroller = useRef<ScrollViewInstance>(null);
  const placed = useRef(false);
  const current = useRef(index);

  const select = useCallback(
    (next: number) => {
      if (next === current.current) return;
      current.current = next;
      onChange(next);
    },
    [onChange],
  );

  return (
    <View style={wide ? styles.wheelWide : styles.wheel} accessibilityLabel={label}>
      <View style={styles.band} pointerEvents="none" />
      <ScrollView
        ref={scroller}
        style={styles.wheelScroll}
        contentContainerStyle={{ paddingVertical: EDGE }}
        showsVerticalScrollIndicator={false}
        nestedScrollEnabled
        snapToInterval={ROW}
        decelerationRate="fast"
        scrollEventThrottle={32}
        onLayout={() => {
          if (placed.current) return;
          placed.current = true;
          scroller.current?.scrollTo({ y: index * ROW, animated: false });
        }}
        onScroll={(event) => {
          const next = Math.round(event.nativeEvent.contentOffset.y / ROW);
          select(Math.min(items.length - 1, Math.max(0, next)));
        }}
      >
        {items.map((item, itemIndex) => (
          <Pressable
            key={item}
            accessibilityRole="button"
            accessibilityState={{ selected: itemIndex === index }}
            style={styles.row}
            onPress={() => scroller.current?.scrollTo({ y: itemIndex * ROW, animated: true })}
          >
            <Text numberOfLines={1} style={[styles.rowText, itemIndex === index && styles.rowTextOn]}>
              {item}
            </Text>
          </Pressable>
        ))}
      </ScrollView>
    </View>
  );
}

export function ReadyPicker({ value, onCancel, onDone }: { value: string; onCancel: () => void; onDone: (value: string) => void }) {
  const insets = useSafeAreaInsets();
  const dates = useMemo(() => buildReadyDates(new Date()), []);
  const [parts, setParts] = useState<ReadyParts>(() => (value ? splitReady(value) : defaultReady(new Date())));
  const [problem, setProblem] = useState('');

  const update = (change: Partial<ReadyParts>) => {
    setParts((current) => ({ ...current, ...change }));
    setProblem('');
  };

  const confirm = () => {
    const found = readyProblem(parts, new Date());
    setProblem(found);
    if (!found) onDone(joinReady(parts));
  };

  return (
    <Modal visible transparent animationType="slide" statusBarTranslucent onRequestClose={onCancel}>
      <View style={styles.layer}>
        <Pressable accessibilityLabel="Закрити" style={StyleSheet.absoluteFill} onPress={onCancel} />
        <View style={[styles.sheet, { paddingBottom: insets.bottom + 16 }]}>
          <Text style={styles.title}>Готуємо на</Text>
          <View style={styles.wheels}>
            <Wheel
              wide
              label="Дата"
              items={dates.map((date) => date.label)}
              index={Math.max(0, dates.findIndex((date) => date.key === parts.date))}
              onChange={(index) => update({ date: dates[index].key })}
            />
            <Wheel
              label="Години"
              items={READY_HOURS}
              index={Math.max(0, READY_HOURS.indexOf(parts.hour))}
              onChange={(index) => update({ hour: READY_HOURS[index] })}
            />
            <Wheel
              label="Хвилини"
              items={READY_MINUTES}
              index={Math.max(0, READY_MINUTES.indexOf(parts.minute))}
              onChange={(index) => update({ minute: READY_MINUTES[index] })}
            />
          </View>
          {!!problem && <ErrorText>{problem}</ErrorText>}
          <View style={styles.actions}>
            <Button kind="outline" title="Скасувати" style={{ flex: 1 }} onPress={onCancel} />
            <Button title="Готово" style={{ flex: 1 }} onPress={confirm} />
          </View>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  layer: { flex: 1, justifyContent: 'flex-end', alignItems: 'center', backgroundColor: colors.overlay },
  sheet: {
    width: '100%',
    maxWidth: 430,
    backgroundColor: colors.white,
    borderTopLeftRadius: 16,
    borderTopRightRadius: 16,
    paddingTop: 14,
    paddingHorizontal: 14,
  },
  title: { fontSize: 17, fontWeight: '500', color: colors.ink, marginBottom: 8 },
  wheels: { flexDirection: 'row', gap: 6 },
  wheel: { flex: 1, height: ROW * VISIBLE_ROWS },
  wheelWide: { flex: 3, height: ROW * VISIBLE_ROWS },
  wheelScroll: { flex: 1 },
  band: {
    position: 'absolute',
    top: EDGE,
    left: 0,
    right: 0,
    height: ROW,
    borderRadius: 10,
    backgroundColor: colors.soft,
  },
  row: { height: ROW, alignItems: 'center', justifyContent: 'center' },
  rowText: { fontSize: 16, color: colors.text },
  rowTextOn: { fontSize: 17, fontWeight: '500', color: colors.softInk },
  actions: { flexDirection: 'row', gap: 8, marginTop: 12 },
});
