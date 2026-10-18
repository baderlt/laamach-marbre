import type { Product } from './catalogue.ts';
import type { Project } from './projects.ts';
import { findLocale, localeConfiguration } from './locales.ts';
import { origin } from './environment.ts';
import { validateDraftText, validateMaterialDraft, type DraftFields } from './request-draft.ts';

export type RequestKind = 'material' | 'similar' | 'application';
export type WhatsAppSettings = { number: string | null; enabled: boolean; material: boolean; similar: boolean; application: boolean };
export type PreparedRequest = { message: string; url: string | null; canonical: string; oversized: boolean };
export const whatsappUrlBudget = 8000;
const copy = {
  fr: { material: 'Bonjour, je souhaite obtenir un devis pour ce matériau.', similar: 'Bonjour, je souhaite discuter d’un projet inspiré de cet exemple.', application: 'Bonjour, je souhaite utiliser cette pierre dans mon projet.', product: 'Produit', project: 'Projet de référence', reference: 'Référence', link: 'Lien', size: 'Dimensions du matériau', finish: 'Finition', quantity: 'Quantité', city: 'Ville de mon projet', dimensions: 'Dimensions envisagées pour mon projet', description: 'Détails de ma demande', projectType: 'Type de projet envisagé', use: 'Utilisation envisagée', end: 'Pourriez-vous me confirmer les possibilités et les conditions de cette demande ? Merci.', similarEnd: 'Je souhaite étudier un projet similaire, avec des caractéristiques à définir ensemble. Merci.' },
  ar: { material: 'السلام عليكم، أود طلب عرض سعر لهذا المنتج.', similar: 'السلام عليكم، أرغب في مناقشة مشروع مستوحى من هذا المثال.', application: 'السلام عليكم، أود استعمال هذا الحجر في مشروعي.', product: 'المنتج', project: 'المشروع المرجعي', reference: 'المرجع', link: 'الرابط', size: 'مقاس المادة', finish: 'التشطيب', quantity: 'الكمية', city: 'مدينة مشروعي', dimensions: 'الأبعاد المقترحة لمشروعي', description: 'تفاصيل طلبي', projectType: 'نوع المشروع المقترح', use: 'الاستخدام المقترح', end: 'المرجو تأكيد الإمكانيات والشروط المتاحة لهذا الطلب. شكراً.', similarEnd: 'أرغب في دراسة مشروع مشابه، مع تحديد خصائصه معكم. شكراً.' },
};
export function unitLabel(unit: string, locale: string): string {
  const labels = locale === 'ar' ? { m2: 'متر مربع', m3: 'متر مكعب', linear_m: 'متر طولي', piece: 'قطعة' } : { m2: 'm²', m3: 'm³', linear_m: 'mètre linéaire', piece: 'pièce' };
  return labels[unit as keyof typeof labels] || unit;
}
export function requestAvailable(settings: WhatsAppSettings | null | undefined, kind: RequestKind): boolean {
  return settings?.enabled === true && settings[kind] === true && typeof settings.number === 'string' && /^[1-9]\d{7,14}$/.test(settings.number);
}
export function parseRequest(value: unknown): { kind: RequestKind; slug: string; locale: string; fields: DraftFields } | null {
  if (!value || typeof value !== 'object' || Array.isArray(value)) return null;
  const data = value as Record<string, unknown>;
  if (Object.keys(data).some(key => !['kind', 'slug', 'locale', 'fields'].includes(key)) || typeof data.kind !== 'string' || !['material', 'similar', 'application'].includes(data.kind) || typeof data.slug !== 'string' || !/^[a-z0-9-]{1,120}$/.test(data.slug) || typeof data.locale !== 'string' || !findLocale(data.locale) || !data.fields || typeof data.fields !== 'object' || Array.isArray(data.fields)) return null;
  const fields = data.fields as Record<string, unknown>;
  const allowed = data.kind === 'material' ? ['size', 'custom', 'width', 'length', 'finish', 'quantity', 'unit', 'city', 'description'] : data.kind === 'similar' ? ['city', 'dimensions', 'description'] : ['city', 'dimensions', 'description', 'projectType', 'application'];
  if (Object.entries(fields).some(([key, entry]) => !allowed.includes(key) || (key === 'custom' ? typeof entry !== 'boolean' : typeof entry !== 'string' || entry.length > 4000))) return null;
  const clean: DraftFields = { size: '', custom: false, width: '', length: '', finish: '', quantity: '', unit: '', city: '', dimensions: '', description: '', projectType: '', application: '' };
  for (const [key, entry] of Object.entries(fields)) {
    if (key === 'custom') clean.custom = entry as boolean;
    else Object.assign(clean, { [key]: (entry as string).replace(/\r\n/g, '\n').trim() });
  }
  return { kind: data.kind as RequestKind, slug: data.slug, locale: data.locale, fields: clean };
}

// Call only with freshly fetched public API records, never visitor-supplied identity or settings.
export function prepareRequest(kind: RequestKind, locale: string, record: Product | Project, fields: DraftFields, settings: WhatsAppSettings | null | undefined, siteOrigin: string): { errors: string[]; prepared?: PreparedRequest } {
  if (!findLocale(locale) || !requestAvailable(settings, kind)) return { errors: ['unavailable'] };
  const product = kind === 'similar' ? null : record as Product;
  const errors = kind === 'material' && product ? validateMaterialDraft(product, fields) : validateDraftText(fields);
  if (errors.length) return { errors };
  const text = copy[locale as keyof typeof copy] || copy[localeConfiguration.fallback as keyof typeof copy];
  if (!text) return { errors: ['unavailable'] };
  const canonical = `${origin(siteOrigin, 'SITE_URL')}/${locale}/${kind === 'similar' ? 'projects' : 'products'}/${encodeURIComponent(record.slug)}`;
  const rows: [string, string | undefined][] = [[product ? text.product : text.project, product ? product.name : (record as Project).title], [text.reference, record.reference], [text.link, canonical]];
  if (kind === 'material' && product) {
    const size = product.sizes.find(size => size.id === fields.size);
    rows.push([text.size, fields.custom ? `${fields.width} × ${fields.length} ${product.custom_size!.measurement_unit}` : size ? `${size.label}${size.measurement_unit ? ` (${size.measurement_unit})` : ''}` : undefined], [text.finish, product.finishes.find(finish => finish.id === fields.finish)?.name], [text.quantity, `${fields.quantity} ${unitLabel(fields.unit, locale)}`]);
  }
  if (kind === 'application') rows.push([text.projectType, fields.projectType], [text.use, fields.application]);
  rows.push([text.city, fields.city], [text.dimensions, fields.dimensions], [text.description, fields.description]);
  // Isolate each value in RTL text, preserving Latin references, URLs and dimension order.
  // Visitor-supplied direction controls are rejected by validation; these are generated here.
  const display = (value: string) => findLocale(locale)?.direction === 'rtl' ? value.split('\n').map(line => `\u2068${line}\u2069`).join('\n') : value;
  const message = [text[kind], '', ...rows.filter(([, value]) => !!value).map(([label, value]) => `${label} : ${display(value!)}`), '', kind === 'similar' ? text.similarEnd : text.end].join('\n');
  const url = `https://wa.me/${settings!.number}?text=${encodeURIComponent(message)}`;
  const oversized = url.length > whatsappUrlBudget;
  return { errors: [], prepared: { message, canonical, oversized, url: oversized ? null : url } };
}
