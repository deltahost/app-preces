import React, { useEffect, useMemo, useState } from 'react';
import { ActivityIndicator, Alert, Image, Pressable, ScrollView, StyleSheet, View } from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { router, useLocalSearchParams } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Text, TextInput } from '../typography';
import { dark, light } from '../theme';
import { Icon, shadow } from '../ui';
import {
  BibleBook,
  BibleChapter,
  BibleGlossaryTerm,
  BibleTestament,
  BibleVerse,
  loadBibleChapter,
  loadBibleChapters,
  loadBibleGlossary,
  loadBibleBooks,
  searchBible,
} from '../services/bible';
import { PlaybackPhase, playAudioUrl, stopNarration } from '../services/tts';

type Mode = 'bible' | 'glossary';
type StoredState = { dark?: boolean };

export default function BibleScreen() {
  const params = useLocalSearchParams<{ testament?: string | string[] }>();
  const requestedTestament = Array.isArray(params.testament) ? params.testament[0] : params.testament;
  const [darkMode, setDarkMode] = useState(false);
  const [mode, setMode] = useState<Mode>('bible');
  const [testament, setTestament] = useState<BibleTestament>(requestedTestament === 'new' ? 'new' : 'old');
  const [books, setBooks] = useState<BibleBook[]>([]);
  const [chapters, setChapters] = useState<BibleChapter[]>([]);
  const [verses, setVerses] = useState<BibleVerse[]>([]);
  const [glossary, setGlossary] = useState<BibleGlossaryTerm[]>([]);
  const [selectedBook, setSelectedBook] = useState<BibleBook | null>(null);
  const [selectedChapter, setSelectedChapter] = useState<BibleChapter | null>(null);
  const [query, setQuery] = useState('');
  const [submittedQuery, setSubmittedQuery] = useState('');
  const [searchVerses, setSearchVerses] = useState<BibleVerse[]>([]);
  const [searchTerms, setSearchTerms] = useState<BibleGlossaryTerm[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [audioUrl, setAudioUrl] = useState<string | null>(null);
  const [audioPhase, setAudioPhase] = useState<PlaybackPhase>('idle');
  const p = darkMode ? dark : light;

  useEffect(() => {
    AsyncStorage.getItem('@preces/state').then(raw => {
      const state: StoredState = raw ? JSON.parse(raw) : {};
      setDarkMode(!!state.dark);
    }).catch(() => {});
    return () => { void stopNarration(); };
  }, []);

  useEffect(() => {
    setLoading(true);
    setError('');
    loadBibleBooks(testament)
      .then(setBooks)
      .catch(() => setError('Não foi possível carregar os livros. Confira a conexão e tente novamente.'))
      .finally(() => setLoading(false));
  }, [testament]);

  const title = selectedChapter
    ? `${selectedBook?.name ?? ''} ${selectedChapter.number}`
    : selectedBook?.name ?? (mode === 'glossary' ? 'Glossário Bíblico' : 'Bíblia Sagrada');
  const subtitle = selectedChapter
    ? `${verses.length} versículos`
    : selectedBook
      ? `${chapters.length} capítulos disponíveis`
      : mode === 'glossary'
        ? 'Termos e significados das Escrituras'
        : testament === 'old' ? 'Antigo Testamento' : 'Novo Testamento';
  const isSearching = submittedQuery.length > 0;

  const groupedGlossary = useMemo(() => {
    return glossary.reduce<Record<string, BibleGlossaryTerm[]>>((groups, term) => {
      const letter = term.term.charAt(0).toLocaleUpperCase('pt-BR') || '#';
      groups[letter] = [...(groups[letter] ?? []), term];
      return groups;
    }, {});
  }, [glossary]);

  const goBack = () => {
    if (isSearching) {
      setQuery('');
      setSubmittedQuery('');
      setSearchVerses([]);
      setSearchTerms([]);
      return;
    }
    if (selectedChapter) {
      setSelectedChapter(null);
      setVerses([]);
      return;
    }
    if (selectedBook) {
      setSelectedBook(null);
      setChapters([]);
      return;
    }
    router.back();
  };

  const changeTestament = (next: BibleTestament) => {
    setTestament(next);
    setSelectedBook(null);
    setSelectedChapter(null);
    setChapters([]);
    setVerses([]);
  };

  const changeMode = (next: Mode) => {
    setMode(next);
    setSelectedBook(null);
    setSelectedChapter(null);
    setChapters([]);
    setVerses([]);
    setError('');
    if (next === 'glossary' && !glossary.length) {
      setLoading(true);
      loadBibleGlossary()
        .then(setGlossary)
        .catch(() => setError('Não foi possível carregar o glossário agora.'))
        .finally(() => setLoading(false));
    }
  };

  const openBook = (book: BibleBook) => {
    setSelectedBook(book);
    setLoading(true);
    setError('');
    loadBibleChapters(book.slug)
      .then(setChapters)
      .catch(() => setError('Não foi possível carregar os capítulos deste livro.'))
      .finally(() => setLoading(false));
  };

  const openChapter = (chapter: BibleChapter) => {
    if (!selectedBook) return;
    setSelectedChapter(chapter);
    setLoading(true);
    setError('');
    loadBibleChapter(selectedBook.slug, chapter.number)
      .then(result => {
        setSelectedChapter(result.chapter ?? chapter);
        setVerses(result.verses);
      })
      .catch(() => setError('Não foi possível carregar os versículos deste capítulo.'))
      .finally(() => setLoading(false));
  };

  const submitSearch = () => {
    const value = query.trim();
    if (!value) {
      setSubmittedQuery('');
      setSearchVerses([]);
      setSearchTerms([]);
      return;
    }
    setSubmittedQuery(value);
    setLoading(true);
    setError('');
    searchBible(value)
      .then(result => {
        setSearchVerses(result.verses);
        setSearchTerms(result.glossary);
      })
      .catch(() => setError('A pesquisa não pôde ser realizada. Tente novamente.'))
      .finally(() => setLoading(false));
  };

  const toggleAudio = async (url?: string) => {
    if (!url || audioPhase === 'loading') return;
    if (audioUrl === url && audioPhase === 'playing') {
      await stopNarration();
      setAudioUrl(null);
      setAudioPhase('idle');
      return;
    }
    try {
      await stopNarration();
      setAudioUrl(url);
      setAudioPhase('loading');
      await playAudioUrl(url, phase => {
        setAudioPhase(phase);
        if (phase === 'idle' || phase === 'error') setAudioUrl(null);
      });
    } catch {
      setAudioUrl(null);
      setAudioPhase('idle');
      Alert.alert('Áudio indisponível', 'Não foi possível reproduzir esta narração.');
    }
  };

  const AudioButton = ({ url, label }: { url?: string; label: string }) => {
    if (!url) return null;
    const active = audioUrl === url;
    const preparing = active && audioPhase === 'loading';
    const playing = active && audioPhase === 'playing';
    return <Pressable accessibilityRole="button" accessibilityState={{ busy: preparing }} onPress={() => void toggleAudio(url)} style={({ pressed }) => [styles.audioButton, { backgroundColor: playing ? p.danger : p.surface2, borderColor: playing ? p.danger : p.border }, pressed && styles.pressed]}>{preparing ? <ActivityIndicator size="small" color={p.primary} /> : <Icon name={playing ? 'stop-circle' : 'volume-high-outline'} size={18} color={playing ? '#fff' : p.primary} />}<Text style={[styles.audioText, { color: playing ? '#fff' : p.text }]}>{preparing ? 'Carregando…' : playing ? 'Parar' : label}</Text></Pressable>;
  };

  const renderSearch = () => <View style={styles.results}>
    <Text style={[styles.resultHeading, { color: p.text }]}>Resultados para “{submittedQuery}”</Text>
    {!searchVerses.length && !searchTerms.length && !loading && <Empty icon="search-outline" title="Nada encontrado" detail="Tente outra palavra ou uma referência como João 3:16." />}
    {searchVerses.map(verse => <View key={`verse-${verse.id}`} style={[styles.verseCard, { backgroundColor: p.surface, borderColor: p.border }]}><View style={[styles.verseNumber, { backgroundColor: p.surface2 }]}><Text style={[styles.verseNumberText, { color: p.primary }]}>{verse.number}</Text></View><View style={styles.flex}><Text style={[styles.reference, { color: p.gold }]}>{verse.bookName} {verse.chapterNumber}:{verse.number}</Text><Text selectable style={[styles.verseText, { color: p.text }]}>{verse.text}</Text><AudioButton url={verse.audioUrl ?? verse.chapterAudioUrl} label="Ouvir" /></View></View>)}
    {searchTerms.map(term => <GlossaryCard key={`term-${term.id}`} term={term} />)}
  </View>;

  const GlossaryCard = ({ term }: { term: BibleGlossaryTerm }) => <View style={[styles.glossaryCard, { backgroundColor: p.surface, borderColor: p.border }]}><View style={[styles.glossaryMark, { backgroundColor: p.surface2 }]}><Text style={[styles.glossaryLetter, { color: p.gold }]}>{term.term.charAt(0).toLocaleUpperCase('pt-BR')}</Text></View><View style={styles.flex}><Text style={[styles.glossaryTerm, { color: p.text }]}>{term.term}</Text><Text selectable style={[styles.glossaryDefinition, { color: p.muted }]}>{term.definition}</Text>{term.relatedReference && <View style={styles.related}><Icon name="bookmark-outline" size={14} color={p.gold} /><Text style={[styles.relatedText, { color: p.gold }]}>{term.relatedReference}</Text></View>}</View></View>;

  const Empty = ({ icon, title: emptyTitle, detail }: { icon: string; title: string; detail: string }) => <View style={styles.empty}><Icon name={icon} size={46} color={p.border} /><Text style={[styles.emptyTitle, { color: p.text }]}>{emptyTitle}</Text><Text style={[styles.emptyText, { color: p.muted }]}>{detail}</Text></View>;

  return <SafeAreaView edges={['top', 'bottom', 'left', 'right']} style={[styles.page, { backgroundColor: p.bg }]}>
    <StatusBar style={darkMode ? 'light' : 'dark'} />
    <View style={styles.header}><Pressable accessibilityRole="button" accessibilityLabel="Voltar" hitSlop={12} onPress={goBack} style={({ pressed }) => [styles.roundButton, { backgroundColor: p.surface }, pressed && styles.pressed]}><Icon name="arrow-back" color={p.text} /></Pressable><View style={styles.heading}><Text numberOfLines={1} style={[styles.title, { color: p.text }]}>{title}</Text><Text numberOfLines={1} style={[styles.subtitle, { color: p.muted }]}>{subtitle}</Text></View><View style={styles.roundButton} /></View>

    <View style={[styles.searchBox, { backgroundColor: p.surface, borderColor: p.border }]}><Icon name="search-outline" size={21} color={p.muted} /><TextInput value={query} onChangeText={setQuery} onSubmitEditing={submitSearch} returnKeyType="search" placeholder="Buscar palavra, termo ou João 3:16" placeholderTextColor={p.muted} style={[styles.searchInput, { color: p.text }]} />{query.length > 0 && <Pressable hitSlop={10} onPress={() => { setQuery(''); setSubmittedQuery(''); setSearchVerses([]); setSearchTerms([]); }}><Icon name="close-circle" size={20} color={p.muted} /></Pressable>}<Pressable accessibilityRole="button" onPress={submitSearch} style={[styles.searchButton, { backgroundColor: p.primary }]}><Icon name="arrow-forward" size={18} color="#fff" /></Pressable></View>

    {!selectedBook && !isSearching && <View style={[styles.modeTabs, { backgroundColor: p.surface2 }]}><Pressable onPress={() => changeMode('bible')} style={[styles.modeTab, mode === 'bible' && { backgroundColor: p.surface }]}><Icon name="book-outline" size={17} color={mode === 'bible' ? p.primary : p.muted} /><Text style={[styles.modeText, { color: mode === 'bible' ? p.text : p.muted }]}>Bíblia</Text></Pressable><Pressable onPress={() => changeMode('glossary')} style={[styles.modeTab, mode === 'glossary' && { backgroundColor: p.surface }]}><Icon name="library-outline" size={17} color={mode === 'glossary' ? p.primary : p.muted} /><Text style={[styles.modeText, { color: mode === 'glossary' ? p.text : p.muted }]}>Glossário</Text></Pressable></View>}

    {mode === 'bible' && !selectedBook && !isSearching && <View style={styles.testamentRow}><Pressable onPress={() => changeTestament('old')} style={[styles.testamentButton, { borderColor: testament === 'old' ? p.primary : p.border, backgroundColor: testament === 'old' ? p.primary : p.surface }]}><Text style={[styles.testamentText, { color: testament === 'old' ? '#fff' : p.text }]}>Antigo Testamento</Text></Pressable><Pressable onPress={() => changeTestament('new')} style={[styles.testamentButton, { borderColor: testament === 'new' ? p.primary : p.border, backgroundColor: testament === 'new' ? p.primary : p.surface }]}><Text style={[styles.testamentText, { color: testament === 'new' ? '#fff' : p.text }]}>Novo Testamento</Text></Pressable></View>}

    {loading ? <View style={styles.loading}><ActivityIndicator size="large" color={p.gold} /><Text style={[styles.emptyText, { color: p.muted }]}>Carregando conteúdo bíblico…</Text></View> : error ? <View style={styles.loading}><Icon name="cloud-offline-outline" size={44} color={p.border} /><Text style={[styles.emptyTitle, { color: p.text }]}>Conteúdo indisponível</Text><Text style={[styles.emptyText, { color: p.muted }]}>{error}</Text></View> : <ScrollView keyboardShouldPersistTaps="handled" contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
      {isSearching ? renderSearch() : mode === 'glossary' ? <View>{glossary.length ? Object.entries(groupedGlossary).sort(([a], [b]) => a.localeCompare(b)).map(([letter, terms]) => <View key={letter} style={styles.glossaryGroup}><Text style={[styles.letterHeading, { color: p.gold }]}>{letter}</Text>{terms.map(term => <GlossaryCard key={term.id} term={term} />)}</View>) : <Empty icon="library-outline" title="Glossário em preparação" detail="Os termos publicados no painel aparecerão aqui." />}</View> : !selectedBook ? <View><View style={[styles.bibleHero, { backgroundColor: p.surface, borderColor: p.border }]}><Image source={require('../../assets/open-bible-minimal.png')} resizeMode="contain" style={styles.bibleArt} /><Text style={[styles.heroTitle, { color: p.text }]}>Escolha um livro para começar</Text><Text style={[styles.heroText, { color: p.muted }]}>Leia a Palavra e encontre seus capítulos favoritos.</Text></View><View style={styles.bookGrid}>{books.map(book => <Pressable key={book.id} onPress={() => openBook(book)} style={({ pressed }) => [styles.bookCard, { backgroundColor: p.surface, borderColor: p.border }, pressed && styles.pressed]}><Text style={[styles.bookAbbreviation, { color: p.gold }]}>{book.abbreviation}</Text><Text numberOfLines={2} style={[styles.bookName, { color: p.text }]}>{book.name}</Text><Text style={[styles.bookMeta, { color: p.muted }]}>{book.chapterCount} cap.</Text></Pressable>)}</View>{!books.length && <Empty icon="book-outline" title="Nenhum livro publicado" detail="Publique os livros na aba Bíblia do painel." />}</View> : !selectedChapter ? <View style={styles.chapterGrid}>{chapters.map(chapter => <Pressable key={chapter.id} onPress={() => openChapter(chapter)} style={({ pressed }) => [styles.chapterCard, { backgroundColor: p.surface, borderColor: p.border }, pressed && styles.pressed]}><Text style={[styles.chapterNumber, { color: p.primary }]}>{chapter.number}</Text><Text numberOfLines={1} style={[styles.chapterTitle, { color: p.text }]}>{chapter.title || `Capítulo ${chapter.number}`}</Text><View style={styles.chapterMeta}><Text style={[styles.bookMeta, { color: p.muted }]}>{chapter.verseCount} versículos</Text>{chapter.audioUrl && <Icon name="headset-outline" size={15} color={p.gold} />}</View></Pressable>)}</View> : <View><View style={[styles.chapterAudioCard, { backgroundColor: p.surface, borderColor: p.border }]}><View style={[styles.chapterAudioIcon, { backgroundColor: p.surface2 }]}><Icon name="headset-outline" size={25} color={p.gold} /></View><View style={styles.flex}><Text style={[styles.chapterAudioTitle, { color: p.text }]}>Narração do capítulo</Text><Text style={[styles.bookMeta, { color: p.muted }]}>{selectedChapter.audioUrl ? 'Ouça o capítulo completo' : 'Áudio ainda não publicado'}</Text></View><AudioButton url={selectedChapter.audioUrl} label="Ouvir" /></View>{verses.map(verse => <View key={verse.id} style={[styles.verseCard, { backgroundColor: p.surface, borderColor: p.border }]}><View style={[styles.verseNumber, { backgroundColor: p.surface2 }]}><Text style={[styles.verseNumberText, { color: p.primary }]}>{verse.number}</Text></View><View style={styles.flex}><Text selectable style={[styles.verseText, { color: p.text }]}>{verse.text}</Text><AudioButton url={verse.audioUrl} label="Ouvir versículo" /></View></View>)}{!verses.length && <Empty icon="document-text-outline" title="Capítulo em preparação" detail="Os versículos publicados no painel aparecerão aqui." />}</View>}
    </ScrollView>}
  </SafeAreaView>;
}

const styles = StyleSheet.create({
  page: { flex: 1 }, flex: { flex: 1 }, pressed: { opacity: .72, transform: [{ scale: .985 }] },
  header: { minHeight: 66, paddingHorizontal: 18, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  roundButton: { width: 42, height: 42, borderRadius: 21, alignItems: 'center', justifyContent: 'center' },
  heading: { flex: 1, alignItems: 'center', paddingHorizontal: 10 }, title: { fontSize: 20, fontWeight: '900' }, subtitle: { fontSize: 10, marginTop: 1 },
  searchBox: { minHeight: 52, marginHorizontal: 18, borderWidth: 1, borderRadius: 17, paddingLeft: 14, paddingRight: 6, flexDirection: 'row', alignItems: 'center', gap: 9, ...shadow },
  searchInput: { flex: 1, minHeight: 48, paddingVertical: 10, fontSize: 12 }, searchButton: { width: 40, height: 40, borderRadius: 13, alignItems: 'center', justifyContent: 'center' },
  modeTabs: { marginHorizontal: 18, marginTop: 12, borderRadius: 14, padding: 4, flexDirection: 'row' }, modeTab: { flex: 1, minHeight: 42, borderRadius: 11, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 7 }, modeText: { fontSize: 11, fontWeight: '800' },
  testamentRow: { flexDirection: 'row', gap: 9, paddingHorizontal: 18, paddingTop: 12 }, testamentButton: { flex: 1, minHeight: 42, borderRadius: 13, borderWidth: 1, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 8 }, testamentText: { fontSize: 10, fontWeight: '800', textAlign: 'center' },
  content: { padding: 18, paddingBottom: 42 }, loading: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: 10, padding: 30 },
  bibleHero: { borderWidth: 1, borderRadius: 22, alignItems: 'center', padding: 14, marginBottom: 14, overflow: 'hidden', ...shadow }, bibleArt: { width: '100%', height: 132 }, heroTitle: { fontSize: 16, fontWeight: '900', marginTop: -5 }, heroText: { fontSize: 10, textAlign: 'center', marginTop: 3 },
  bookGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 }, bookCard: { width: '48.4%', minHeight: 108, borderWidth: 1, borderRadius: 17, padding: 14, justifyContent: 'space-between', ...shadow }, bookAbbreviation: { fontSize: 10, fontWeight: '900', letterSpacing: .8 }, bookName: { fontSize: 14, lineHeight: 19, fontWeight: '800' }, bookMeta: { fontSize: 9 },
  chapterGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 }, chapterCard: { width: '48.4%', minHeight: 112, borderWidth: 1, borderRadius: 17, padding: 14, ...shadow }, chapterNumber: { fontSize: 27, fontWeight: '900' }, chapterTitle: { fontSize: 11, fontWeight: '800', marginTop: 3 }, chapterMeta: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 'auto' },
  chapterAudioCard: { borderWidth: 1, borderRadius: 18, padding: 13, flexDirection: 'row', alignItems: 'center', gap: 11, marginBottom: 12 }, chapterAudioIcon: { width: 46, height: 46, borderRadius: 14, alignItems: 'center', justifyContent: 'center' }, chapterAudioTitle: { fontSize: 13, fontWeight: '800' },
  verseCard: { borderWidth: 1, borderRadius: 17, padding: 14, flexDirection: 'row', alignItems: 'flex-start', gap: 11, marginBottom: 10 }, verseNumber: { width: 35, height: 35, borderRadius: 12, alignItems: 'center', justifyContent: 'center' }, verseNumberText: { fontSize: 13, fontWeight: '900' }, verseText: { fontSize: 13, lineHeight: 22 }, reference: { fontSize: 10, fontWeight: '900', marginBottom: 5 },
  audioButton: { alignSelf: 'flex-start', minHeight: 34, borderRadius: 11, borderWidth: 1, paddingHorizontal: 10, marginTop: 9, flexDirection: 'row', alignItems: 'center', gap: 6 }, audioText: { fontSize: 9, fontWeight: '800' },
  glossaryGroup: { marginBottom: 18 }, letterHeading: { fontSize: 18, fontWeight: '900', marginBottom: 8 }, glossaryCard: { borderWidth: 1, borderRadius: 17, padding: 14, flexDirection: 'row', alignItems: 'flex-start', gap: 11, marginBottom: 10 }, glossaryMark: { width: 40, height: 40, borderRadius: 13, alignItems: 'center', justifyContent: 'center' }, glossaryLetter: { fontSize: 17, fontWeight: '900' }, glossaryTerm: { fontSize: 15, fontWeight: '900' }, glossaryDefinition: { fontSize: 11, lineHeight: 18, marginTop: 4 }, related: { flexDirection: 'row', alignItems: 'center', gap: 5, marginTop: 8 }, relatedText: { fontSize: 9, fontWeight: '800' },
  results: { flex: 1 }, resultHeading: { fontSize: 15, fontWeight: '900', marginBottom: 13 }, empty: { minHeight: 260, padding: 28, alignItems: 'center', justifyContent: 'center', gap: 8 }, emptyTitle: { fontSize: 16, fontWeight: '900', textAlign: 'center' }, emptyText: { fontSize: 11, lineHeight: 18, textAlign: 'center' },
});
