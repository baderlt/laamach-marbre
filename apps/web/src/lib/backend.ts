import 'server-only';
import { serverEnvironment } from './environment';

export async function backendAvailable(): Promise<boolean> {
  const config = serverEnvironment(process.env);
  try {
    const response = await fetch(`${config.backend}/api/v1/health`, {
      cache: 'no-store', redirect: 'error', signal: AbortSignal.timeout(config.timeout),
      headers: { Accept: 'application/json' },
    });
    if (!response.ok) return false;
    const body: unknown = await response.json();
    return !!body && typeof body === 'object' && 'application' in body && body.application === 'laamach-marbre' && 'status' in body && body.status === 'ok';
  } catch { return false; }
}
