import type { ProjectCard } from '@/lib/projects';
import { catalogueCopy } from '@/lib/catalogue';
import { CatalogueImage } from './catalogue-image';
import { SiteIcon } from './site-icon';

export function ProjectCards({ projects, locale }: { projects: ProjectCard[]; locale: string }) {
  return <div className="project-grid" data-project-cards>{projects.map(project => <article className="project-card" key={project.reference}><a className="project-card-media" href={`/${locale}/projects/${project.slug}`}><CatalogueImage media={project.cover} placeholder={catalogueCopy(locale).missingImage} /></a><div className="project-card-copy"><div><h2><a href={`/${locale}/projects/${project.slug}`}>{project.title}</a></h2><p>{project.categories.map(category => category.name).join(' / ')}</p></div><SiteIcon name="building" /></div></article>)}</div>;
}
