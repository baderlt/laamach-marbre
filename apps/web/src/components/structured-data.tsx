import { headers } from 'next/headers';
import { jsonLd } from '@/lib/seo';

export async function StructuredData({ value }: { value: unknown }) {
  return <script nonce={(await headers()).get('x-nonce') || undefined} type="application/ld+json" dangerouslySetInnerHTML={{ __html: jsonLd(value) }} />;
}
