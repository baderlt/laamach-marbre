import { getSite } from '@/lib/site-server';
import { serverEnvironment } from '@/lib/environment';
import { notFound } from 'next/navigation';
import { findLocale, localeConfiguration } from '@/lib/locales';
import { projectsCopy, type Project } from '@/lib/projects';
import { catalogueRequest } from '@/lib/catalogue-server';
import { ProjectGallery } from '@/components/project-gallery';
import { ProductCards } from '@/components/product-cards';
import { ProjectCards } from '@/components/project-cards';
import { PageHero, SectionHeading } from '@/components/page-design';
import { RequestDraft } from '@/components/request-draft';
import { DirectWhatsApp } from '@/components/direct-whatsapp';
import { ContactBanner } from '@/components/site-footer';
import type { Metadata } from 'next';
import { siteCopy } from '@/lib/site';
import { SiteIcon } from '@/components/site-icon';
import { PortfolioMotion } from '@/components/portfolio-motion';

export async function generateMetadata({ params }: { params: Promise<{ locale: string; slug: string }> }): Promise<Metadata> {
  const { locale, slug } = await params; if (!findLocale(locale) || !/^[a-z0-9-]{1,120}$/.test(slug)) return {};
  const response = await catalogueRequest<{ data: Project }>(`projects/${slug}`, locale);
  const project = response.data?.data;
  const image = project?.cover?.variants.find(variant => variant.format === 'webp');
  const title = project?.seo_title || project?.title;
  const description = project?.seo_description || undefined;
  return { title, description, alternates: { canonical: `/${locale}/projects/${slug}`, languages: Object.fromEntries(localeConfiguration.locales.map(item => [item.code, `/${item.code}/projects/${slug}`])) }, openGraph: { title, description, type: 'article', images: image ? [{ url: image.url, width: image.width, height: image.height, alt: project?.title }] : [] }, twitter: { card: 'summary_large_image', title, description, images: image ? [image.url] : [] } };
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
  const site = await getSite(locale);
  const canonical = `${serverEnvironment(process.env).site}/${locale}/projects/${slug}`;
  return <main id="main" className="catalogue project-detail public-page">
    <PageHero locale={locale} title={project.title} eyebrow={project.reference} body={project.summary} media={project.cover} large aside={project.location || project.year || project.area || project.area_display ? <dl className="project-metadata">{project.location && <div><dt>{copy.location}</dt><dd>{project.location}</dd></div>}{project.year && <div><dt>{copy.year}</dt><dd>{project.year}</dd></div>}{(project.area_display || project.area) && <div><dt>{copy.area}</dt><dd>{project.area_display || `${project.area} ${project.area_unit}`}</dd></div>}</dl> : undefined}><div className="project-tags">{project.categories.map(category => <a key={category.id} href={`/${locale}/projects?category=${category.slug}`}>{category.name}</a>)}</div></PageHero>
    <div className="site-width">
    {project.description && <p className="preserve-text project-intro">{project.description}</p>}
    <section className="section"><SectionHeading title={copy.gallery} /><ProjectGallery project={project} locale={locale} /></section>
    <section className="project-enquiry" aria-labelledby="project-enquiry-title" data-motion-group>
      <div><span className="project-enquiry-icon"><SiteIcon name="masonry" /></span><p className="eyebrow">{locale === 'ar' ? 'من الإلهام إلى مشروعك' : 'De l’inspiration à votre projet'}</p><h2 id="project-enquiry-title">{locale === 'ar' ? 'أيّ تفاصيل تلهمك؟' : 'Quels détails vous inspirent ?'}</h2><p>{locale === 'ar' ? 'تضم هذه المجموعة أعمالاً من مواقع مختلفة. شاركنا الصورة التي أعجبتك، لنتحدث عن الحجر والمقاسات وفكرة مشروعك.' : 'Cette collection réunit des réalisations sur différents sites. Partagez la photo qui vous plaît pour échanger sur la pierre, les dimensions et votre projet.'}</p></div>
      <div className="project-request"><DirectWhatsApp settings={site.settings} locale={locale} kind="similar" title={project.title} reference={project.reference} canonical={canonical} /><RequestDraft settings={site.settings} canonical={canonical} slug={project.slug} locale={locale} kind="similar" identity={{ title: project.title, reference: project.reference }} /></div>
    </section>
    <section className="section" data-materials-used><SectionHeading title={copy.materials} body={!project.products.length ? design.materialsPending : undefined} /><ProductCards products={project.products.slice(0, 5)} locale={locale} compact slots={5} /></section>
    {!!project.related_projects?.length && <section className="section related-projects"><SectionHeading title={copy.projects} /><ProjectCards projects={project.related_projects} locale={locale} /></section>}
    <PortfolioMotion identity={slug} /></div><ContactBanner locale={locale} pageKey="projects" />
  </main>;
}
