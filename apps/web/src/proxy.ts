import { NextRequest, NextResponse } from 'next/server';
import { localeForPath } from './lib/locales';

export function proxy(request: NextRequest) {
  const headers = new Headers(request.headers);
  headers.set('x-laamach-locale', localeForPath(request.nextUrl.pathname).code);
  return NextResponse.next({ request: { headers } });
}

export const config = { matcher: ['/((?!_next/static|_next/image|favicon.ico).*)'] };
