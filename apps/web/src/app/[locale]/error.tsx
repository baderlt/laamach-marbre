'use client';

import { useParams } from 'next/navigation';
import { getMessages } from '@/lib/messages';
import { localeConfiguration } from '@/lib/locales';

export default function ErrorBoundary({ reset }: { error: Error & { digest?: string }; reset(): void }) {
  const params = useParams<{ locale: string }>();
  const copy = getMessages(params.locale || localeConfiguration.default);
  return <main><h1>{copy.error}</h1><button onClick={reset}>{copy.retry}</button></main>;
}
