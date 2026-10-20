import { serverEnvironment } from './environment.ts';

export function trustedHost(request: Pick<Request, 'headers' | 'url'>, site: string): boolean {
  // Next's internal request URL can use its bind host/port behind a reverse proxy.
  // Validate the actual Host; configured SITE_URL alone supplies canonical URLs.
  return request.headers.get('host') === new URL(site).host;
}

export function contentSecurityPolicy(nonce: string, environment: ReturnType<typeof serverEnvironment>, development: boolean): string {
  const media = [...environment.mediaOrigins, ...(environment.stage === 'local' ? [environment.backend] : [])].join(' ');
  // GLTFLoader's ImageBitmapLoader fetches object URLs for validated embedded textures.
  return `default-src 'self'; script-src 'self' 'nonce-${nonce}'${development ? " 'unsafe-eval'" : ''}; style-src 'self' 'nonce-${nonce}'; style-src-attr 'unsafe-inline'; img-src 'self' data: blob: ${media}; font-src 'self'; connect-src 'self' blob: ${media}; media-src 'self' blob: ${media}; worker-src 'self' blob:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'`;
}

// A bounded, ephemeral per-process circuit breaker, not a distributed IP limiter.
// The reverse proxy supplies authoritative per-client limits in production.
const windows = new Map<string, { start: number; count: number }>();
export function withinRequestBudget(bucket: string, limit: number, now = Date.now()): boolean {
  const current = windows.get(bucket);
  if (!current || now - current.start >= 60_000) { windows.set(bucket, { start: now, count: 1 }); return true; }
  return ++current.count <= limit;
}
