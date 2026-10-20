type Environment = Record<string, string | undefined>;

export function origin(value: string | undefined, name: string): string {
  if (!value) throw new Error(`${name} is required`);
  let parsed: URL;
  try { parsed = new URL(value); } catch { throw new Error(`${name} must be an HTTP(S) origin`); }
  if (!['http:', 'https:'].includes(parsed.protocol) || parsed.username || parsed.password || parsed.hostname.includes('*') || /[\u0000-\u0020\u007f]/.test(value) || parsed.pathname !== '/' || parsed.search || parsed.hash) {
    throw new Error(`${name} must be an HTTP(S) origin without credentials, paths or query parameters`);
  }
  return parsed.origin;
}

export function serverEnvironment(environment: Environment) {
  const timeout = Number(environment.API_TIMEOUT_MS || '3000');
  if (!Number.isInteger(timeout) || timeout < 500 || timeout > 8000) throw new Error('API_TIMEOUT_MS must be between 500 and 8000');
  const site = origin(environment.SITE_URL, 'SITE_URL');
  const stage = environment.SITE_ENV || 'local';
  if (!['local', 'staging', 'production'].includes(stage)) throw new Error('SITE_ENV must be local, staging or production');
  if (stage !== 'local' && (!site.startsWith('https:') || /^(localhost|127\.|\[?::1)/.test(new URL(site).hostname))) throw new Error('Staging and production SITE_URL require a public HTTPS origin');
  const mediaOrigins = (environment.MEDIA_ORIGINS || '').split(',').filter(Boolean).map(value => origin(value.trim(), 'MEDIA_ORIGINS'));
  if (mediaOrigins.length > 12 || (stage !== 'local' && mediaOrigins.some(value => !value.startsWith('https:')))) throw new Error('Configure at most twelve trusted media origins; HTTPS is required outside local');
  if (environment.SITE_INDEXING_ENABLED && !['true', 'false'].includes(environment.SITE_INDEXING_ENABLED)) throw new Error('SITE_INDEXING_ENABLED must be true or false');
  return {
    site,
    backend: origin(environment.LARAVEL_API_URL, 'LARAVEL_API_URL'),
    timeout,
    stage,
    indexing: stage === 'production' && environment.SITE_INDEXING_ENABLED === 'true',
    mediaOrigins,
    hsts: stage === 'production' && environment.HSTS_ENABLED === 'true',
  };
}
