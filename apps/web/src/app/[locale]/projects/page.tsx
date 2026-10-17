import { notFound } from 'next/navigation';
import { findLocale, localeConfiguration } from '@/lib/locales';
import { catalogueCopy } from '@/lib/catalogue';
import { projectsCopy, projectFilters, type ProjectsPage, type ProjectCategory } from '@/lib/projects';
import { catalogueRequest } from '@/lib/catalogue-server';
import { ProjectCards } from '@/components/project-cards';

export default async function Projects({ params, searchParams }: { params: Promise<{ locale: string }>; searchParams: Promise<Record<string, string | string[] | undefined>> }) {
  const { locale } = await params;
  if (!findLocale(locale)) notFound();
  const copy = projectsCopy(locale);
  const common = catalogueCopy(locale);
  const filters = projectFilters(await searchParams);
  const [categories, projects] = await Promise.all([catalogueRequest<{ data: ProjectCategory[] }>('project-categories', locale), catalogueRequest<ProjectsPage>('projects', locale, filters)]);
  const pageLink = (page: number) => { const query = new URLSearchParams(filters); query.set('page', String(page)); return `/${locale}/projects?${query}`; };
  return <main className="catalogue">
    <nav aria-label={copy.projects}><a href={`/${locale}`}>{common.home}</a><a href={`/${locale}/products`}>{common.catalogue}</a>{localeConfiguration.locales.map(item => <a key={item.code} href={`/${item.code}/projects?${filters}`} lang={item.code} dir={item.direction} aria-current={item.code === locale ? 'page' : undefined}>{item.name}</a>)}</nav>
    <h1>{copy.projects}</h1>
    {!categories.data || !projects.data ? <p role="status">{copy.unavailable}</p> : <>
      <form method="get"><fieldset><legend>{copy.categories}</legend><div className="option-list">{categories.data.data.map(category => <label className="category-choice" key={category.id}><input type="checkbox" name="categories[]" value={category.slug} defaultChecked={filters.getAll('categories[]').includes(category.slug)} />{category.name}</label>)}</div><button type="submit">{common.apply}</button></fieldset></form>
      {projects.data.data.length ? <ProjectCards projects={projects.data.data} locale={locale} /> : <p>{copy.empty}</p>}
      <nav aria-label="Pagination">{projects.data.meta.current_page > 1 && <a href={pageLink(projects.data.meta.current_page - 1)}>{common.previous}</a>}<span>{projects.data.meta.current_page} / {projects.data.meta.last_page}</span>{projects.data.meta.current_page < projects.data.meta.last_page && <a href={pageLink(projects.data.meta.current_page + 1)}>{common.next}</a>}</nav>
    </>}
  </main>;
}
