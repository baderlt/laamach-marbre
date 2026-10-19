import type { ViewerConfiguration } from './viewer.ts';
import { localeConfiguration } from './locales.ts';
import type { ProjectCard } from './projects.ts';

export type Variant = { url: string; width: number; height: number; format: string; profile: string; revision: string; hash: string };
export type Media = { id: string; alt: string; variants: Variant[] };
export type Category = { id: string; slug: string; name: string; description?: string; cover?: Media | null; children?: Category[] };
export type Finish = { id: string; slug: string; name: string; is_default?: boolean };
export type ProductCard = { reference: string; slug: string; name: string; featured: boolean; parent_category: Category; category: Category; cover: Media | null };
export type Size = { id: string; label: string; width: string | null; length: string | null; height: string | null; thickness: string | null; measurement_unit: string; cover: Media | null };
export type Product = ProductCard & { viewer_3d?: ViewerConfiguration | null; projects: ProjectCard[]; description: string | null; colour: string | null; origin: string | null; seo_title: string | null; seo_description: string | null; gallery: Media[]; sizes: Size[]; custom_size_allowed: boolean; custom_size: { measurement_unit: string; min_width: string; max_width: string; min_length: string; max_length: string } | null; quantity_units: string[]; finishes: Finish[]; technical_information: { label: string; value: string }[] | null };
export type ProductPage = { data: ProductCard[]; meta: { current_page: number; last_page: number; per_page: number; total: number } };
export type CategoryPage = { data: Category[]; finishes: Finish[] };

const messages = {
  fr: { catalogue: 'Catalogue', home: 'Accueil', parent: 'Famille', child: 'Catégorie', finish: 'Finition', all: 'Toutes', apply: 'Appliquer', previous: 'Précédent', next: 'Suivant', empty: 'Aucun produit à afficher.', missingImage: 'Image non fournie', sizes: 'Dimensions standard', custom: 'Dimensions personnalisées sur demande. La faisabilité est confirmée par l’entreprise.', bounds: 'Limites des dimensions personnalisées', width: 'Largeur', length: 'Longueur', units: 'Unités de quantité autorisées', technical: 'Informations techniques', colour: 'Couleur', origin: 'Origine', image: 'Vue du produit', mainImage: 'Vue principale', gallery: 'Galerie', sizeNote: 'Les options présentées ne constituent pas une garantie de disponibilité commerciale.', unavailable: 'Catalogue temporairement indisponible.' },
  ar: { catalogue: 'الكتالوج', home: 'الرئيسية', parent: 'العائلة', child: 'الفئة', finish: 'التشطيب', all: 'الكل', apply: 'تطبيق', previous: 'السابق', next: 'التالي', empty: 'لا توجد منتجات للعرض.', missingImage: 'لم تقدم صورة', sizes: 'الأبعاد القياسية', custom: 'الأبعاد المخصصة عند الطلب. تؤكد الشركة إمكانية التنفيذ.', bounds: 'حدود الأبعاد المخصصة', width: 'العرض', length: 'الطول', units: 'وحدات الكمية المسموح بها', technical: 'المعلومات التقنية', colour: 'اللون', origin: 'المنشأ', image: 'صورة المنتج', mainImage: 'الصورة الرئيسية', gallery: 'معرض الصور', sizeNote: 'الخيارات المعروضة لا تضمن التوفر التجاري.', unavailable: 'الكتالوج غير متاح مؤقتًا.' },
};
export function catalogueCopy(locale: string) {
  const result = messages[locale as keyof typeof messages] || messages[localeConfiguration.fallback as keyof typeof messages];
  if (!result) throw new Error('The configured fallback requires catalogue translations');
  return result;
}

export function selectedImage(product: Pick<Product, 'cover' | 'sizes'>, sizeId: string | null): Media | null {
  return product.sizes.find(size => size.id === sizeId)?.cover ?? product.cover;
}

export function catalogueFilters(input: Record<string, string | string[] | undefined>): URLSearchParams {
  const result = new URLSearchParams();
  for (const key of ['parent', 'category', 'finish']) {
    const value = input[key];
    if (typeof value === 'string' && /^[a-z0-9-]{1,120}$/.test(value)) result.set(key, value);
  }
  if (typeof input.page === 'string' && /^[1-9][0-9]{0,3}$|^10000$/.test(input.page)) result.set('page', input.page);
  if (typeof input.q === 'string' && input.q.trim().length > 0 && input.q.trim().length <= 120) result.set('q', input.q.trim());
  if (input.sort === 'name') result.set('sort', 'name');
  return result;
}
