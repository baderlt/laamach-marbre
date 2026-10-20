import type { SiteSettings } from '@/lib/site';
import { SiteIcon } from './site-icon';

export function FloatingWhatsApp({ settings, locale }: { settings: SiteSettings | null; locale: string }) {
  const contact = settings?.whatsapp;
  if (!contact?.enabled || !contact.number || !/^[1-9]\d{7,14}$/.test(contact.number)) return null;
  const label = locale === 'ar' ? 'تواصل معنا عبر واتساب' : 'Discuter sur WhatsApp';
  const greeting = locale === 'ar' ? 'السلام عليكم LAAMACH MARBRE، أود الاستفسار عن الحجر الطبيعي ومناقشة مشروعي.' : 'Bonjour LAAMACH MARBRE, je souhaite me renseigner sur vos pierres naturelles et discuter de mon projet.';
  return <aside aria-label={label}><a className="floating-whatsapp" href={`https://wa.me/${contact.number}?text=${encodeURIComponent(greeting)}`} target="_blank" rel="noopener noreferrer" aria-label={`${label} (${locale === 'ar' ? 'نافذة جديدة' : 'nouvelle fenêtre'})`}><SiteIcon name="whatsapp" /><span>{label}</span></a></aside>;
}
