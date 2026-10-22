import { notFound } from 'next/navigation';
import { findLocale } from '@/lib/locales';
import { getSite, pageMetadata } from '@/lib/site-server';
import { editorialHref, siteCopy } from '@/lib/site';
import { projectCategoryIcon } from '@/lib/projects';
import { PageHero, SectionHeading, PageSections } from '@/components/page-design';
import { ProductCards } from '@/components/product-cards';
import { CatalogueImage } from '@/components/catalogue-image';
import { ContactBanner } from '@/components/site-footer';
import { SiteIcon } from '@/components/site-icon';
import { HomeMotion } from '@/components/home-motion';
import { HomeProductCarousel } from '@/components/home-product-carousel';

export async function generateMetadata({ params }: { params: Promise<{ locale: string }> }) { const { locale } = await params; if (!findLocale(locale)) return {}; return pageMetadata(locale, 'home', ''); }

export default async function FoundationPage({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  if (!findLocale(locale)) notFound();
  const copy = siteCopy(locale);
  const site = await getSite(locale);
  const page = site.pages.home;
  const homeProducts = site.home_products ?? site.featured_products;
  const activityOrder = (link?: string | null) => link?.split('?')[0] === 'products' ? 1 : 0;
  const activities = (page?.sections.filter(section => section.group === 'activities') || []).sort((a, b) => activityOrder(a.link) - activityOrder(b.link));
  const paths = activities.length ? activities : [{ title: locale === 'ar' ? 'إنجاز المشاريع' : 'Réalisation de projets', icon: 'building', link: 'projects', media: site.featured_projects[0]?.cover || null }, { title: copy.products, icon: 'stone', link: 'products', media: site.featured_products[0]?.cover || null }];
  const collectionStories = page?.sections.filter(section => section.group === 'services') || [];
  const collections = site.navigation?.products.flatMap(category => category.children?.length ? category.children : [category]) || [];
  return <main id="main" className="public-page homepage" data-backend-status={site.available ? 'ok' : 'unavailable'}>
    <HomeMotion />
    <nav className="brand-rail" aria-label={locale === 'ar' ? 'أقسام الموقع' : 'Rubriques du site'}>{[['', copy.home], ['products', copy.products], ['projects', copy.projects], ['services', copy.services], ['about', copy.about]].map(([path, label], index) => <a key={path} href={`/${locale}${path ? `/${path}` : ''}`}><span>{String(index + 1).padStart(2, '0')}</span>{label}</a>)}</nav>
    <PageHero locale={locale} title={page?.title || copy.heroTitle} eyebrow={page?.eyebrow || copy.heroEyebrow} body={page?.body || copy.heroBody} media={page?.hero} large variant="brand" footer={<nav className="site-width hero-paths" aria-label={copy.activities}>{paths.map((section, index) => <a key={index} className="hero-path" href={editorialHref(locale, section.link) || `/${locale}/projects`}>{section.media ? <CatalogueImage media={section.media} alt="" placeholder={copy.missingMedia} thumbnail sizes="104px" /> : <SiteIcon name={section.icon} />}<span><strong>{section.title}</strong><small>{section.link?.split('?')[0] === 'projects' ? locale === 'ar' ? 'شاهد مشاريعنا' : 'Voir nos projets' : section.link === 'contact' ? copy.contactAction : copy.discover}</small></span><SiteIcon name="arrow" className="directional" /></a>)}</nav>}><a className="button bronze" href={`/${locale}/contact`}>{copy.contactAction}<SiteIcon name="arrow" className="directional" /></a><a className="button outline" href={`/${locale}/products`}>{copy.productsAction}<SiteIcon name="stone" /></a></PageHero>
    <section className="section site-width"><SectionHeading title={copy.categories} body={page?.body} action={<a className="button outline" href={`/${locale}/products`}>{copy.allProducts}<SiteIcon name="arrow" className="directional" /></a>} /><div className="collection-grid">{collectionStories.length ? collectionStories.slice(0, 6).map((section, index) => <a className="collection-card" key={index} href={editorialHref(locale, section.link) || `/${locale}/products`}><CatalogueImage media={section.media} alt={section.title} placeholder={copy.missingMedia} /><span>{section.title}<SiteIcon name="arrow" className="directional" /></span></a>) : <>{collections.slice(0, 6).map(category => <a className="collection-card" key={category.id} href={`/${locale}/products?category=${category.slug}`}><CatalogueImage media={category.cover || null} alt={category.name} placeholder={copy.missingMedia} /><span>{category.name}</span></a>)}{Array.from({ length: Math.max(0, 6 - collections.length) }, (_, index) => <div className="collection-card" key={index}><div className="neutral-material"><SiteIcon name="building" /></div><span>{copy.pendingReference}</span></div>)}</>}</div></section>
    <section className="section site-width home-featured-products"><SectionHeading title={copy.featuredProducts} action={<a className="home-featured-link" href={`/${locale}/products`}>{copy.allProducts}<SiteIcon name="arrow" className="directional" /></a>} /><HomeProductCarousel locale={locale} count={homeProducts.length} title={copy.featuredProducts}><ProductCards products={homeProducts} locale={locale} compact slots={6} minimal imageSizes="(max-width: 767px) 78vw, (max-width: 1023px) 46vw, 400px" /></HomeProductCarousel></section>
    <section className="section site-width home-projects"><SectionHeading title={copy.featuredProjects} body={locale === 'ar' ? 'اختر الأجواء التي تلهمك، واكتشف الحجر في أعمالنا.' : 'Choisissez un univers et découvrez la pierre à travers nos réalisations.'} />
      {site.navigation?.projects.length ? <nav className="home-usage-links" aria-label={copy.usages}>{site.navigation.projects.map((category, index) => <a key={category.id} href={`/${locale}/projects?category=${category.slug}`}>
        <CatalogueImage media={category.cover || site.featured_projects.find(project => project.categories.some(item => item.slug === category.slug))?.cover || null} alt={category.name} placeholder={copy.missingMedia} thumbnail />
        <span className="usage-card-top"><SiteIcon name={projectCategoryIcon(category.slug)} /><small>{String(index + 1).padStart(2, '0')}</small></span><span className="usage-card-title">{category.name}<SiteIcon name="arrow" className="directional" /></span>
      </a>)}</nav> : null}
      <div className="home-projects-footer"><div><span className="eyebrow">{locale === 'ar' ? 'حجر مغربي، رؤى متعددة' : 'La pierre marocaine, au fil des projets'}</span><p>{locale === 'ar' ? 'شاهد جميع مجموعات الأعمال، واحتفظ بما يلهم مشروعك.' : 'Parcourez toutes les collections et gardez les idées qui vous inspirent.'}</p></div><a className="button bronze" href={`/${locale}/projects`}>{copy.allProjects}<SiteIcon name="arrow" className="directional" /></a></div>
    </section>
    <PageSections locale={locale} page={page} group="trust" title={copy.trust} />
    <ContactBanner locale={locale} />
  </main>;
}
