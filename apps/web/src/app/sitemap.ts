import type { MetadataRoute } from 'next';
import { catalogueRequest } from '@/lib/catalogue-server';
import { serverEnvironment } from '@/lib/environment';
import { findLocale, localeConfiguration } from '@/lib/locales';

export const dynamic = 'force-dynamic';

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const result = await catalogueRequest<{ data: { path: string; locale: string }[] }>('seo', localeConfiguration.default);
  if (!result.data) throw new Error('Public sitemap source unavailable');
  const site = serverEnvironment(process.env).site;
  const rows = result.data.data.filter(row => findLocale(row.locale) && /^(?:|products|projects|about|services|contact|(?:products|projects)\/[a-z0-9-]{1,120})$/.test(row.path));
  const paths = new Map<string, Record<string, string>>();
  for (const row of rows) paths.set(row.path, { ...paths.get(row.path), [row.locale]: `${site}/${row.locale}${row.path ? `/${row.path}` : ''}` });
  return rows.map(row => ({ url: paths.get(row.path)![row.locale], alternates: { languages: paths.get(row.path)! } }));
}
