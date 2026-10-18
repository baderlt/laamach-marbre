import { build } from 'esbuild';
import { resolve } from 'node:path';
await build({ entryPoints: [resolve('apps/web/src/lib/viewer-admin.ts')], outfile: resolve('apps/api/public/js/viewer/preview.js'), bundle: true, format: 'esm', minify: true, target: 'es2022', legalComments: 'eof' });
console.log('Shared authenticated viewer preview built.');
