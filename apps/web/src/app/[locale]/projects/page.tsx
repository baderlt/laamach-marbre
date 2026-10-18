import { notFound } from 'next/navigation';
import { findLocale } from '@/lib/locales';
import { catalogueCopy } from '@/lib/catalogue';
import { projectsCopy, projectFilters, type ProjectsPage, type ProjectCategory } from '@/lib/projects';
import { catalogueRequest } from '@/lib/catalogue-server';
import { ProjectCards } from '@/components/project-cards';
import { siteCopy } from '@/lib/site';
import { getSite, pageMetadata } from '@/lib/site-server';
import { PageHero, SectionHeading } from '@/components/page-design';
import { ContactBanner } from '@/components/site-footer';
import { SiteIcon } from '@/components/site-icon';

export async function generateMetadata({ params }: { params: Promise<{ locale: string }> }) { const { locale } = await params; if (!findLocale(locale)) return {}; return pageMetadata(locale, 'projects', '/projects'); }

export default async function Projects({ params, searchParams }: { params: Promise<{ locale: string }>; searchParams: Promise<Record<string, string | string[] | undefined>> }) {
  const { locale } = await params;
  if (!findLocale(locale)) notFound();
  const copy = projectsCopy(locale);
  const common = catalogueCopy(locale);
  const filters = projectFilters(await searchParams);
  const design = siteCopy(locale); const page = (await getSite(locale)).pages.projects;
  const [categories, projects] = await Promise.all([catalogueRequest<{ data: ProjectCategory[] }>('project-categories', locale), catalogueRequest<ProjectsPage>('projects', locale, filters)]);
  const pageLink = (page: number) => { const query = new URLSearchParams(filters); query.set('page', String(page)); return `/${locale}/projects?${query}`; };
  return <main id="main" className="catalogue public-page projects-index">
    <PageHero locale={locale} title={page?.title || copy.projects} eyebrow={page?.eyebrow} body={page?.body || design.projectIntro} media={page?.hero} large><a className="button bronze" href={`/${locale}/contact`}>{design.contactAction}<SiteIcon name="stone" /></a><a className="button outline" href={`/${locale}/products`}>{design.productsAction}<SiteIcon name="building" /></a></PageHero>
    <section className="site-width section"><SectionHeading title={copy.categories} />
    {!categories.data || !projects.data ? <p role="status">{copy.unavailable}</p> : <>
      <form className="project-category-form" method="get"><fieldset><legend className="sr-only">{copy.categories}</legend><div className="category-rail project-category-rail"><a className={`category-choice${filters.size ? '' : ' selected'}`} href={`/${locale}/projects`}><SiteIcon name="stone" /><span>{common.all}</span></a>{categories.data.data.map(category => <label className="category-choice" key={category.id}><SiteIcon name="building" /><input type="checkbox" name="categories[]" value={category.slug} defaultChecked={filters.getAll('categories[]').includes(category.slug)} /><span>{category.name}</span></label>)}</div><div className="filter-actions"><button className="button bronze" type="submit">{common.apply}</button><a className="text-link" href={`/${locale}/projects`}>{design.clear}</a></div></fieldset></form>
      {projects.data.data.length ? <ProjectCards projects={projects.data.data} locale={locale} slots={filters.size ? 0 : 12} /> : <p>{copy.empty}</p>}
      <nav className="pagination" aria-label="Pagination">{projects.data.meta.current_page > 1 && <a className="button outline" href={pageLink(projects.data.meta.current_page - 1)}>{common.previous}</a>}<span>{projects.data.meta.current_page} / {projects.data.meta.last_page}</span>{projects.data.meta.current_page < projects.data.meta.last_page && <a className="button outline" href={pageLink(projects.data.meta.current_page + 1)}>{common.next}</a>}</nav>
    </>}</section><ContactBanner locale={locale} pageKey="projects" />
  </main>;
}
