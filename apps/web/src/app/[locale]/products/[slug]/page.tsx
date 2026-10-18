import { notFound } from 'next/navigation';
import { findLocale } from '@/lib/locales';
import { catalogueCopy, type Product, type ProductPage as ProductListing } from '@/lib/catalogue';
import { catalogueRequest } from '@/lib/catalogue-server';
import { ProductOptions } from '@/components/product-options';
import { projectsCopy } from '@/lib/projects';
import { ProjectCards } from '@/components/project-cards';
import { siteCopy } from '@/lib/site';
import { PageHero, SectionHeading } from '@/components/page-design';
import { ProductCards } from '@/components/product-cards';
import { provisionalPreviewEnabled } from '@/lib/provisional-preview';
import { ContactBanner } from '@/components/site-footer';
import type { Metadata } from 'next';

export async function generateMetadata({ params }: { params: Promise<{ locale: string; slug: string }> }): Promise<Metadata> {
  const { locale, slug } = await params; if (!findLocale(locale) || !/^[a-z0-9-]{1,120}$/.test(slug)) return {};
  const response = await catalogueRequest<{ data: Product }>(`products/${slug}`, locale);
  return { title: response.data?.data.seo_title || response.data?.data.name, description: response.data?.data.seo_description || undefined, alternates: { canonical: `/${locale}/products/${slug}` } };
}

export default async function ProductPage({ params }: { params: Promise<{ locale: string; slug: string }> }) {
  const { locale, slug } = await params;
  if (!findLocale(locale) || !/^[a-z0-9-]{1,120}$/.test(slug)) notFound();
  const copy = catalogueCopy(locale);
  const response = await catalogueRequest<{ data: Product }>(`products/${encodeURIComponent(slug)}`, locale);
  if (response.status === 404) notFound();
  if (!response.data) return <main><a href={`/${locale}/products`}>{copy.catalogue}</a><p role="status">{copy.unavailable}</p></main>;
  const product = response.data.data;
  const design = siteCopy(locale);
  const listing = await catalogueRequest<ProductListing>('products', locale, new URLSearchParams({ category: product.category.slug }));
  const relatedProducts = listing.data?.data.filter(item => item.slug !== product.slug).slice(0, 5) || [];
  const knownFacts = [...(product.colour ? [{ label: copy.colour, value: product.colour }] : []), ...(product.origin ? [{ label: copy.origin, value: product.origin }] : []), ...(product.technical_information || [])];
  const technical = <section className="technical-section"><h2>{design.technical}</h2><dl className="facts-table">{(knownFacts.length ? knownFacts : [copy.colour, copy.origin, copy.sizes, copy.finish, design.details].map(label => ({ label, value: design.technicalPending }))).map((item, index) => <div key={index}><dt>{item.label}</dt><dd>{item.value}</dd></div>)}</dl></section>;
  const related = <section className="product-related-projects"><SectionHeading title={product.projects?.length ? projectsCopy(locale).related : design.projectsAction} /><ProjectCards projects={product.projects || []} locale={locale} slots={4} /><a className="button outline" href={`/${locale}/projects`}>{design.allProjects}</a></section>;
  return <main id="main" className="catalogue product-detail public-page">
    <PageHero locale={locale} title={product.name} eyebrow={`${product.parent_category.name} / ${product.category.name}`} media={product.cover} variant="material" headingTag="p" />
    <div className="breadcrumb-band"><nav className="site-width breadcrumbs" aria-label={copy.catalogue}><a href={`/${locale}/products`}>{copy.catalogue}</a><span>›</span><a href={`/${locale}/products?parent=${product.parent_category.slug}`}>{product.parent_category.name}</a><span>›</span><a href={`/${locale}/products?parent=${product.parent_category.slug}&category=${product.category.slug}`}>{product.category.name}</a><span>›</span><span>{product.name}</span></nav></div>
    <div className="site-width section product-content">
    <ProductOptions product={product} locale={locale} technical={technical} related={related} pendingSpecification={provisionalPreviewEnabled(process.env) && product.category.slug === 'selection-photo-provisoire'} />
    <section className="section related-products"><SectionHeading title={design.relatedProducts} /><ProductCards products={relatedProducts} locale={locale} compact slots={5} /></section>
    </div><ContactBanner locale={locale} pageKey="products" />
  </main>;
}
