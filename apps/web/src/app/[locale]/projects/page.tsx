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
import { ProjectCategoryFilter } from '@/components/project-category-filter';
import { PortfolioMotion } from '@/components/portfolio-motion';

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
    <PageHero locale={locale} title={page?.title || copy.projects} eyebrow={page?.eyebrow} body={page?.body || design.projectIntro} media={page?.hero || projects.data?.data[0]?.cover} variant="photo-banner portfolio-hero" large><a className="button bronze" href={`/${locale}/contact`}>{design.contactAction}<SiteIcon name="arrow" className="directional" /></a><a className="button outline" href={`/${locale}/products`}>{design.productsAction}<SiteIcon name="stone" /></a></PageHero>
    <section className="site-width section"><SectionHeading title={copy.categories} />
    {!categories.data || !projects.data ? <p role="status">{copy.unavailable}</p> : <>
      <ProjectCategoryFilter categories={categories.data.data} selected={filters.getAll('categories[]')} locale={locale} total={projects.data.meta.total} />
      {projects.data.data.length ? <ProjectCards projects={projects.data.data} locale={locale} slots={filters.size ? 0 : 12} /> : <p>{copy.empty}</p>}
      {projects.data.meta.last_page > 1 && <nav className="pagination portfolio-pagination" aria-label={locale === 'ar' ? 'صفحات المجموعات' : 'Pages des collections'}>
        {projects.data.meta.current_page > 1 && <a className="pagination-step" href={pageLink(projects.data.meta.current_page - 1)}><SiteIcon name="arrow" className="project-view-previous directional" /><span>{common.previous}</span></a>}
        {[...new Set([1, projects.data.meta.last_page, projects.data.meta.current_page - 1, projects.data.meta.current_page, projects.data.meta.current_page + 1])].filter(number => number >= 1 && number <= projects.data!.meta.last_page).sort((a, b) => a - b).map((number, index, pages) => <span className="pagination-item" key={number}>{index > 0 && number > pages[index - 1] + 1 && <span aria-hidden="true">…</span>}<a href={pageLink(number)} aria-label={`${locale === 'ar' ? 'الصفحة' : 'Page'} ${number}`} aria-current={number === projects.data!.meta.current_page ? 'page' : undefined}>{number}</a></span>)}
        {projects.data.meta.current_page < projects.data.meta.last_page && <a className="pagination-step" href={pageLink(projects.data.meta.current_page + 1)}><span>{common.next}</span><SiteIcon name="arrow" className="directional" /></a>}
      </nav>}
      <PortfolioMotion identity={filters.toString()} />
    </>}</section><ContactBanner locale={locale} pageKey="projects" />
  </main>;
}
