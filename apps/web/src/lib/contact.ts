import type { WhatsAppSettings } from './whatsapp.ts';
import { whatsappUrlBudget } from './whatsapp.ts';
import { findLocale } from './locales.ts';

export type ContactFields = { name: string; email: string; phone: string; request: string; message: string };
export function contactChatUrl(settings: WhatsAppSettings | null | undefined, locale: string, fields?: ContactFields): { url: string; message: string } | null {
  if (!findLocale(locale) || !settings?.enabled || !settings.number || !/^[1-9]\d{7,14}$/.test(settings.number)) return null;
  const ar = locale === 'ar';
  let message = ar ? 'السلام عليكم LAAMACH MARBRE، أود الاستفسار عن الحجر الطبيعي ومناقشة مشروعي.' : 'Bonjour LAAMACH MARBRE, je souhaite me renseigner sur vos pierres naturelles et discuter de mon projet.';
  if (fields) {
    const limits = { name: 120, email: 254, phone: 40, request: 80, message: 1800 };
    if (Object.entries(limits).some(([key, max]) => typeof fields[key as keyof ContactFields] !== 'string' || fields[key as keyof ContactFields].length > max || /[\u0000-\u0008\u000b-\u001f\u007f\u202a-\u202e\u2066-\u2069]/u.test(fields[key as keyof ContactFields]))) return null;
    if (!fields.name.trim() || !fields.request.trim() || !fields.message.trim() || (fields.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(fields.email))) return null;
    const labels = ar ? { name: 'الاسم', email: 'البريد الإلكتروني', phone: 'الهاتف', request: 'موضوع الطلب', message: 'تفاصيل المشروع' } : { name: 'Nom', email: 'E-mail', phone: 'Téléphone', request: 'Objet', message: 'Mon projet' };
    message += '\n\n' + (Object.keys(labels) as (keyof ContactFields)[]).filter(key => fields[key].trim()).map(key => `${labels[key]} : ${fields[key].trim()}`).join('\n');
  }
  const url = `https://wa.me/${settings.number}?text=${encodeURIComponent(message)}`;
  return url.length <= whatsappUrlBudget ? { url, message } : null;
}
