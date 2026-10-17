import { notFound } from 'next/navigation';
import { backendAvailable } from '@/lib/backend';
import { findLocale } from '@/lib/locales';
import { getSite, pageMetadata } from '@/lib/site-server';
import { siteCopy } from '@/lib/site';
import { catalogueRequest } from '@/lib/catalogue-server';
import type { CategoryPage, Media } from '@/lib/catalogue';
import type { ProjectCategory } from '@/lib/projects';
import { PageHero, SectionHeading, EditorialCards, PageSections } from '@/components/page-design';
import { ProductCards } from '@/components/product-cards';
import { ProjectCards } from '@/components/project-cards';
import { CatalogueImage } from '@/components/catalogue-image';
import { ContactBanner } from '@/components/site-footer';
import { SiteIcon } from '@/components/site-icon';

export async function generateMetadata({ params }: { params: Promise<{ locale: string }> }) { const { locale } = await params; if (!findLocale(locale)) return {}; return pageMetadata(locale, 'home', ''); }

export default async function FoundationPage({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  if (!findLocale(locale)) notFound();
  const copy = siteCopy(locale);
  const [site, categories, usages, available] = await Promise.all([getSite(locale), catalogueRequest<CategoryPage>('product-categories', locale), catalogueRequest<{ data: ProjectCategory[] }>('project-categories', locale), backendAvailable()]);
  const page = site.pages.home;
  const activities = page?.sections.filter(section => section.group === 'activities') || [];
  const collections = categories.data?.data.flatMap(category => category.children?.length ? category.children : [category]) || [];
  return <main id="main" className="public-page homepage" data-backend-status={available ? 'ok' : 'unavailable'}>
    <PageHero locale={locale} title={page?.title || copy.heroTitle} eyebrow={page?.eyebrow || copy.heroEyebrow} body={page?.body || copy.heroBody} media={page?.hero} large><a className="button bronze" href={`/${locale}/products`}>{copy.products}<SiteIcon name="stone" /></a><a className="button outline" href={`/${locale}/projects`}>{copy.projects}<SiteIcon name="building" /></a></PageHero>
    <section className="section site-width"><SectionHeading title={copy.activities} body={copy.activitiesIntro} /><EditorialCards locale={locale} variant="activities-grid" sections={activities.length ? activities : [{ group: 'activities', title: copy.products, body: null, icon: 'stone', link: 'products', media: null }, { group: 'activities', title: copy.projects, body: null, icon: 'building', link: 'projects', media: null }]} /></section>
    <section className="section site-width"><SectionHeading title={copy.categories} />{collections.length ? <div className="collection-grid">{collections.slice(0, 6).map(category => <a className="collection-card" key={category.id} href={`/${locale}/products?category=${category.slug}`}><CatalogueImage media={'cover' in category ? category.cover as Media | null : null} placeholder={copy.missingMedia} /><span>{category.name}<SiteIcon name="arrow" className="directional" /></span></a>)}</div> : <div className="editorial-empty"><SiteIcon name="stone" /><p>{copy.emptyProducts}</p></div>}</section>
    <section className="section site-width"><SectionHeading title={copy.featuredProducts} action={<a className="button bronze" href={`/${locale}/products`}>{copy.allProducts}<SiteIcon name="arrow" className="directional" /></a>} />{site.featured_products.length ? <ProductCards products={site.featured_products} locale={locale} compact /> : <div className="editorial-empty"><p>{copy.emptyProducts}</p></div>}</section>
    <section className="section site-width"><SectionHeading title={copy.featuredProjects} action={<a className="button bronze" href={`/${locale}/projects`}>{copy.allProjects}<SiteIcon name="arrow" className="directional" /></a>} />{site.featured_projects.length ? <ProjectCards projects={site.featured_projects} locale={locale} /> : <div className="editorial-empty"><p>{copy.emptyProjects}</p></div>}</section>
    <section className="usage-section"><div className="site-width"><SectionHeading title={copy.usages} />{usages.data?.data.length ? <div className="usage-links">{usages.data.data.map(category => <a key={category.id} href={`/${locale}/projects?category=${category.slug}`}><SiteIcon name="building" />{category.name}</a>)}</div> : <p className="centered muted">{copy.emptyProjects}</p>}</div></section>
    <PageSections locale={locale} page={page} group="trust" title={copy.trust} />
    <ContactBanner locale={locale} />
  </main>;
}
