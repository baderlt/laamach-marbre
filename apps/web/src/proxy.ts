import { NextRequest, NextResponse } from 'next/server';
import { localeForPath } from './lib/locales';
import { serverEnvironment } from './lib/environment';
import { contentSecurityPolicy } from './lib/http-security';

export function proxy(request: NextRequest) {
  const environment = serverEnvironment(process.env);
  if (request.headers.get('host') !== new URL(environment.site).host) return new NextResponse('Invalid host.', { status: 400, headers: { 'Cache-Control': 'no-store' } });
  const headers = new Headers(request.headers);
  headers.set('x-laamach-locale', localeForPath(request.nextUrl.pathname).code);
  headers.set('x-laamach-home', String(request.nextUrl.pathname === `/${localeForPath(request.nextUrl.pathname).code}`));
  const nonce = btoa(crypto.randomUUID());
  const policy = contentSecurityPolicy(nonce, environment, process.env.NODE_ENV === 'development');
  headers.set('x-nonce', nonce);
  // Next extracts its runtime nonce from the forwarded CSP request header.
  headers.set('Content-Security-Policy', policy);
  const response = NextResponse.next({ request: { headers } });
  response.headers.set('Content-Security-Policy-Report-Only', policy);
  response.headers.set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
  response.headers.set('X-Robots-Tag', environment.indexing && !request.nextUrl.pathname.startsWith('/api/') ? 'index, follow' : 'noindex, nofollow');
  if (environment.hsts) response.headers.set('Strict-Transport-Security', 'max-age=31536000');
  return response;
}

export const config = { matcher: ['/((?!_next/static|_next/image|stone-background/|brand/|favicon.ico).*)'] };
