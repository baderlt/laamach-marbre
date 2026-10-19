import { getSite } from '@/lib/site-server';
import { serverEnvironment } from '@/lib/environment';
import { notFound } from 'next/navigation';
import { findLocale, localeConfiguration } from '@/lib/locales';
import { catalogueCopy, type Product, type ProductPage as ProductListing } from '@/lib/catalogue';
import { catalogueRequest } from '@/lib/catalogue-server';
import { ProductOptions } from '@/components/product-options';
import { projectsCopy } from '@/lib/projects';
import { ProjectCards } from '@/components/project-cards';
import { siteCopy } from '@/lib/site';
import { SectionHeading } from '@/components/page-design';
import { SiteIcon } from '@/components/site-icon';
import { PortfolioMotion } from '@/components/portfolio-motion';
import { ProductCards } from '@/components/product-cards';
import { ContactBanner } from '@/components/site-footer';
import type { Metadata } from 'next';

export async function generateMetadata({ params }: { params: Promise<{ locale: string; slug: string }> }): Promise<Metadata> {
  const { locale, slug } = await params; if (!findLocale(locale) || !/^[a-z0-9-]{1,120}$/.test(slug)) return {};
  const response = await catalogueRequest<{ data: Product }>(`products/${slug}`, locale);
  const product = response.data?.data;
  const image = product?.cover?.variants.find(variant => variant.format === 'webp');
  const title = product?.seo_title || product?.name;
  const description = product?.seo_description || undefined;
  return { title, description, alternates: { canonical: `/${locale}/products/${slug}`, languages: Object.fromEntries(localeConfiguration.locales.map(item => [item.code, `/${item.code}/products/${slug}`])) }, openGraph: { title, description, type: 'website', images: image ? [{ url: image.url, width: image.width, height: image.height, alt: product?.name }] : [] }, twitter: { card: 'summary_large_image', title, description, images: image ? [image.url] : [] } };
}

export default async function ProductPage({ params }: { params: Promise<{ locale: string; slug: string }> }) {
  const { locale, slug } = await params;
  if (!findLocale(locale) || !/^[a-z0-9-]{1,120}$/.test(slug)) notFound();
  const copy = catalogueCopy(locale);
  const response = await catalogueRequest<{ data: Product }>(`products/${encodeURIComponent(slug)}`, locale);
  if (response.status === 404) notFound();
  if (!response.data) return <main><a href={`/${locale}`}>{copy.home}</a><span aria-hidden="true">›</span><a href={`/${locale}/products`}>{copy.catalogue}</a><p role="status">{copy.unavailable}</p></main>;
  const product = response.data.data;
  const design = siteCopy(locale);
  const site = await getSite(locale);
  const canonical = `${serverEnvironment(process.env).site}/${locale}/products/${slug}`;
  const listing = await catalogueRequest<ProductListing>('products', locale, new URLSearchParams({ category: product.category.slug }));
  const relatedProducts = listing.data?.data.filter(item => item.slug !== product.slug).slice(0, 5) || [];
  const knownFacts = [...(product.colour ? [{ label: copy.colour, value: product.colour }] : []), ...(product.origin ? [{ label: copy.origin, value: product.origin }] : []), ...(product.technical_information || [])].filter((item, index, rows) => rows.findIndex(row => row.label.trim().normalize('NFKC').toLocaleLowerCase(locale) === item.label.trim().normalize('NFKC').toLocaleLowerCase(locale)) === index);
  const technical = knownFacts.length ? <section className="technical-section"><h2><SiteIcon name="ruler" />{design.technical}</h2><dl className="facts-table">{knownFacts.map((item, index) => <div key={index}><dt>{item.label}</dt><dd>{item.value}</dd></div>)}</dl></section> : null;
  const related = product.projects?.length ? <section className="product-related-projects"><SectionHeading title={projectsCopy(locale).related} /><ProjectCards projects={product.projects.slice(0, 1)} locale={locale} /><a className="text-link" href={`/${locale}/projects`}>{design.allProjects}<SiteIcon name="arrow" className="directional" /></a></section> : null;
  return <main id="main" className="catalogue product-detail public-page">
    <div className="breadcrumb-band"><nav className="site-width breadcrumbs" aria-label={copy.catalogue}><a href={`/${locale}`}>{copy.home}</a><span aria-hidden="true">›</span><a href={`/${locale}/products`}>{copy.catalogue}</a><span>›</span><a href={`/${locale}/products?parent=${product.parent_category.slug}`}>{product.parent_category.name}</a><span>›</span><a href={`/${locale}/products?parent=${product.parent_category.slug}&category=${product.category.slug}`}>{product.category.name}</a><span>›</span><span>{product.name}</span></nav></div>
    <div className="site-width section product-content">
    <ProductOptions settings={site.settings} canonical={canonical} product={product} locale={locale} technical={technical} related={related} />
    <section className="section related-products"><SectionHeading title={design.relatedProducts} /><ProductCards products={relatedProducts.slice(0, 4)} locale={locale} compact /></section>
    <PortfolioMotion identity={slug} /></div><ContactBanner locale={locale} pageKey="products" />
  </main>;
}
