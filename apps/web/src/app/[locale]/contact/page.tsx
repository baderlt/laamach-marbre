import { notFound } from 'next/navigation';
import { findLocale } from '@/lib/locales';
import { getSite, pageMetadata } from '@/lib/site-server';
import { siteCopy, phoneHref } from '@/lib/site';
import { PageHero, SectionHeading } from '@/components/page-design';
import { CatalogueImage } from '@/components/catalogue-image';
import { ContactForm } from '@/components/contact-form';
import { SiteIcon } from '@/components/site-icon';
import { editorialMedia } from '@/lib/editorial-media';
import { contactChatUrl } from '@/lib/contact';
import { PortfolioMotion } from '@/components/portfolio-motion';

export async function generateMetadata({ params }: { params: Promise<{ locale: string }> }) { const { locale } = await params; if (!findLocale(locale)) return {}; return pageMetadata(locale, 'contact', '/contact'); }
export default async function Contact({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params; if (!findLocale(locale)) notFound();
  const copy = siteCopy(locale), site = await getSite(locale), settings = site.settings, ar = locale === 'ar';
  const chat = contactChatUrl(settings?.whatsapp, locale);
  const hero = site.pages.contact?.hero || editorialMedia('about', locale);
  const support = ar ? [
    { icon: 'stone', title: 'اختيار الحجر الطبيعي', body: 'نقارن معك الملامس والتدرجات لتحديد المادة التي تلائم فكرتك.', link: 'products', action: 'اكتشف المواد' },
    { icon: 'building', title: 'مشروع من الحجر والرخام', body: 'من أول فكرة إلى تفاصيل المقاسات والتشطيب، لنتحدث عن مشروعك.', link: 'projects', action: 'شاهد أعمالنا' },
    { icon: 'truck', title: 'في جميع أنحاء المغرب', body: 'التوصيل وخدمات البناء في جميع أنحاء المغرب، حسب تفاصيل طلبك.', link: 'services', action: 'اكتشف خدماتنا' },
  ] : [
    { icon: 'stone', title: 'Choisir votre pierre', body: 'Textures et nuances : échangeons sur la matière qui donnera son caractère à votre projet.', link: 'products', action: 'Explorer les matières' },
    { icon: 'building', title: 'Construire votre projet', body: 'De la première idée aux dimensions et aux finitions, précisons ensemble votre demande.', link: 'projects', action: 'Voir les réalisations' },
    { icon: 'truck', title: 'Partout au Maroc', body: 'Livraison et services de construction partout au Maroc, selon les détails de votre demande.', link: 'services', action: 'Découvrir nos services' },
  ];
  return <main id="main" className="public-page contact-page premium-contact">
    <PageHero locale={locale} title={ar ? 'تواصل معنا' : 'Parlons de votre projet'} eyebrow={ar ? 'الحجر، وفكرتك' : 'La pierre, votre idée'} body={ar ? 'من فكرتك إلى تفاصيل الحجر، فريقنا يستمع إليك لنتصور معاً مشروعك المقبل.' : 'Une idée à concrétiser, une pierre à choisir. Notre équipe vous accompagne pour préciser les détails de votre projet.'} media={hero} large variant="photo-banner"><a className="button bronze" href="#contact-form">{ar ? 'حدثنا عن مشروعك' : 'Présentez votre projet'}<SiteIcon name="arrow" className="directional" /></a></PageHero>
    <div className="site-width contact-body">
      <section className="contact-info-grid" aria-label={copy.footerContact} data-motion-group>
        {settings?.phone && <a className="contact-info-card" href={phoneHref(settings.phone)}><SiteIcon name="phone" /><h2>{copy.phone}</h2><bdi dir="ltr">{settings.phone}</bdi><span>{ar ? 'اتصل بفريقنا' : 'Appeler notre équipe'}<SiteIcon name="arrow" className="directional" /></span></a>}
        {chat && <a className="contact-info-card" href={chat.url} target="_blank" rel="noopener noreferrer"><SiteIcon name="whatsapp" /><h2>WhatsApp</h2><p>{ar ? 'لنتحدث مباشرة عن فكرتك' : 'Un échange direct sur votre idée'}</p><span>{ar ? 'ابدأ المحادثة' : 'Commencer la discussion'}<SiteIcon name="arrow" className="directional" /></span></a>}
        {settings?.email ? <a className="contact-info-card" href={`mailto:${settings.email}`}><SiteIcon name="mail" /><h2>{copy.email}</h2><bdi>{settings.email}</bdi><span>{ar ? 'اكتب لنا' : 'Nous écrire'}<SiteIcon name="arrow" className="directional" /></span></a> : <article className="contact-info-card"><SiteIcon name="truck" /><h2>{ar ? 'في جميع أنحاء المغرب' : 'Partout au Maroc'}</h2><p>{ar ? 'التوصيل وخدمات البناء' : 'Livraison et construction'}</p><span>{ar ? 'حسب تفاصيل مشروعك' : 'Selon votre projet'}</span></article>}
        <a className="contact-info-card" href="#contact-form"><SiteIcon name="ruler" /><h2>{ar ? 'لنحضّر مشروعك' : 'Préparons votre projet'}</h2><p>{ar ? 'المادة، المقاسات وفكرتك' : 'La matière, les dimensions, votre idée'}</p><span>{ar ? 'أرسل التفاصيل' : 'Partager les détails'}<SiteIcon name="arrow" className="directional" /></span></a>
      </section>
      <section className="contact-studio" aria-label={ar ? 'طلب معلومات أو عرض سعر' : 'Demander des informations ou un devis'}>
        <div className="contact-quarry"><CatalogueImage media={editorialMedia('services', locale)} alt={ar ? 'كتل الحجر الطبيعي في المحجر' : 'Blocs de pierre naturelle dans la carrière'} placeholder={copy.missingMedia} /><div><p className="eyebrow">LAAMACH MARBRE</p><h2>{ar ? 'كل مشروع يبدأ بلقاء' : 'Chaque projet commence par un échange'}</h2><p>{ar ? 'الحجر الطبيعي، خبرة فريقنا والعناية بالتفاصيل لخدمة فكرتك.' : 'La pierre naturelle, le savoir-faire et le goût du détail au service de votre idée.'}</p></div></div>
        <ContactForm locale={locale} settings={settings} />
      </section>
      <section className="contact-support"><SectionHeading title={ar ? 'كيف يمكننا مساعدتك؟' : 'Comment pouvons-nous vous accompagner ?'} /><div className="contact-support-grid" data-motion-group>{support.map(item => <a key={item.link} href={`/${locale}/${item.link}`}><SiteIcon name={item.icon} /><h3>{item.title}</h3><p>{item.body}</p><span>{item.action}<SiteIcon name="arrow" className="directional" /></span></a>)}</div></section>
      {(settings?.address || settings?.map_url) && <section className="contact-location"><SiteIcon name="pin" /><div><h2>{copy.address}</h2>{settings.address && <p className="preserve-text">{settings.address}</p>}</div>{settings.map_url && <a className="button outline" href={settings.map_url} target="_blank" rel="noopener noreferrer">{copy.map}<SiteIcon name="arrow" className="directional" /></a>}</section>}
      <section className="contact-proof"><div><p className="eyebrow">{ar ? 'خبرتنا في خدمة مشروعك' : 'Notre expérience, votre projet'}</p><h2>{ar ? 'نرافقك في كل التفاصيل' : 'À vos côtés, dans chaque détail'}</h2></div><dl><div><dt>150+</dt><dd>{ar ? 'عميل راضٍ' : 'clients satisfaits'}</dd></div><div><dt>100%</dt><dd>{ar ? 'حجر مغربي' : 'pierre marocaine'}</dd></div><div><dt>10+</dt><dd>{ar ? 'سنوات من الخبرة' : 'ans d’expérience'}</dd></div></dl></section>
      {settings?.social_links.length ? <nav className="contact-social-links" aria-label={copy.footerSocial}>{settings.social_links.map(link => <a key={link.url} href={link.url} target="_blank" rel="noopener noreferrer">{link.label}<SiteIcon name="arrow" /></a>)}</nav> : null}
      <PortfolioMotion identity="contact" />
    </div>
  </main>;
}
