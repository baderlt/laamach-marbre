import type { ProjectCard } from '@/lib/projects';
import { catalogueCopy } from '@/lib/catalogue';
import { CatalogueImage } from './catalogue-image';

export function ProjectCards({ projects, locale }: { projects: ProjectCard[]; locale: string }) {
  return <div className="product-grid" data-project-cards>{projects.map(project => <article key={project.reference}><a href={`/${locale}/projects/${project.slug}`}><CatalogueImage media={project.cover} placeholder={catalogueCopy(locale).missingImage} /><h2>{project.title}</h2></a><p>{project.categories.map(category => category.name).join(' / ')}</p><bdi>{project.reference}</bdi></article>)}</div>;
}
