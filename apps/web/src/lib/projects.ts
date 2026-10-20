import type { Media, ProductCard, Variant, ProductPage } from './catalogue.ts';
import { localeConfiguration } from './locales.ts';

export type ProjectCategory = { id: string; slug: string; name: string; description?: string | null; cover?: Media | null };
export type ProjectCard = { reference: string; slug: string; title: string; summary: string | null; featured: boolean; categories: ProjectCategory[]; cover: Media | null };
export type GalleryItem = { type: 'image'; media: Media; caption: string | null; poster: null } | { type: 'video'; media: { id: string; duration: string; variants: Variant[] }; caption: string | null; poster: Media | null };
export type Project = ProjectCard & { available_locales?: string[]; description: string | null; area_display: string | null; seo_title: string | null; seo_description: string | null; location: string | null; year: number | null; area: string | null; area_unit: string | null; gallery: GalleryItem[]; products: ProductCard[]; related_projects: ProjectCard[] };
export type ProjectsPage = { data: ProjectCard[]; meta: ProductPage['meta'] };

const messages = {
  fr: { projects: 'Projets', categories: 'Catégories d’usage', empty: 'Aucun projet à afficher.', unavailable: 'Projets temporairement indisponibles.', materials: 'Matières à découvrir pour votre projet', related: 'Des idées pour votre projet', gallery: 'Les réalisations de cette collection', cover: 'Vue principale', photo: 'Photo', video: 'Vidéo', missingVideo: 'Vidéo du projet', year: 'Année', location: 'Lieu', area: 'Surface' },
  ar: { projects: 'المشاريع', categories: 'فئات الاستخدام', empty: 'لا توجد مشاريع للعرض.', unavailable: 'المشاريع غير متاحة مؤقتًا.', materials: 'مواد مقترحة لمشروعك', related: 'أفكار لمشروعك', gallery: 'أعمال ضمن هذه الفئة', cover: 'الصورة الرئيسية', photo: 'صورة', video: 'فيديو', missingVideo: 'فيديو المشروع', year: 'السنة', location: 'المكان', area: 'المساحة' },
};
export function projectsCopy(locale: string) {
  const copy = messages[locale as keyof typeof messages] || messages[localeConfiguration.fallback as keyof typeof messages];
  if (!copy) throw new Error('The configured fallback requires project translations');
  return copy;
}

export function projectFilters(input: Record<string, string | string[] | undefined>): URLSearchParams {
  const filters = new URLSearchParams();
  const supplied = input['categories[]'] ?? input.categories ?? input.category;
  const categories = typeof supplied === 'string' ? [supplied] : supplied || [];
  for (const slug of [...new Set(categories)].filter(value => /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(value) && value.length <= 120).slice(0, 8)) filters.append('categories[]', slug);
  if (typeof input.page === 'string' && /^(?:[1-9][0-9]{0,3}|10000)$/.test(input.page)) filters.set('page', input.page);
  return filters;
}

export function projectCategoryIcon(slug: string): string {
  if (slug.includes('sol')) return 'paving';
  if (slug.includes('cascade') || slug.includes('fontaine')) return 'fountain';
  if (slug.includes('tradition')) return 'masonry';
  return 'facade';
}
