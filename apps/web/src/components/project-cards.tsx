import type { ProjectCard } from '@/lib/projects';
import { catalogueCopy } from '@/lib/catalogue';
import { CatalogueImage } from './catalogue-image';
import { SiteIcon } from './site-icon';
import { siteCopy } from '@/lib/site';

export function ProjectCards({ projects, locale, slots = 0 }: { projects: ProjectCard[]; locale: string; slots?: number }) {
  const copy = siteCopy(locale);
  return <div className="project-grid" data-project-cards>{projects.map((project, index) => {
    const categories = project.categories.map(category => category.name).filter(name => name.trim().toLocaleLowerCase(locale) !== project.title.trim().toLocaleLowerCase(locale)).join(' / ');
    return <article className={`project-card crop-${index % 3}`} key={project.reference}><a className="project-card-media" href={`/${locale}/projects/${project.slug}`}><CatalogueImage media={project.cover} alt={project.title} placeholder={catalogueCopy(locale).missingImage} /></a><div className="project-card-copy"><div><h2><a href={`/${locale}/projects/${project.slug}`}>{project.title}</a></h2>{categories && <p>{categories}</p>}</div><a className="project-gallery-link" href={`/${locale}/projects/${project.slug}`}><span><SiteIcon name="image" />{locale === 'ar' ? 'شاهد معرض الصور' : 'Voir la galerie'}</span><SiteIcon name="arrow" className="directional" /></a></div></article>;
  })}{Array.from({ length: projects.length ? 0 : Math.min(1, slots) }, (_, index) => <div key={`pending-${index}`} className="project-card pending-card" data-visual-placeholder><div className={`project-card-media architectural-study study-${index % 3}`}><i /><i /><i /><span>{copy.missingMedia}</span></div><div className="project-card-copy"><div><h3>{copy.pendingProject}</h3><p>{copy.pendingEditorial}</p></div><SiteIcon name="building" /></div></div>)}</div>;
}
