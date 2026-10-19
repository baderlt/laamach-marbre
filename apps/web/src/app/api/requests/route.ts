import { catalogueRequest } from '@/lib/catalogue-server';
import { serverEnvironment } from '@/lib/environment';
import { parseRequest, prepareRequest } from '@/lib/whatsapp';
import type { Product } from '@/lib/catalogue';
import type { Project } from '@/lib/projects';
import type { SiteData } from '@/lib/site';

export const runtime = 'nodejs';
const headers = { 'Cache-Control': 'no-store', 'X-Content-Type-Options': 'nosniff' };
const reply = (data: unknown, status = 200) => Response.json(data, { status, headers });

// Stateless preparation only: no persistence, redirects, analytics or external messaging.
export async function POST(request: Request) {
  const environment = serverEnvironment(process.env);
  if (request.headers.get('origin') !== environment.site || request.headers.get('content-type')?.split(';')[0].trim() !== 'application/json') return reply({ errors: ['unavailable'] }, 403);
  const reader = request.body?.getReader();
  if (!reader) return reply({ errors: ['invalid'] }, 400);
  const chunks: Uint8Array[] = []; let length = 0;
  try {
    while (true) {
      const { value, done } = await reader.read();
      if (done) break;
      length += value.byteLength;
      if (length > 16384) { await reader.cancel(); return reply({ errors: ['invalid'] }, 413); }
      chunks.push(value);
    }
    const data = parseRequest(JSON.parse(Buffer.concat(chunks).toString('utf8')));
    if (!data) return reply({ errors: ['invalid'] }, 422);
    const [site, context] = await Promise.all([
      catalogueRequest<{ data: SiteData }>('site', data.locale),
      catalogueRequest<{ data: Product | Project }>(`${data.kind === 'similar' ? 'projects' : 'products'}/${data.slug}`, data.locale),
    ]);
    if (!site.data || !context.data) return reply({ errors: ['unavailable'] }, context.status === 404 ? 404 : 503);
    const result = prepareRequest(data.kind, data.locale, context.data.data, data.fields, site.data.data.settings?.whatsapp, environment.site);
    return reply(result, result.errors.length ? (result.errors.includes('unavailable') ? 409 : 422) : 200);
  } catch {
    return reply({ errors: ['invalid'] }, 400);
  } finally { reader.releaseLock(); }
}
