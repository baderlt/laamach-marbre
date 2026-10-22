import { catalogueRequest } from '@/lib/catalogue-server';
import { catalogueFilters, type ProductPage } from '@/lib/catalogue';
import { findLocale } from '@/lib/locales';
import { trustedHost, withinRequestBudget } from '@/lib/http-security';
import { serverEnvironment } from '@/lib/environment';

const headers = { 'Cache-Control': 'no-store', 'X-Content-Type-Options': 'nosniff' };

export async function GET(request: Request) {
  if (!trustedHost(request, serverEnvironment(process.env).site)) return Response.json({}, { status: 403, headers });
  if (!withinRequestBudget('catalogue', 240)) return Response.json({}, { status: 429, headers });
  const params = new URL(request.url).searchParams;
  const locale = params.get('locale') || '';
  if (!findLocale(locale) || !/^(?:[1-9][0-9]{0,3}|10000)$/.test(params.get('page') || '')) return Response.json({}, { status: 400, headers });
  const filters = catalogueFilters(Object.fromEntries(params));
  const grouped = [...filters.keys()].every(key => key === 'page');
  const result = await catalogueRequest<ProductPage>(grouped ? 'product-groups' : 'products', locale, filters);
  if (!result.data) return Response.json({}, { status: result.status === 429 ? 429 : 503, headers });
  return Response.json(result.data, { headers });
}
