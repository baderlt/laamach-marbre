import { catalogueRequest } from '@/lib/catalogue-server';
import { findLocale } from '@/lib/locales';
import type { ProductPage } from '@/lib/catalogue';
import { trustedHost, withinRequestBudget } from '@/lib/http-security';
import { serverEnvironment } from '@/lib/environment';

const headers = { 'Cache-Control': 'no-store', 'X-Content-Type-Options': 'nosniff' };

export async function GET(request: Request) {
  if (!trustedHost(request, serverEnvironment(process.env).site)) return Response.json({ data: [], total: 0 }, { status: 403, headers });
  if (!withinRequestBudget('search', 240)) return Response.json({ data: [], total: 0 }, { status: 429, headers });
  const params = new URL(request.url).searchParams;
  const locale = params.get('locale') || '';
  const query = (params.get('q') || '').trim();
  if (!findLocale(locale) || query.length < 2 || query.length > 120) {
    return Response.json({ data: [], total: 0 }, { status: 400, headers });
  }
  // Reuse the published catalogue and its validated, bounded search query.
  const result = await catalogueRequest<ProductPage>('products', locale, new URLSearchParams({ q: query, per_page: '6' }));
  if (!result.data) return Response.json({ data: [], total: 0 }, { status: 503, headers });
  return Response.json({ data: result.data.data.slice(0, 6), total: result.data.meta.total }, { headers });
}
