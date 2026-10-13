const CONTENT_API_URL = (process.env.EXPO_PUBLIC_CONTENT_API_URL ?? 'https://preces.criandoconteudo.com.br/api').replace(/\/$/, '');

export type BibleTestament = 'old' | 'new';

export type BibleBook = {
  id: number;
  name: string;
  slug: string;
  abbreviation: string;
  testament: BibleTestament;
  chapterCount: number;
};

export type BibleChapter = {
  id: number;
  number: number;
  title: string;
  audioUrl?: string;
  verseCount: number;
};

export type BibleVerse = {
  id: number;
  number: number;
  text: string;
  audioUrl?: string;
  chapterId?: number;
  chapterNumber?: number;
  chapterAudioUrl?: string;
  bookId?: number;
  bookName?: string;
  bookSlug?: string;
  abbreviation?: string;
};

export type BibleGlossaryTerm = {
  id: number;
  term: string;
  slug: string;
  definition: string;
  relatedReference?: string;
};

async function requestBible(params: Record<string, string | number>) {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 12_000);
  const query = new URLSearchParams(Object.entries(params).map(([key, value]) => [key, String(value)])).toString();
  try {
    const response = await fetch(`${CONTENT_API_URL}/bible.php${query ? `?${query}` : ''}`, { signal: controller.signal });
    if (!response.ok) throw new Error(`Bible API ${response.status}`);
    return await response.json() as Record<string, unknown>;
  } finally {
    clearTimeout(timeout);
  }
}

function mapBook(item: Record<string, unknown>): BibleBook {
  return {
    id: Number(item.id),
    name: String(item.name ?? ''),
    slug: String(item.slug ?? ''),
    abbreviation: String(item.abbreviation ?? ''),
    testament: item.testament === 'new' ? 'new' : 'old',
    chapterCount: Number(item.chapter_count ?? 0),
  };
}

function mapChapter(item: Record<string, unknown>): BibleChapter {
  return {
    id: Number(item.id),
    number: Number(item.chapter_number),
    title: String(item.title ?? ''),
    audioUrl: item.audio_url ? String(item.audio_url) : undefined,
    verseCount: Number(item.verse_count ?? 0),
  };
}

function mapVerse(item: Record<string, unknown>): BibleVerse {
  return {
    id: Number(item.id),
    number: Number(item.verse_number),
    text: String(item.verse_text ?? ''),
    audioUrl: item.audio_url ? String(item.audio_url) : undefined,
    chapterId: item.chapter_id ? Number(item.chapter_id) : undefined,
    chapterNumber: item.chapter_number ? Number(item.chapter_number) : undefined,
    chapterAudioUrl: item.chapter_audio_url ? String(item.chapter_audio_url) : undefined,
    bookId: item.book_id ? Number(item.book_id) : undefined,
    bookName: item.book_name ? String(item.book_name) : undefined,
    bookSlug: item.book_slug ? String(item.book_slug) : undefined,
    abbreviation: item.abbreviation ? String(item.abbreviation) : undefined,
  };
}

function mapGlossary(item: Record<string, unknown>): BibleGlossaryTerm {
  return {
    id: Number(item.id),
    term: String(item.term ?? ''),
    slug: String(item.slug ?? ''),
    definition: String(item.definition ?? ''),
    relatedReference: item.related_reference ? String(item.related_reference) : undefined,
  };
}

export async function loadBibleBooks(testament: BibleTestament) {
  const payload = await requestBible({ testament });
  return Array.isArray(payload.items) ? payload.items.map(item => mapBook(item as Record<string, unknown>)) : [];
}

export async function loadBibleChapters(bookSlug: string) {
  const payload = await requestBible({ book: bookSlug });
  return Array.isArray(payload.chapters) ? payload.chapters.map(item => mapChapter(item as Record<string, unknown>)) : [];
}

export async function loadBibleChapter(bookSlug: string, chapterNumber: number) {
  const payload = await requestBible({ book: bookSlug, chapter: chapterNumber });
  const chapter = payload.chapter && typeof payload.chapter === 'object' ? mapChapter(payload.chapter as Record<string, unknown>) : null;
  const verses = Array.isArray(payload.verses) ? payload.verses.map(item => mapVerse(item as Record<string, unknown>)) : [];
  return { chapter, verses };
}

export async function loadBibleGlossary() {
  const payload = await requestBible({ glossary: 1 });
  return Array.isArray(payload.items) ? payload.items.map(item => mapGlossary(item as Record<string, unknown>)) : [];
}

export async function searchBible(query: string) {
  const payload = await requestBible({ q: query, limit: 60 });
  return {
    verses: Array.isArray(payload.verses) ? payload.verses.map(item => mapVerse(item as Record<string, unknown>)) : [],
    glossary: Array.isArray(payload.glossary) ? payload.glossary.map(item => mapGlossary(item as Record<string, unknown>)) : [],
  };
}
