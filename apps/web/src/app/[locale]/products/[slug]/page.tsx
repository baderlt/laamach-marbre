import { notFound } from 'next/navigation';
import { findLocale } from '@/lib/locales';
import { catalogueCopy, type Product } from '@/lib/catalogue';
import { catalogueRequest } from '@/lib/catalogue-server';
import { ProductOptions } from '@/components/product-options';
import { projectsCopy } from '@/lib/projects';
import { ProjectCards } from '@/components/project-cards';

export default async function ProductPage({ params }: { params: Promise<{ locale: string; slug: string }> }) {
  const { locale, slug } = await params;
  if (!findLocale(locale) || !/^[a-z0-9-]{1,120}$/.test(slug)) notFound();
  const copy = catalogueCopy(locale);
  const response = await catalogueRequest<{ data: Product }>(`products/${encodeURIComponent(slug)}`, locale);
  if (response.status === 404) notFound();
  if (!response.data) return <main><a href={`/${locale}/products`}>{copy.catalogue}</a><p role="status">{copy.unavailable}</p></main>;
  const product = response.data.data;
  return <main className="catalogue product-detail">
    <nav aria-label={copy.catalogue}><a href={`/${locale}/products`}>{copy.catalogue}</a><a href={`/${locale}/products?parent=${product.parent_category.slug}`}>{product.parent_category.name}</a><a href={`/${locale}/products?parent=${product.parent_category.slug}&category=${product.category.slug}`}>{product.category.name}</a></nav>
    <h1>{product.name}</h1><p><bdi>{product.reference}</bdi></p>
    <ProductOptions product={product} locale={locale} />
    {product.description && <p className="preserve-text">{product.description}</p>}
    {!!product.projects?.length && <section><h2>{projectsCopy(locale).related}</h2><ProjectCards projects={product.projects} locale={locale} /></section>}
    {product.colour && <p>{copy.colour}: {product.colour}</p>}{product.origin && <p>{copy.origin}: {product.origin}</p>}
    {!!product.technical_information?.length && <section><h2>{copy.technical}</h2><dl>{product.technical_information.map((item, index) => <div key={index}><dt>{item.label}</dt><dd>{item.value}</dd></div>)}</dl></section>}
  </main>;
}
