import sharp from 'sharp';
import { createHash } from 'node:crypto';
import { readFile, mkdir, writeFile } from 'node:fs/promises';
import { resolve, basename } from 'node:path';

// Explicit client source only; never download or replace the photograph.
if (!process.argv[2]) throw new Error('Pass the approved client photograph path.');
const source = await readFile(resolve(process.argv[2]));
const output = resolve('apps/web/public/stone-background');
await mkdir(output, { recursive: true });
const variants = {};
for (const [name, width, height, blur] of [
  ['mobile', 640, 1120, 1.2], ['tablet', 1024, 1024, 2.5], ['desktop', 1600, 1000, 3.5],
  ['tabletDetail', 1024, 1024, 0], ['desktopDetail', 1600, 1000, 0],
]) {
  let image = sharp(source).rotate().resize(width, height, { fit: 'cover', position: 'centre' });
  if (blur) image = image.blur(blur);
  const bytes = await image.webp({ quality: blur ? 80 : 86, effort: 6 }).toBuffer();
  const hash = createHash('sha256').update(bytes).digest('hex');
  const file = `${name}-${hash.slice(0, 16)}.webp`;
  await writeFile(resolve(output, file), bytes);
  variants[name] = { url: `/stone-background/${file}`, width, height, bytes: bytes.length, sha256: hash };
}
await writeFile(resolve(output, 'manifest.json'), JSON.stringify({
  provenance: 'client-supplied; approved for website background',
  originalFilename: basename(process.argv[2]),
  originalSha256: createHash('sha256').update(source).digest('hex'),
  processing: 'EXIF orientation; centre crop without stretching; resize; fixed soft blur on base only; WebP; metadata stripped. No recolouring or generated content.',
  variants,
}, null, 2) + '\n');
console.log(Object.fromEntries(Object.entries(variants).map(([name, v]) => [name, v.bytes])));
