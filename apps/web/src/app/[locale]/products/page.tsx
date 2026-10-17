import { notFound } from 'next/navigation';
import { findLocale } from '@/lib/locales';
import { catalogueCopy, catalogueFilters, type CategoryPage, type ProductPage } from '@/lib/catalogue';
import { catalogueRequest } from '@/lib/catalogue-server';
import { siteCopy } from '@/lib/site';
import { getSite, pageMetadata } from '@/lib/site-server';
import { PageHero } from '@/components/page-design';
import { ProductCards } from '@/components/product-cards';
import { CatalogueFilters } from '@/components/catalogue-filters';
import { ContactBanner } from '@/components/site-footer';
import { SiteIcon } from '@/components/site-icon';

export async function generateMetadata({ params }: { params: Promise<{ locale: string }> }) { const { locale } = await params; if (!findLocale(locale)) return {}; return pageMetadata(locale, 'products', '/products'); }

export default async function CataloguePage({ params, searchParams }: { params: Promise<{ locale: string }>; searchParams: Promise<Record<string, string | string[] | undefined>> }) {
  const { locale } = await params;
  if (!findLocale(locale)) notFound();
  const copy = catalogueCopy(locale);
  const filters = catalogueFilters(await searchParams);
  const [categories, products] = await Promise.all([catalogueRequest<CategoryPage>('product-categories', locale), catalogueRequest<ProductPage>('products', locale, filters)]);
  const design = siteCopy(locale);
  const page = (await getSite(locale)).pages.products;
  const pageLink = (page: number) => { const query = new URLSearchParams(filters); query.set('page', String(page)); return `/${locale}/products?${query}`; };
  return <main id="main" className="catalogue public-page">
    <PageHero locale={locale} title={page?.title || design.products} eyebrow={page?.eyebrow || 'LAAMACH MARBRE'} body={page?.body || design.collectionIntro} media={page?.hero} />
    {!categories.data || !products.data ? <div className="site-width section"><p role="status">{copy.unavailable}</p></div> : <div className="section site-width catalogue-layout">
      <CatalogueFilters locale={locale} data={categories.data} query={filters.toString()} />
      <div className="catalogue-results"><div className="results-bar"><form className="catalogue-search" method="get">{[...filters].filter(([key]) => key !== 'q' && key !== 'page').map(([key, value]) => <input type="hidden" key={key} name={key} value={value} />)}<label className="sr-only" htmlFor="search">{design.search}</label><input id="search" name="q" type="search" maxLength={120} defaultValue={filters.get('q') || ''} placeholder={design.search} /><button className="icon-button" type="submit" aria-label={design.search}><SiteIcon name="search" /></button></form><p className="results-count">{products.data.meta.total} {design.results}</p></div>
      <nav className="category-rail" aria-label={design.categories}><a className={!filters.get('parent') ? 'active' : ''} href={`/${locale}/products`}>{copy.all}</a>{categories.data.data.map(category => <a key={category.id} className={filters.get('parent') === category.slug ? 'active' : ''} href={`/${locale}/products?parent=${category.slug}`}>{category.name}</a>)}</nav>
      {products.data.data.length === 0 ? <div className="editorial-empty"><p>{copy.empty}</p></div> : <ProductCards products={products.data.data} locale={locale} />}
      <nav className="pagination" aria-label="Pagination">{products.data.meta.current_page > 1 && <a className="button outline" href={pageLink(products.data.meta.current_page - 1)}>{copy.previous}</a>}<span>{products.data.meta.current_page} / {products.data.meta.last_page}</span>{products.data.meta.current_page < products.data.meta.last_page && <a className="button outline" href={pageLink(products.data.meta.current_page + 1)}>{copy.next}</a>}</nav>
      </div>
    </div>}
    <ContactBanner locale={locale} />
  </main>;
}
