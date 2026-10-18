import type { ProjectCard } from '@/lib/projects';
import { catalogueCopy } from '@/lib/catalogue';
import { CatalogueImage } from './catalogue-image';
import { SiteIcon } from './site-icon';
import { siteCopy } from '@/lib/site';
import { provisionalPreviewEnabled, provisionalImageUrl, provisionalImageLabel } from '@/lib/provisional-preview';

export function ProjectCards({ projects, locale, slots = 0 }: { projects: ProjectCard[]; locale: string; slots?: number }) {
  const provisional = provisionalPreviewEnabled(process.env);
  const copy = siteCopy(locale);
  return <div className="project-grid" data-project-cards>{projects.map((project, index) => <article className={`project-card crop-${index % 3}`} key={project.reference}><a className="project-card-media" href={`/${locale}/projects/${project.slug}`}>{provisional && project.slug.startsWith('inspiration-') ? <><img src={provisionalImageUrl} alt={provisionalImageLabel(locale)} width={1920} height={1080} loading="lazy" /><span className="provisional-image-label">{provisionalImageLabel(locale, true)}</span></> : <CatalogueImage media={project.cover} alt={project.title} placeholder={catalogueCopy(locale).missingImage} />}</a><div className="project-card-copy"><div><h2><a href={`/${locale}/projects/${project.slug}`}>{project.title}</a></h2><p>{project.categories.map(category => category.name).join(' / ')}</p></div><SiteIcon name="building" /></div></article>)}{Array.from({ length: Math.max(0, slots - projects.length) }, (_, index) => <div key={`pending-${index}`} className="project-card pending-card" data-visual-placeholder><div className={`project-card-media architectural-study study-${index % 3}`}><i /><i /><i /><span>{copy.missingMedia}</span></div><div className="project-card-copy"><div><h3>{copy.pendingProject}</h3><p>{copy.pendingEditorial}</p></div><SiteIcon name="building" /></div></div>)}</div>;
}
