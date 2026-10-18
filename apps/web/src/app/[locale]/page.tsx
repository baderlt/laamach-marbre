import { notFound } from 'next/navigation';
import { backendAvailable } from '@/lib/backend';
import { findLocale } from '@/lib/locales';
import { getSite, pageMetadata } from '@/lib/site-server';
import { siteCopy } from '@/lib/site';
import { catalogueRequest } from '@/lib/catalogue-server';
import type { CategoryPage } from '@/lib/catalogue';
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
  const collectionStories = page?.sections.filter(section => section.group === 'services') || [];
  return <main id="main" className="public-page homepage" data-backend-status={available ? 'ok' : 'unavailable'}>
    <nav className="brand-rail" aria-label={copy.menu}>{[['', copy.home], ['products', copy.products], ['projects', copy.projects], ['services', copy.services], ['about', copy.about]].map(([path, label], index) => <a key={path} href={`/${locale}${path ? `/${path}` : ''}`}><span>{String(index + 1).padStart(2, '0')}</span>{label}</a>)}</nav>
    <PageHero locale={locale} title={page?.title || copy.heroTitle} eyebrow={page?.eyebrow || copy.heroEyebrow} body={page?.body || copy.heroBody} media={page?.hero} large variant="brand" aside={<aside className="hero-studies">{site.featured_products.slice(0, 3).map(product => <a key={product.reference} href={`/${locale}/products/${product.slug}`} aria-label={product.name}><CatalogueImage media={product.cover} alt={product.name} placeholder={copy.missingMedia} thumbnail /></a>)}<div className="hero-material-note"><p className="eyebrow">{copy.featuredProducts}</p><h2>{site.featured_products[0]?.name || copy.details}</h2><a href={`/${locale}/products`}>{copy.productsAction}<SiteIcon name="arrow" className="directional" /></a></div></aside>}><a className="button bronze" href={`/${locale}/contact`}>{copy.contactAction}<SiteIcon name="arrow" className="directional" /></a><a className="button outline" href={`/${locale}/products`}>{copy.productsAction}<SiteIcon name="stone" /></a></PageHero>
    <div className="material-values"><div className="site-width">{[{ title: copy.products, icon: 'stone' }, { title: copy.details, icon: 'craft' }, { title: copy.preparation, icon: 'ruler' }, { title: copy.projects, icon: 'building' }, { title: copy.footerFormats, icon: 'check' }, { title: copy.technical, icon: 'leaf' }].map(item => <div key={item.title}><SiteIcon name={item.icon} /><span>{item.title}</span></div>)}</div></div>
    <section className="section site-width"><SectionHeading title={copy.categories} eyebrow="LAAMACH MARBRE" body={page?.body} action={<a className="button outline" href={`/${locale}/products`}>{copy.allProducts}<SiteIcon name="arrow" className="directional" /></a>} /><div className="collection-grid">{collectionStories.length ? collectionStories.slice(0, 6).map((section, index) => <a className="collection-card" key={index} href={`/${locale}/products`}><CatalogueImage media={section.media} alt={section.title} placeholder={copy.missingMedia} /><span>{section.title}<SiteIcon name="arrow" className="directional" /></span><p>{section.body}</p></a>) : <>{collections.slice(0, 6).map(category => <a className="collection-card" key={category.id} href={`/${locale}/products?category=${category.slug}`}><CatalogueImage media={category.cover || null} alt={category.name} placeholder={copy.missingMedia} /><span>{category.name}</span></a>)}{Array.from({ length: Math.max(0, 6 - collections.length) }, (_, index) => <div className="collection-card" key={index}><div className="neutral-material"><SiteIcon name="building" /></div><span>{copy.pendingReference}</span><p>{copy.pendingEditorial}</p></div>)}</>}</div></section>
    <section className="section site-width brand-activities"><SectionHeading title={copy.activities} body={copy.activitiesIntro} /><EditorialCards locale={locale} variant="activities-grid" sections={activities.length ? activities : [{ group: 'activities', title: copy.products, body: null, icon: 'stone', link: 'products', media: null }, { group: 'activities', title: copy.projects, body: null, icon: 'building', link: 'projects', media: null }]} /></section>
    <section className="section site-width"><SectionHeading title={copy.featuredProducts} action={<a className="button bronze" href={`/${locale}/products`}>{copy.allProducts}<SiteIcon name="arrow" className="directional" /></a>} /><ProductCards products={site.featured_products.slice(0, 6)} locale={locale} compact slots={6} /></section>
    <section className="section site-width"><SectionHeading title={copy.featuredProjects} action={<a className="button bronze" href={`/${locale}/projects`}>{copy.allProjects}<SiteIcon name="arrow" className="directional" /></a>} /><ProjectCards projects={site.featured_projects.slice(0, 5)} locale={locale} slots={5} />{usages.data?.data.length ? <nav className="home-usage-links" aria-label={copy.usages}>{usages.data.data.map(category => <a key={category.id} href={`/${locale}/projects?category=${category.slug}`}>{category.name}</a>)}</nav> : null}</section>
    <PageSections locale={locale} page={page} group="trust" title={copy.trust} />
    <ContactBanner locale={locale} />
  </main>;
}
