import type { EditorialPage, EditorialSection } from '@/lib/site';
import { siteCopy } from '@/lib/site';
import type { Media } from '@/lib/catalogue';
import { CatalogueImage } from './catalogue-image';
import { SiteIcon } from './site-icon';

export function PageHero({ locale, title, eyebrow, body, media, large = false, children, variant = '', aside, headingTag = 'h1' }: { locale: string; title: string; eyebrow?: string | null; body?: string | null; media?: Media | null; large?: boolean; children?: React.ReactNode; variant?: string; aside?: React.ReactNode; headingTag?: 'h1' | 'p' }) {
  const copy = siteCopy(locale);
  const stone = variant === 'brand' || variant === 'quarry' || (!media && variant !== 'material');
  const Heading = headingTag;
  return <section data-stone-surface={stone ? '' : undefined} className={`page-hero ${variant}${large ? ' large-hero' : ''}${stone ? ' stone-surface' : media ? ' has-media' : ' neutral-hero'}`}>
    {!stone && <div className="hero-media">{media ? <CatalogueImage media={media} alt={title} placeholder={copy.missingMedia} eager /> : <div className="hero-placeholder" role="img" aria-label={copy.missingMedia}><span>{copy.missingMedia}</span></div>}</div>}
    <div className="site-width hero-inner"><div className="hero-copy"><p className="eyebrow">{eyebrow || 'LAAMACH MARBRE'}</p><Heading className="hero-title">{title}</Heading>{body && <p className="hero-description preserve-text">{body}</p>}{children && <div className="hero-actions">{children}</div>}</div></div>
    {aside}
  </section>;
}

export function SectionHeading({ title, eyebrow, body, action }: { title: string; eyebrow?: string | null; body?: string | null; action?: React.ReactNode }) {
  return <div className={`section-heading${action ? ' with-action' : ''}`}><div>{eyebrow && <p className="eyebrow">{eyebrow}</p>}<h2>{title}</h2>{body && <p>{body}</p>}</div>{action}</div>;
}

export function EditorialCards({ locale, sections, variant = '' }: { locale: string; sections: EditorialSection[]; variant?: string }) {
  const copy = siteCopy(locale);
  return <div className={`editorial-grid ${variant}`}>{sections.map((section, index) => {
    const [intro, ...points] = variant === 'activities-grid' ? (section.body || '').split('\n') : [section.body || ''];
    return <article key={index} className="editorial-card">{!['trust-grid', 'process-grid'].includes(variant) && <CatalogueImage media={section.media} alt={section.title} placeholder={copy.missingMedia} />}<div className="editorial-card-copy">{variant === 'process-grid' && <span className="step-position" aria-hidden="true">{index + 1}</span>}<SiteIcon name={section.icon} /><h3>{section.title}</h3>{intro && <p className="preserve-text">{intro}</p>}{section.link && <a className={variant === 'activities-grid' ? 'button bronze' : 'text-link'} href={`/${locale}/${section.link}`}>{section.link === 'contact' ? copy.contactAction : copy.discover}<SiteIcon name="arrow" className="directional" /></a>}{points.length > 0 && <ul className="activity-points">{points.map(point => <li key={point}><SiteIcon name="check" />{point}</li>)}</ul>}</div></article>;
  })}</div>;
}

export function BenefitsStrip({ locale, sections }: { locale: string; sections?: EditorialSection[] }) {
  const rows = sections?.length ? sections.slice(0, 4) : [{ title: siteCopy(locale).details, icon: 'stone' }, { title: siteCopy(locale).standardNote, icon: 'ruler' }, { title: siteCopy(locale).contactAction, icon: 'craft' }, { title: siteCopy(locale).technical, icon: 'check' }];
  return <div className="benefits-strip"><div className="site-width">{rows.map((row, index) => <div key={index}><SiteIcon name={row.icon} /><span>{row.title}</span></div>)}</div></div>;
}

export function PageSections({ locale, page, group, title }: { locale: string; page?: EditorialPage; group: EditorialSection['group']; title: string }) {
  const rows = page?.sections.filter(section => section.group === group) || [];
  const variant = group === 'trust' ? 'trust-grid' : group === 'process' ? 'process-grid' : '';
  const placeholders: EditorialSection[] = Array.from({ length: group === 'process' ? 5 : group === 'trust' ? 4 : 6 }, () => ({ group, title: siteCopy(locale).notProvided, body: null, icon: group === 'process' ? 'ruler' : 'stone', media: null }));
  return <section className="section site-width"><SectionHeading title={title} />{!rows.length && <p className="editorial-notice">{siteCopy(locale).emptyEditorial}</p>}<EditorialCards locale={locale} sections={rows.length ? rows : placeholders} variant={variant} /></section>;
}
