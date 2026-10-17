type Environment = Record<string, string | undefined>;

export function origin(value: string | undefined, name: string): string {
  if (!value) throw new Error(`${name} is required`);
  let parsed: URL;
  try { parsed = new URL(value); } catch { throw new Error(`${name} must be an HTTP(S) origin`); }
  if (!['http:', 'https:'].includes(parsed.protocol) || parsed.username || parsed.password || parsed.pathname !== '/' || parsed.search || parsed.hash) {
    throw new Error(`${name} must be an HTTP(S) origin without credentials, paths or query parameters`);
  }
  return parsed.origin;
}

export function serverEnvironment(environment: Environment) {
  const timeout = Number(environment.API_TIMEOUT_MS || '3000');
  if (!Number.isInteger(timeout) || timeout < 500 || timeout > 8000) throw new Error('API_TIMEOUT_MS must be between 500 and 8000');
  return {
    site: origin(environment.SITE_URL, 'SITE_URL'),
    backend: origin(environment.LARAVEL_API_URL, 'LARAVEL_API_URL'),
    timeout,
  };
}
