import { getSite } from '@/lib/site-server';
import { siteCopy } from '@/lib/site';
import { PageHero, SectionHeading, PageSections, BenefitsStrip } from './page-design';
import { CatalogueImage } from './catalogue-image';
import { ProjectCards } from './project-cards';
import { ContactBanner } from './site-footer';
import { SiteIcon } from './site-icon';

export async function EditorialPageView({ locale, kind }: { locale: string; kind: 'services' | 'about' }) {
  const copy = siteCopy(locale); const site = await getSite(locale); const page = site.pages[kind];
  const story = page?.sections.filter(section => section.group === 'story') || [];
  return <main id="main" className={`public-page ${kind}-page`}>
    <PageHero locale={locale} title={page?.title || (kind === 'about' ? copy.aboutTitle : copy.servicesTitle)} eyebrow={page?.eyebrow || copy[kind]} body={page?.body || copy.emptyEditorial} media={page?.hero} large><a className="button bronze" href={`/${locale}/contact`}>{copy.contactAction}<SiteIcon name="arrow" className="directional" /></a></PageHero>
    {kind === 'services' && <BenefitsStrip locale={locale} sections={page?.sections.filter(section => section.group === 'trust')} />}
    {kind === 'about' && <section className="section site-width story-section"><CatalogueImage media={page?.secondary || null} alt={story[0]?.title || copy.story} placeholder={copy.missingMedia} /><div><p className="eyebrow">{copy.story}</p><h2>{story[0]?.title || copy.about}</h2><p className="preserve-text">{story[0]?.body || copy.emptyEditorial}</p><a className="button bronze" href={`/${locale}/projects`}>{copy.projects}<SiteIcon name="arrow" className="directional" /></a></div><CatalogueImage media={story[0]?.media || null} alt={story[0]?.title || copy.story} placeholder={copy.missingMedia} /></section>}
    <PageSections locale={locale} page={page} group={kind === 'about' ? 'trust' : 'services'} title={kind === 'about' ? copy.values : copy.services} />
    {kind === 'services' ? <PageSections locale={locale} page={page} group="process" title={copy.process} /> : <section className="craft-montage site-width"><div><p className="eyebrow">{copy.preparation}</p><h2>{page?.sections.find(section => section.group === 'services')?.title || copy.details}</h2><p>{page?.sections.find(section => section.group === 'services')?.body || copy.preparationBody}</p><a className="button bronze" href={`/${locale}/services`}>{copy.services}<SiteIcon name="arrow" className="directional" /></a></div>{[page?.secondary, story[0]?.media, page?.sections.find(section => section.group === 'services')?.media].map((media, index) => <CatalogueImage key={index} media={media || null} alt={copy.details} placeholder={copy.missingMedia} />)}</section>}
    <section className="section site-width editorial-projects"><SectionHeading title={copy.featuredProjects} /><ProjectCards projects={site.featured_projects.slice(0, 5)} locale={locale} slots={kind === 'about' ? 4 : 5} /></section>
    <ContactBanner locale={locale} pageKey={kind} />
  </main>;
}
