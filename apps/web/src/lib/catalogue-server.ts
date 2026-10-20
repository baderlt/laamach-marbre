import 'server-only';
import { cache } from 'react';
import { serverEnvironment } from './environment';

// A timeout signal opts out of Next's automatic fetch deduplication. Share only
// identical reads within this React server request, never across requests/users.
const request = cache(async (path: string, locale: string, filters: string): Promise<{ status: number; data: unknown }> => {
  const config = serverEnvironment(process.env);
  const query = new URLSearchParams(filters);
  query.set('locale', locale);
  try {
    const response = await fetch(`${config.backend}/api/v1/${path}?${query}`, { cache: 'no-store', redirect: 'error', signal: AbortSignal.timeout(config.timeout), headers: { Accept: 'application/json' } });
    if (!response.ok) return { status: response.status, data: null };
    return { status: response.status, data: await response.json() as unknown };
  } catch { return { status: 503, data: null }; }
});

export async function catalogueRequest<T>(path: string, locale: string, filters = new URLSearchParams()): Promise<{ status: number; data: T | null }> {
  return await request(path, locale, filters.toString()) as { status: number; data: T | null };
}
