import AsyncStorage from '@react-native-async-storage/async-storage';
import { categories as localCategories, Prayer } from '../data';

const CONTENT_API_URL = (process.env.EXPO_PUBLIC_CONTENT_API_URL ?? 'https://preces.criandoconteudo.com.br/api').replace(/\/$/, '');
const CACHE_KEY = '@preces/published-content-v1';
const RECENT_PRAYERS_KEY = '@preces/recent-prayers-v1';
const FAVORITE_PRAYERS_KEY = '@preces/favorite-prayers-v1';

export type ContentCategory = {
  id: number;
  name: string;
  slug: string;
  icon: string;
  color: string;
};

export type PublishedPrayer = Prayer & {
  slug: string;
  excerpt: string;
  categorySlug?: string;
  premium: boolean;
  featured: boolean;
  imageUrl?: string;
};

export type ContentMediaCategory = {
  id: number;
  name: string;
  slug: string;
};

export type PublishedMedia = {
  id: number;
  type: 'video' | 'audio';
  title: string;
  slug: string;
  description: string;
  sourceUrl: string;
  thumbnailUrl?: string;
  duration: string;
  categorySlug?: string;
  premium: boolean;
};

export type PublishedContent = {
  categories: ContentCategory[];
  prayers: PublishedPrayer[];
  mediaCategories: ContentMediaCategory[];
  media: PublishedMedia[];
};

const localFallback: PublishedContent = {
  categories: localCategories.map(([name, icon, color], index) => ({
    id: index + 1,
    name,
    slug: name.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase(),
    icon,
    color,
  })),
  prayers: [],
  mediaCategories: [],
  media: [],
};

async function requestJson(path: string) {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 8000);
  try {
    const response = await fetch(`${CONTENT_API_URL}/${path}`, { signal: controller.signal });
    if (!response.ok) throw new Error(`Content API ${response.status}`);
    return await response.json();
  } finally {
    clearTimeout(timeout);
  }
}

function mapCategory(item: Record<string, unknown>): ContentCategory {
  return {
    id: Number(item.id),
    name: String(item.name ?? ''),
    slug: String(item.slug ?? ''),
    icon: String(item.icon ?? 'book-outline'),
    color: String(item.color ?? '#7B5C45'),
  };
}

function mapPrayer(item: Record<string, unknown>): PublishedPrayer {
  return {
    id: `published-${String(item.id)}`,
    title: String(item.title ?? ''),
    text: String(item.body ?? ''),
    slug: String(item.slug ?? ''),
    excerpt: String(item.excerpt ?? ''),
    categorySlug: item.category_slug ? String(item.category_slug) : undefined,
    premium: Number(item.is_premium) === 1,
    featured: Number(item.is_featured) === 1,
    imageUrl: item.image_url ? String(item.image_url) : undefined,
    audioUrl: item.audio_url ? String(item.audio_url) : undefined,
  };
}

function mapMediaCategory(item: Record<string, unknown>): ContentMediaCategory {
  return { id: Number(item.id), name: String(item.name ?? ''), slug: String(item.slug ?? '') };
}

function mapMedia(item: Record<string, unknown>): PublishedMedia {
  return {
    id: Number(item.id),
    type: item.type === 'audio' ? 'audio' : 'video',
    title: String(item.title ?? ''),
    slug: String(item.slug ?? ''),
    description: String(item.description ?? ''),
    sourceUrl: String(item.source_url ?? ''),
    thumbnailUrl: item.thumbnail_url ? String(item.thumbnail_url) : undefined,
    duration: String(item.duration_label ?? ''),
    categorySlug: item.category_slug ? String(item.category_slug) : undefined,
    premium: Number(item.is_premium) === 1,
  };
}

export async function loadPublishedContent(): Promise<PublishedContent> {
  try {
    const [categoryPayload, prayerPayload] = await Promise.all([
      requestJson('categories.php'),
      requestJson('prayers.php?limit=100'),
    ]);
    const [mediaCategoryPayload, mediaPayload] = await Promise.all([
      requestJson('media-categories.php').catch(() => ({ items: [] })),
      requestJson('media.php?limit=100').catch(() => ({ items: [] })),
    ]);
    const content: PublishedContent = {
      categories: Array.isArray(categoryPayload.items) ? categoryPayload.items.map(mapCategory) : [],
      prayers: Array.isArray(prayerPayload.items) ? prayerPayload.items.map(mapPrayer) : [],
      mediaCategories: Array.isArray(mediaCategoryPayload.items) ? mediaCategoryPayload.items.map(mapMediaCategory) : [],
      media: Array.isArray(mediaPayload.items) ? mediaPayload.items.map(mapMedia) : [],
    };
    if (!content.categories.length) content.categories = localFallback.categories;
    await AsyncStorage.setItem(CACHE_KEY, JSON.stringify(content));
    return content;
  } catch {
    const cached = await AsyncStorage.getItem(CACHE_KEY);
    if (cached) {
      try {
        const content = JSON.parse(cached) as Partial<PublishedContent>;
        return { ...localFallback, ...content, mediaCategories: content.mediaCategories ?? [], media: content.media ?? [] };
      } catch {
        await AsyncStorage.removeItem(CACHE_KEY);
      }
    }
    return localFallback;
  }
}

export async function loadRecentPrayerIds(): Promise<string[]> {
  try {
    const raw = await AsyncStorage.getItem(RECENT_PRAYERS_KEY);
    const ids = raw ? JSON.parse(raw) : [];
    return Array.isArray(ids) ? ids.filter(id => typeof id === 'string').slice(0, 3) : [];
  } catch {
    return [];
  }
}

export async function recordPrayerAccess(prayerId: string): Promise<string[]> {
  const current = await loadRecentPrayerIds();
  const next = [prayerId, ...current.filter(id => id !== prayerId)].slice(0, 3);
  await AsyncStorage.setItem(RECENT_PRAYERS_KEY, JSON.stringify(next));
  return next;
}

export async function loadFavoritePrayerIds(): Promise<string[]> {
  try {
    const raw = await AsyncStorage.getItem(FAVORITE_PRAYERS_KEY);
    const ids = raw ? JSON.parse(raw) : [];
    return Array.isArray(ids) ? ids.filter(id => typeof id === 'string') : [];
  } catch {
    return [];
  }
}

export async function togglePrayerFavorite(prayerId: string): Promise<string[]> {
  const current = await loadFavoritePrayerIds();
  const next = current.includes(prayerId)
    ? current.filter(id => id !== prayerId)
    : [prayerId, ...current];
  await AsyncStorage.setItem(FAVORITE_PRAYERS_KEY, JSON.stringify(next));
  return next;
}
