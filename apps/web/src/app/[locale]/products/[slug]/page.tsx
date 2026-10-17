import { notFound } from 'next/navigation';
import { findLocale } from '@/lib/locales';
import { catalogueCopy, type Product } from '@/lib/catalogue';
import { catalogueRequest } from '@/lib/catalogue-server';
import { ProductOptions } from '@/components/product-options';
import { projectsCopy } from '@/lib/projects';
import { ProjectCards } from '@/components/project-cards';
import { siteCopy } from '@/lib/site';
import { SectionHeading } from '@/components/page-design';
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
  return <main id="main" className="catalogue product-detail public-page">
    <div className="breadcrumb-band"><nav className="site-width breadcrumbs" aria-label={copy.catalogue}><a href={`/${locale}/products`}>{copy.catalogue}</a><span>›</span><a href={`/${locale}/products?parent=${product.parent_category.slug}`}>{product.parent_category.name}</a><span>›</span><a href={`/${locale}/products?parent=${product.parent_category.slug}&category=${product.category.slug}`}>{product.category.name}</a><span>›</span><span>{product.name}</span></nav></div>
    <div className="site-width section product-content">
    <ProductOptions product={product} locale={locale} />
    {(product.colour || product.origin || product.technical_information?.length) && <section className="technical-section"><SectionHeading title={siteCopy(locale).technical} /><dl className="facts-table">{product.colour && <div><dt>{copy.colour}</dt><dd>{product.colour}</dd></div>}{product.origin && <div><dt>{copy.origin}</dt><dd>{product.origin}</dd></div>}{product.technical_information?.map((item, index) => <div key={index}><dt>{item.label}</dt><dd>{item.value}</dd></div>)}</dl></section>}
    {!!product.projects?.length && <section className="section"><SectionHeading title={projectsCopy(locale).related} /><ProjectCards projects={product.projects} locale={locale} /></section>}
    </div><ContactBanner locale={locale} />
  </main>;
}
