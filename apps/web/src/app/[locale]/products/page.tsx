import { notFound } from 'next/navigation';
import { findLocale } from '@/lib/locales';
import { catalogueCopy, catalogueFilters, type CategoryPage, type ProductPage } from '@/lib/catalogue';
import { catalogueRequest } from '@/lib/catalogue-server';
import { siteCopy } from '@/lib/site';
import { getSite, pageMetadata } from '@/lib/site-server';
import { editorialMedia } from '@/lib/editorial-media';
import { PageHero } from '@/components/page-design';
import { CatalogueFeed } from '@/components/catalogue-feed';
import { CatalogueCategories, CatalogueDisplay, CatalogueFilters, CatalogueSort } from '@/components/catalogue-filters';
import { PortfolioMotion } from '@/components/portfolio-motion';
import { ContactBanner } from '@/components/site-footer';
import { SiteIcon } from '@/components/site-icon';

export async function generateMetadata({ params }: { params: Promise<{ locale: string }> }) { const { locale } = await params; if (!findLocale(locale)) return {}; return pageMetadata(locale, 'products', '/products'); }

export default async function CataloguePage({ params, searchParams }: { params: Promise<{ locale: string }>; searchParams: Promise<Record<string, string | string[] | undefined>> }) {
  const { locale } = await params;
  if (!findLocale(locale)) notFound();
  const copy = catalogueCopy(locale);
  const filters = catalogueFilters(await searchParams);
  const grouped = [...filters.keys()].every(key => key === 'page');
  const [categories, products] = await Promise.all([catalogueRequest<CategoryPage>('product-categories', locale), catalogueRequest<ProductPage>(grouped ? 'product-groups' : 'products', locale, filters)]);
  const design = siteCopy(locale);
  const page = (await getSite(locale)).pages.products;
  const ar = locale === 'ar';
  return <main id="main" className="catalogue public-page products-index">
    <noscript><style>{'.products-index .desktop-filter-panel{display:block;position:static;width:100%}.products-index .catalogue-view-switch{display:none}'}</style></noscript>
    <PortfolioMotion identity={filters.toString()} />
    <PageHero locale={locale} title={page?.title || (ar ? 'الحجر الطبيعي والرخام' : 'Pierres naturelles & marbres')} eyebrow={page?.eyebrow || (ar ? 'مجموعة مختارة بعناية' : 'Notre sélection')} body={page?.body || (ar ? 'حجر مغربي بطابعه الأصيل، ولمسات تمنح مشروعك شخصيته. اكتشف المواد، وقارن الملامس، واختر معنا ما يناسب رؤيتك.' : 'Le caractère de la pierre marocaine, pour donner vie à vos espaces. Explorez les nuances, découvrez les textures et trouvons ensemble la matière de votre projet.')} media={editorialMedia('services', locale)} variant="photo-banner catalogue-hero">
      <ul className="catalogue-hero-benefits">{(ar ? [['stone', 'أحجار طبيعية ورخام'], ['craft', 'أكثر من 10 سنوات من الخبرة'], ['check', 'مواكبة مشروعك']] : [['stone', 'Pierres naturelles & marbres'], ['craft', 'Plus de 10 ans d’expérience'], ['check', 'Conseils pour votre projet']]).map(([icon, text]) => <li key={icon}><SiteIcon name={icon} /><span>{text}</span></li>)}</ul>
    </PageHero>
    {!categories.data || !products.data ? <div className="site-width section"><p role="status">{copy.unavailable}</p></div> : <div className="section site-width catalogue-browse">
      <CatalogueCategories locale={locale} data={categories.data} query={filters.toString()} />
      <div className="catalogue-layout">
      <CatalogueFilters key={filters.toString()} locale={locale} data={categories.data} query={filters.toString()} />
      <CatalogueDisplay locale={locale} toolbar={<><form className="catalogue-search" method="get">{[...filters].filter(([key]) => key !== 'q' && key !== 'page').map(([key, value]) => <input type="hidden" key={key} name={key} value={value} />)}<label className="sr-only" htmlFor="search">{design.search}</label><input id="search" name="q" type="search" maxLength={120} defaultValue={filters.get('q') || ''} placeholder={ar ? 'ابحث عن حجر أو رخام…' : 'Rechercher une pierre, un marbre…'} /><button className="icon-button" type="submit" aria-label={design.search}><SiteIcon name="search" /></button></form><p className="results-count"><strong>{products.data.meta.total}</strong> {design.results}</p><CatalogueSort locale={locale} query={filters.toString()} /></>}>
      {products.data.data.length === 0 ? <div className="editorial-empty"><SiteIcon name="search" /><h2>{copy.empty}</h2><a className="button outline" href={`/${locale}/products`}>{design.clear}<SiteIcon name="arrow" className="directional" /></a></div> : <CatalogueFeed key={`${locale}:${filters}`} initial={products.data} locale={locale} query={filters.toString()} />}
      </CatalogueDisplay>
      </div>
    </div>}
    <ContactBanner locale={locale} pageKey="products" />
  </main>;
}
