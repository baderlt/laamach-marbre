import { localeConfiguration } from './locales.ts';

type Messages = { title: string; description: string; language: string; backend: string; online: string; unavailable: string; retry: string; error: string; missing: string; home: string };

const messages: Record<string, Messages> = {
  fr: {
    title: 'Fondation du projet',
    description: 'Socle technique LAAMACH MARBRE. Les pages publiques seront préparées lors des prochaines étapes.',
    language: 'Langue', backend: 'Connexion au serveur', online: 'Disponible', unavailable: 'Temporairement indisponible',
    retry: 'Réessayer', error: 'Cette page est temporairement indisponible.', missing: 'Page introuvable.', home: 'Accueil',
  },
  ar: {
    title: 'أساس المشروع',
    description: 'الأساس التقني لمشروع LAAMACH MARBRE. سيتم إعداد الصفحات العامة خلال المراحل القادمة.',
    language: 'اللغة', backend: 'الاتصال بالخادم', online: 'متاح', unavailable: 'غير متاح مؤقتًا',
    retry: 'إعادة المحاولة', error: 'هذه الصفحة غير متاحة مؤقتًا.', missing: 'الصفحة غير موجودة.', home: 'الرئيسية',
  },
};

export function getMessages(locale: string): Messages {
  const copy = messages[locale] || messages[localeConfiguration.fallback];
  if (!copy) throw new Error('The configured fallback locale requires shell translations');
  return copy;
}
