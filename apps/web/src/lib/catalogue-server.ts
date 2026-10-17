import 'server-only';
import { serverEnvironment } from './environment';

export async function catalogueRequest<T>(path: string, locale: string, filters = new URLSearchParams()): Promise<{ status: number; data: T | null }> {
  const config = serverEnvironment(process.env);
  const query = new URLSearchParams(filters);
  query.set('locale', locale);
  try {
    const response = await fetch(`${config.backend}/api/v1/${path}?${query}`, { cache: 'no-store', redirect: 'error', signal: AbortSignal.timeout(config.timeout), headers: { Accept: 'application/json' } });
    if (!response.ok) return { status: response.status, data: null };
    return { status: response.status, data: await response.json() as T };
  } catch { return { status: 503, data: null }; }
}
