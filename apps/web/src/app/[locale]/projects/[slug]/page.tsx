import { notFound } from 'next/navigation';
import { findLocale } from '@/lib/locales';
import { projectsCopy, type Project } from '@/lib/projects';
import { catalogueRequest } from '@/lib/catalogue-server';
import { ProjectGallery } from '@/components/project-gallery';
import { ProductCards } from '@/components/product-cards';
import { ProjectCards } from '@/components/project-cards';
import { PageHero, SectionHeading } from '@/components/page-design';
import { RequestDraft } from '@/components/request-draft';
import { ContactBanner } from '@/components/site-footer';
import type { Metadata } from 'next';
import { siteCopy } from '@/lib/site';

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
  const design = siteCopy(locale);
  return <main id="main" className="catalogue project-detail public-page">
    <PageHero locale={locale} title={project.title} eyebrow={project.reference} body={project.summary} media={project.cover} large aside={<dl className="project-metadata"><div><dt>{copy.location}</dt><dd>{project.location || design.notProvided}</dd></div><div><dt>{copy.year}</dt><dd>{project.year || design.notProvided}</dd></div><div><dt>{copy.area}</dt><dd>{project.area_display || (project.area ? `${project.area} ${project.area_unit}` : design.notProvided)}</dd></div></dl>}><div className="project-tags">{project.categories.map(category => <a key={category.id} href={`/${locale}/projects?category=${category.slug}`}>{category.name}</a>)}</div></PageHero>
    <div className="site-width"><div className="project-request"><RequestDraft locale={locale} kind="similar" identity={{ title: project.title, reference: project.reference }} /></div>
    {project.description && <p className="preserve-text project-intro">{project.description}</p>}
    <section className="section"><SectionHeading title={copy.gallery} /><ProjectGallery project={project} locale={locale} /></section>
    <section className="section" data-materials-used><SectionHeading title={copy.materials} body={!project.products.length ? design.materialsPending : undefined} /><ProductCards products={project.products.slice(0, 5)} locale={locale} compact slots={5} /></section>
    <section className="section related-projects"><SectionHeading title={copy.projects} /><ProjectCards projects={project.related_projects || []} locale={locale} slots={4} /></section>
    </div><ContactBanner locale={locale} pageKey="projects" />
  </main>;
}
