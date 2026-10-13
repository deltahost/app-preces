import AsyncStorage from '@react-native-async-storage/async-storage';
import { categories as localCategories, Prayer } from '../data';

const CONTENT_API_URL = (process.env.EXPO_PUBLIC_CONTENT_API_URL ?? 'https://preces.criandoconteudo.com.br/api').replace(/\/$/, '');
const CACHE_KEY = '@preces/published-content-v1';

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

export type PublishedContent = {
  categories: ContentCategory[];
  prayers: PublishedPrayer[];
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
  };
}

export async function loadPublishedContent(): Promise<PublishedContent> {
  try {
    const [categoryPayload, prayerPayload] = await Promise.all([
      requestJson('categories.php'),
      requestJson('prayers.php?limit=100'),
    ]);
    const content: PublishedContent = {
      categories: Array.isArray(categoryPayload.items) ? categoryPayload.items.map(mapCategory) : [],
      prayers: Array.isArray(prayerPayload.items) ? prayerPayload.items.map(mapPrayer) : [],
    };
    if (!content.categories.length) content.categories = localFallback.categories;
    await AsyncStorage.setItem(CACHE_KEY, JSON.stringify(content));
    return content;
  } catch {
    const cached = await AsyncStorage.getItem(CACHE_KEY);
    if (cached) {
      try {
        return JSON.parse(cached) as PublishedContent;
      } catch {
        await AsyncStorage.removeItem(CACHE_KEY);
      }
    }
    return localFallback;
  }
}
