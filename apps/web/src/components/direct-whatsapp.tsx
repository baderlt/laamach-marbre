import type { SiteSettings } from '@/lib/site';
import { directChatUrl } from '@/lib/whatsapp';
import { SiteIcon } from './site-icon';

export function DirectWhatsApp({ settings, locale, kind, title, reference, canonical }: { settings: SiteSettings | null; locale: string; kind: 'material' | 'similar'; title: string; reference: string; canonical: string }) {
  const url = directChatUrl(settings?.whatsapp, locale, kind, title, reference, canonical);
  if (!url) return null;
  return <a className="button direct-whatsapp" href={url} target="_blank" rel="noopener noreferrer"><SiteIcon name="phone" />{locale === 'ar' ? 'تحدث معنا مباشرة عبر واتساب' : 'Discuter directement sur WhatsApp'}</a>;
}
