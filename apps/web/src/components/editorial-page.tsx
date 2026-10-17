import { getSite } from '@/lib/site-server';
import { siteCopy } from '@/lib/site';
import { PageHero, SectionHeading, PageSections } from './page-design';
import { CatalogueImage } from './catalogue-image';
import { ProjectCards } from './project-cards';
import { ContactBanner } from './site-footer';
import { SiteIcon } from './site-icon';

export async function EditorialPageView({ locale, kind }: { locale: string; kind: 'services' | 'about' }) {
  const copy = siteCopy(locale); const site = await getSite(locale); const page = site.pages[kind];
  const story = page?.sections.filter(section => section.group === 'story') || [];
  return <main id="main" className={`public-page ${kind}-page`}>
    <PageHero locale={locale} title={page?.title || (kind === 'about' ? copy.aboutTitle : copy.servicesTitle)} eyebrow={page?.eyebrow || copy[kind]} body={page?.body || copy.emptyEditorial} media={page?.hero} large><a className="button bronze" href={`/${locale}/contact`}>{copy.contactAction}<SiteIcon name="arrow" className="directional" /></a></PageHero>
    {kind === 'about' && <section className="section site-width story-section"><CatalogueImage media={page?.secondary || null} placeholder={copy.missingMedia} /><div><p className="eyebrow">{copy.story}</p><h2>{story[0]?.title || copy.about}</h2><p className="preserve-text">{story[0]?.body || copy.emptyEditorial}</p><a className="button bronze" href={`/${locale}/projects`}>{copy.projects}<SiteIcon name="arrow" className="directional" /></a></div><CatalogueImage media={story[0]?.media || null} placeholder={copy.missingMedia} /></section>}
    <PageSections locale={locale} page={page} group={kind === 'about' ? 'trust' : 'services'} title={kind === 'about' ? copy.values : copy.services} />
    <PageSections locale={locale} page={page} group={kind === 'about' ? 'services' : 'process'} title={kind === 'about' ? copy.services : copy.process} />
    <section className="section site-width"><SectionHeading title={copy.featuredProjects} />{site.featured_projects.length ? <ProjectCards projects={site.featured_projects} locale={locale} /> : <div className="editorial-empty"><p>{copy.emptyProjects}</p></div>}</section>
    <ContactBanner locale={locale} />
  </main>;
}
