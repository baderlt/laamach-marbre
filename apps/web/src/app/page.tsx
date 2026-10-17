import { redirect } from 'next/navigation';
import { localeConfiguration } from '@/lib/locales';

export default function RootPage() { redirect(`/${localeConfiguration.default}`); }
