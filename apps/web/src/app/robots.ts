import type { MetadataRoute } from 'next';

// M1 is an unpublished foundation. Launch indexing is implemented in M8.
export default function robots(): MetadataRoute.Robots {
  return { rules: { userAgent: '*', disallow: '/' } };
}
