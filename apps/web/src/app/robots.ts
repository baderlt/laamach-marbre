import type { MetadataRoute } from 'next';
import { serverEnvironment } from '@/lib/environment';

export const dynamic = 'force-dynamic';

export default function robots(): MetadataRoute.Robots {
  const environment = serverEnvironment(process.env);
  return environment.indexing ? { rules: { userAgent: '*', allow: '/', disallow: ['/api/', '/admin/', '/*?'] }, sitemap: `${environment.site}/sitemap.xml`, host: environment.site } : { rules: { userAgent: '*', disallow: '/' } };
}
