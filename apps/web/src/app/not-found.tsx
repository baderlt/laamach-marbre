import { headers } from 'next/headers';
import { findLocale, localeConfiguration } from '@/lib/locales';
import { getMessages } from '@/lib/messages';

export default async function NotFound() {
  const code = (await headers()).get('x-laamach-locale') || localeConfiguration.default;
  const locale = findLocale(code)?.code || localeConfiguration.default;
  const copy = getMessages(locale);
  return <main><h1>{copy.missing}</h1><a href={`/${locale}`}>{copy.home}</a></main>;
}
