import React, { useEffect, useMemo, useState } from 'react';
import { ActivityIndicator, Alert, Pressable, ScrollView, StyleSheet, View } from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { router, useLocalSearchParams } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Reader } from '../../Overlays';
import { Prayer } from '../../data';
import { dark, light } from '../../theme';
import { Icon, shadow } from '../../ui';
import { loadPublishedContent, PublishedPrayer, recordPrayerAccess } from '../../services/content';
import { DailyNarrationLimitError, playPrayerNarration } from '../../services/prayerNarration';
import { NarrationCancelledError, PlaybackPhase } from '../../services/tts';
import { Text } from '../../typography';

type StoredState = { dark?: boolean; premium?: boolean };

export default function CategoryScreen() {
  const params = useLocalSearchParams<{ slug?: string | string[] }>();
  const slug = Array.isArray(params.slug) ? params.slug[0] : params.slug;
  const [prayers, setPrayers] = useState<PublishedPrayer[]>([]);
  const [categoryName, setCategoryName] = useState('Orações');
  const [loading, setLoading] = useState(true);
  const [reader, setReader] = useState<Prayer | null>(null);
  const [premium, setPremium] = useState(false);
  const [darkMode, setDarkMode] = useState(false);
  const p = darkMode ? dark : light;

  useEffect(() => {
    Promise.all([loadPublishedContent(), AsyncStorage.getItem('@preces/state')])
      .then(([content, raw]) => {
        const state: StoredState = raw ? JSON.parse(raw) : {};
        setPremium(!!state.premium);
        setDarkMode(!!state.dark);
        const category = content.categories.find(item => item.slug === slug);
        setCategoryName(category?.name || 'Orações');
        setPrayers(content.prayers.filter(prayer => prayer.categorySlug === slug));
      })
      .catch(() => Alert.alert('Não foi possível carregar', 'Confira sua conexão e tente novamente.'))
      .finally(() => setLoading(false));
  }, [slug]);

  const subtitle = useMemo(() => `${prayers.length} ${prayers.length === 1 ? 'oração disponível' : 'orações disponíveis'}`, [prayers.length]);

  const openPrayer = async (prayer: PublishedPrayer) => {
    if (prayer.premium && !premium) {
      Alert.alert('Conteúdo Premium', 'Assine o Premium na tela inicial para acessar esta oração.', [
        { text: 'Agora não' },
        { text: 'Voltar e assinar', onPress: () => router.back() },
      ]);
      return;
    }
    setReader(prayer);
    await recordPrayerAccess(prayer.id);
  };

  const listen = async (prayer: Prayer, onPhase?: (phase: PlaybackPhase) => void) => {
    try {
      await playPrayerNarration(prayer, premium, onPhase);
    } catch (error) {
      onPhase?.('idle');
      if (error instanceof NarrationCancelledError) return;
      if (error instanceof DailyNarrationLimitError) {
        Alert.alert('Limite diário utilizado', 'Na versão gratuita você pode ouvir 1 oração por dia. Volte à tela inicial para conhecer o Premium.');
        return;
      }
      Alert.alert('Não foi possível reproduzir', 'Confira sua conexão e o link do áudio, depois tente novamente.');
    }
  };

  return (
    <SafeAreaView edges={['top', 'bottom', 'left', 'right']} style={[styles.page, { backgroundColor: p.bg }]}>
      <StatusBar style={darkMode ? 'light' : 'dark'} />
      <View style={styles.header}>
        <Pressable hitSlop={12} onPress={() => router.back()} style={({ pressed }) => [styles.back, { backgroundColor: p.surface }, pressed && styles.pressed]}>
          <Icon name="arrow-back" color={p.text} />
        </Pressable>
        <View style={styles.heading}>
          <Text style={[styles.title, { color: p.text }]}>{categoryName}</Text>
          <Text style={[styles.subtitle, { color: p.muted }]}>{subtitle}</Text>
        </View>
        <View style={styles.back} />
      </View>

      {loading ? (
        <View style={styles.center}><ActivityIndicator color={p.primary} size="large" /><Text style={[styles.emptyText, { color: p.muted }]}>Carregando orações...</Text></View>
      ) : (
        <ScrollView contentContainerStyle={styles.list} showsVerticalScrollIndicator={false}>
          {prayers.length ? prayers.map(prayer => (
            <Pressable key={prayer.id} onPress={() => openPrayer(prayer)} style={({ pressed }) => [styles.card, { backgroundColor: p.surface }, pressed && styles.pressed]}>
              <View style={[styles.icon, { backgroundColor: p.surface2 }]}>
                <Icon name={prayer.premium ? 'lock-closed-outline' : 'book-outline'} color={p.gold} />
              </View>
              <View style={styles.copy}>
                <Text style={[styles.cardTitle, { color: p.text }]}>{prayer.title}</Text>
                <Text numberOfLines={3} style={[styles.excerpt, { color: p.muted }]}>{prayer.excerpt || prayer.text}</Text>
                <View style={styles.meta}>
                  {prayer.audioUrl && <><Icon name="headset-outline" size={14} color={p.primary} /><Text style={[styles.audio, { color: p.primary }]}>Áudio disponível</Text></>}
                  {prayer.premium && <Text style={[styles.premium, { color: p.gold }]}>PREMIUM</Text>}
                </View>
              </View>
              <Icon name="chevron-forward" color={p.muted} />
            </Pressable>
          )) : (
            <View style={styles.center}>
              <Icon name="book-outline" size={52} color={p.border} />
              <Text style={[styles.emptyTitle, { color: p.text }]}>Nenhuma oração nesta categoria</Text>
              <Text style={[styles.emptyText, { color: p.muted }]}>Novas orações publicadas no painel aparecerão aqui.</Text>
            </View>
          )}
        </ScrollView>
      )}

      <Reader prayer={reader} close={() => setReader(null)} p={p} premium={premium} listen={listen} toggle={() => {}} />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  page: { flex: 1 },
  header: { minHeight: 68, paddingHorizontal: 18, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  back: { width: 42, height: 42, borderRadius: 21, alignItems: 'center', justifyContent: 'center' },
  heading: { flex: 1, alignItems: 'center', paddingHorizontal: 10 },
  title: { fontSize: 21, fontWeight: '900' },
  subtitle: { fontSize: 11, marginTop: 2 },
  list: { padding: 18, paddingBottom: 36, gap: 12 },
  card: { borderRadius: 18, padding: 14, flexDirection: 'row', alignItems: 'center', gap: 12, ...shadow },
  pressed: { opacity: 0.72, transform: [{ scale: 0.985 }] },
  icon: { width: 48, height: 48, borderRadius: 15, alignItems: 'center', justifyContent: 'center' },
  copy: { flex: 1 },
  cardTitle: { fontSize: 16, fontWeight: '800', lineHeight: 21 },
  excerpt: { fontSize: 12, lineHeight: 18, marginTop: 4 },
  meta: { minHeight: 18, flexDirection: 'row', alignItems: 'center', gap: 5, marginTop: 7 },
  audio: { fontSize: 10, fontWeight: '800' },
  premium: { marginLeft: 'auto', fontSize: 9, fontWeight: '900' },
  center: { flex: 1, minHeight: 300, alignItems: 'center', justifyContent: 'center', padding: 28, gap: 10 },
  emptyTitle: { fontSize: 17, fontWeight: '800', textAlign: 'center' },
  emptyText: { fontSize: 12, lineHeight: 18, textAlign: 'center' },
});
