import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { provisionalPreviewEnabled } from '@/lib/provisional-preview';

export const dynamic = 'force-dynamic';
export const runtime = 'nodejs';

export async function GET(_request: Request, { params }: { params: Promise<{ file: string }> }) {
  if (!provisionalPreviewEnabled(process.env) || (await params).file !== 'courtyard-v1.png') return new Response(null, { status: 404 });
  try {
    const content = await readFile(resolve(process.cwd(), '../../.tools/content-preview/ai/courtyard-v1.png'));
    return new Response(content, { headers: { 'Content-Type': 'image/png', 'Cache-Control': 'private, max-age=3600', 'X-Robots-Tag': 'noindex, nofollow' } });
  } catch { return new Response(null, { status: 404 }); }
}
