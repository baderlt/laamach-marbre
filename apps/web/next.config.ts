import type { NextConfig } from 'next';
import { serverEnvironment } from './src/lib/environment.ts';

serverEnvironment(process.env);

const config: NextConfig = {
  distDir: process.env.NEXT_DIST_DIR || (process.env.NODE_ENV === 'development' ? '.next-dev' : '.next'),
  poweredByHeader: false,
  async headers() {
    return [{ source: '/stone-background/:file(.*\\.webp)', headers: [
      { key: 'Cache-Control', value: 'public, max-age=31536000, immutable' },
    ] }, { source: '/:path*', headers: [
      { key: 'X-Content-Type-Options', value: 'nosniff' },
      { key: 'Referrer-Policy', value: 'strict-origin-when-cross-origin' },
      { key: 'X-Frame-Options', value: 'SAMEORIGIN' },
    ] }];
  },
};

export default config;
