import { notFound } from 'next/navigation';
import { findLocale } from '@/lib/locales';
import { projectsCopy, type Project } from '@/lib/projects';
import { catalogueRequest } from '@/lib/catalogue-server';
import { ProjectGallery } from '@/components/project-gallery';
import { ProductCards } from '@/components/product-cards';
import { ProjectCards } from '@/components/project-cards';
import { PageHero, SectionHeading } from '@/components/page-design';
import { ContactBanner } from '@/components/site-footer';
import type { Metadata } from 'next';

export async function generateMetadata({ params }: { params: Promise<{ locale: string; slug: string }> }): Promise<Metadata> {
  const { locale, slug } = await params; if (!findLocale(locale) || !/^[a-z0-9-]{1,120}$/.test(slug)) return {};
  const response = await catalogueRequest<{ data: Project }>(`projects/${slug}`, locale);
  return { title: response.data?.data.seo_title || response.data?.data.title, description: response.data?.data.seo_description || undefined, alternates: { canonical: `/${locale}/projects/${slug}` } };
}

export default async function ProjectPage({ params }: { params: Promise<{ locale: string; slug: string }> }) {
  const { locale, slug } = await params;
  if (!findLocale(locale) || !/^[a-z0-9-]{1,120}$/.test(slug)) notFound();
  const copy = projectsCopy(locale);
  const response = await catalogueRequest<{ data: Project }>(`projects/${encodeURIComponent(slug)}`, locale);
  if (response.status === 404) notFound();
  if (!response.data) return <main><a href={`/${locale}/projects`}>{copy.projects}</a><p role="status">{copy.unavailable}</p></main>;
  const project = response.data.data;
  return <main id="main" className="catalogue project-detail public-page">
    <PageHero locale={locale} title={project.title} eyebrow={project.reference} body={project.summary} media={project.cover} large><div className="project-tags">{project.categories.map(category => <a key={category.id} href={`/${locale}/projects?category=${category.slug}`}>{category.name}</a>)}</div></PageHero>
    <div className="site-width"><dl className="project-metadata">{project.location && <div><dt>{copy.location}</dt><dd>{project.location}</dd></div>}{project.year && <div><dt>{copy.year}</dt><dd>{project.year}</dd></div>}{(project.area_display || project.area) && <div><dt>{copy.area}</dt><dd>{project.area_display || `${project.area} ${project.area_unit}`}</dd></div>}</dl>
    {project.description && <p className="preserve-text project-intro">{project.description}</p>}
    <section className="section"><SectionHeading title={copy.gallery} /><ProjectGallery project={project} locale={locale} /></section>
    {!!project.products.length && <section className="section" data-materials-used><SectionHeading title={copy.materials} /><ProductCards products={project.products} locale={locale} compact /></section>}
    {!!project.related_projects?.length && <section className="section"><SectionHeading title={copy.projects} /><ProjectCards projects={project.related_projects} locale={locale} /></section>}
    </div><ContactBanner locale={locale} />
  </main>;
}
