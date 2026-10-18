type Environment = Record<string, string | undefined>;

export const provisionalImageUrl = '/preview-media/courtyard-v1.png';

export function provisionalPreviewEnabled(environment: Environment): boolean {
  if (environment.LAAMACH_PROVISIONAL_PREVIEW !== '1') return false;
  try {
    const site = new URL(environment.SITE_URL || '');
    const backend = new URL(environment.LARAVEL_API_URL || '');
    const local = (url: URL) => url.protocol === 'http:' && ['127.0.0.1', 'localhost'].includes(url.hostname) && !url.username && !url.password && url.pathname === '/' && !url.search && !url.hash;
    return local(site) && local(backend) && backend.port === '8101';
  } catch { return false; }
}

export function provisionalImageLabel(locale: string, compact = false): string {
  if (compact) return locale === 'ar' ? 'تصور بالذكاء الاصطناعي' : 'Illustration IA';
  return locale === 'ar' ? 'صورة مؤقتة مولّدة بالذكاء الاصطناعي · مشروع خيالي' : 'Image provisoire générée par IA · projet fictif';
}
