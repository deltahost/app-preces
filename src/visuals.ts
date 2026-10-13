export const PEXELS_IMAGES = {
  sunrise: 'https://images.pexels.com/photos/2088169/pexels-photo-2088169.jpeg?auto=compress&cs=tinysrgb&w=1200',
  prayer: 'https://images.pexels.com/photos/267559/pexels-photo-267559.jpeg?auto=compress&cs=tinysrgb&w=1200',
  forest: 'https://images.pexels.com/photos/30725751/pexels-photo-30725751.jpeg?auto=compress&cs=tinysrgb&w=1200',
  cross: 'https://images.pexels.com/photos/24709421/pexels-photo-24709421.jpeg?auto=compress&cs=tinysrgb&w=1200',
  family: 'https://images.pexels.com/photos/17122453/pexels-photo-17122453.jpeg?auto=compress&cs=tinysrgb&w=1200',
  bibleOpen: 'https://images.pexels.com/photos/28896476/pexels-photo-28896476.jpeg?auto=compress&cs=tinysrgb&w=1200',
} as const;

export const PEXELS_CREDITS = [
  'https://www.pexels.com/photo/photo-of-mountains-during-dawn-2088169/',
  'https://www.pexels.com/photo/person-hands-on-holy-bible-267559/',
  'https://www.pexels.com/photo/peaceful-forest-scene-with-sunlight-filtering-through-trees-30725751/',
  'https://www.pexels.com/photo/cross-in-mountains-at-sunset-24709421/',
  'https://www.pexels.com/photo/family-posing-together-for-portrait-17122453/',
  'https://www.pexels.com/photo/open-bible-on-pulpit-in-sunlit-church-interior-28896476/',
] as const;

export function categoryImage(slug?: string) {
  const images: Record<string, string> = {
    pessoal: PEXELS_IMAGES.sunrise,
    familia: PEXELS_IMAGES.family,
    trabalho: PEXELS_IMAGES.forest,
    relacionamento: PEXELS_IMAGES.family,
    saude: PEXELS_IMAGES.forest,
    estudos: PEXELS_IMAGES.prayer,
    financas: PEXELS_IMAGES.sunrise,
    conquistas: PEXELS_IMAGES.cross,
    protecao: PEXELS_IMAGES.cross,
  };
  return images[slug ?? ''] ?? PEXELS_IMAGES.sunrise;
}
