import { notFound } from 'next/navigation';
import { findLocale, localeConfiguration } from '@/lib/locales';
import { catalogueCopy, catalogueFilters, type CategoryPage, type ProductPage } from '@/lib/catalogue';
import { catalogueRequest } from '@/lib/catalogue-server';
import { CatalogueImage } from '@/components/catalogue-image';

export default async function CataloguePage({ params, searchParams }: { params: Promise<{ locale: string }>; searchParams: Promise<Record<string, string | string[] | undefined>> }) {
  const { locale } = await params;
  if (!findLocale(locale)) notFound();
  const copy = catalogueCopy(locale);
  const filters = catalogueFilters(await searchParams);
  const [categories, products] = await Promise.all([catalogueRequest<CategoryPage>('product-categories', locale), catalogueRequest<ProductPage>('products', locale, filters)]);
  const parent = categories.data?.data.find(item => item.slug === filters.get('parent'));
  const children = parent?.children || categories.data?.data.flatMap(item => item.children || []) || [];
  const pageLink = (page: number) => { const query = new URLSearchParams(filters); query.set('page', String(page)); return `/${locale}/products?${query}`; };
  return <main className="catalogue">
    <nav aria-label={copy.catalogue}><a href={`/${locale}`}>{copy.home}</a>{localeConfiguration.locales.map(item => <a key={item.code} href={`/${item.code}/products?${filters}`} lang={item.code} dir={item.direction} aria-current={item.code === locale ? 'page' : undefined}>{item.name}</a>)}</nav>
    <h1>{copy.catalogue}</h1>
    {!categories.data || !products.data ? <p role="status">{copy.unavailable}</p> : <>
      <form className="catalogue-filters" method="get">
        <label>{copy.parent}<select name="parent" defaultValue={filters.get('parent') || ''}><option value="">{copy.all}</option>{categories.data.data.map(item => <option key={item.id} value={item.slug}>{item.name}</option>)}</select></label>
        <label>{copy.child}<select name="category" defaultValue={filters.get('category') || ''}><option value="">{copy.all}</option>{children.map(item => <option key={item.id} value={item.slug}>{item.name}</option>)}</select></label>
        <label>{copy.finish}<select name="finish" defaultValue={filters.get('finish') || ''}><option value="">{copy.all}</option>{categories.data.finishes.map(item => <option key={item.id} value={item.slug}>{item.name}</option>)}</select></label>
        <button type="submit">{copy.apply}</button>
      </form>
      {products.data.data.length === 0 ? <p>{copy.empty}</p> : <div className="product-grid">{products.data.data.map(product => <article key={product.reference}><a href={`/${locale}/products/${product.slug}`}><CatalogueImage media={product.cover} placeholder={copy.missingImage} /><h2>{product.name}</h2></a><p>{product.parent_category.name} / {product.category.name}</p><bdi>{product.reference}</bdi></article>)}</div>}
      <nav aria-label="Pagination">{products.data.meta.current_page > 1 && <a href={pageLink(products.data.meta.current_page - 1)}>{copy.previous}</a>}<span>{products.data.meta.current_page} / {products.data.meta.last_page}</span>{products.data.meta.current_page < products.data.meta.last_page && <a href={pageLink(products.data.meta.current_page + 1)}>{copy.next}</a>}</nav>
    </>}
  </main>;
}
