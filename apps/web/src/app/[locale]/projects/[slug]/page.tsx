import { notFound } from 'next/navigation';
import { findLocale } from '@/lib/locales';
import { catalogueCopy } from '@/lib/catalogue';
import { projectsCopy, type Project } from '@/lib/projects';
import { catalogueRequest } from '@/lib/catalogue-server';
import { ProjectGallery } from '@/components/project-gallery';
import { CatalogueImage } from '@/components/catalogue-image';

export default async function ProjectPage({ params }: { params: Promise<{ locale: string; slug: string }> }) {
  const { locale, slug } = await params;
  if (!findLocale(locale) || !/^[a-z0-9-]{1,120}$/.test(slug)) notFound();
  const copy = projectsCopy(locale);
  const common = catalogueCopy(locale);
  const response = await catalogueRequest<{ data: Project }>(`projects/${encodeURIComponent(slug)}`, locale);
  if (response.status === 404) notFound();
  if (!response.data) return <main><a href={`/${locale}/projects`}>{copy.projects}</a><p role="status">{copy.unavailable}</p></main>;
  const project = response.data.data;
  return <main className="catalogue project-detail">
    <nav aria-label={copy.projects}><a href={`/${locale}/projects`}>{copy.projects}</a>{project.categories.map(category => <a key={category.id} href={`/${locale}/projects?category=${category.slug}`}>{category.name}</a>)}</nav>
    <h1>{project.title}</h1><p><bdi>{project.reference}</bdi></p>
    <ProjectGallery project={project} locale={locale} />
    {project.summary && <p className="preserve-text">{project.summary}</p>}{project.description && <p className="preserve-text">{project.description}</p>}
    <dl>{project.location && <div><dt>{copy.location}</dt><dd>{project.location}</dd></div>}{project.year && <div><dt>{copy.year}</dt><dd>{project.year}</dd></div>}{(project.area_display || project.area) && <div><dt>{copy.area}</dt><dd>{project.area_display || `${project.area} ${project.area_unit}`}</dd></div>}</dl>
    {!!project.products.length && <section><h2>{copy.materials}</h2><div className="product-grid">{project.products.map(product => <article key={product.reference}><a href={`/${locale}/products/${product.slug}`}><CatalogueImage media={product.cover} placeholder={common.missingImage} /><h3>{product.name}</h3></a><bdi>{product.reference}</bdi></article>)}</div></section>}
  </main>;
}
