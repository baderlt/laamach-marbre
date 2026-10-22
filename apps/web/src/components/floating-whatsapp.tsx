import type { SiteSettings } from '@/lib/site';
import { SiteIcon } from './site-icon';

export function FloatingWhatsApp({ settings, locale }: { settings: SiteSettings | null; locale: string }) {
  const contact = settings?.whatsapp;
  const enabled = contact?.enabled && contact.number && /^[1-9]\d{7,14}$/.test(contact.number);
  const label = locale === 'ar' ? 'تواصل معنا عبر واتساب' : 'Discuter sur WhatsApp';
  const greeting = locale === 'ar' ? 'السلام عليكم LAAMACH MARBRE، أود الاستفسار عن الحجر الطبيعي ومناقشة مشروعي.' : 'Bonjour LAAMACH MARBRE, je souhaite me renseigner sur vos pierres naturelles et discuter de mon projet.';
  const url = enabled ? `https://wa.me/${contact.number}?text=${encodeURIComponent(greeting)}` : undefined;
  const accessibleLabel = `${label} (${locale === 'ar' ? 'نافذة جديدة' : 'nouvelle fenêtre'})`;
  return <aside aria-label={locale === 'ar' ? 'تواصل معنا' : 'Nous contacter'}>
    {url && <a className="floating-whatsapp" href={url} target="_blank" rel="noopener noreferrer" aria-label={accessibleLabel}><SiteIcon name="whatsapp" /><span>{label}</span></a>}
    <nav className="mobile-contact-bar" aria-label={locale === 'ar' ? 'التواصل السريع' : 'Contact rapide'}>
      <a className="mobile-contact-quote" href={`/${locale}/contact#contact-form`} aria-label={locale === 'ar' ? 'طلب عرض سعر' : 'Demander un devis'}><SiteIcon name="mail" /><span>{locale === 'ar' ? 'طلب عرض سعر' : 'Devis'}</span></a>
      {url && <a className="mobile-contact-whatsapp" href={url} target="_blank" rel="noopener noreferrer" aria-label={accessibleLabel}><SiteIcon name="whatsapp" /><span>{locale === 'ar' ? 'واتساب' : 'WhatsApp'}</span></a>}
    </nav>
  </aside>;
}
