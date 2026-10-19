'use client';

import { useState } from 'react';
import { siteCopy, type SiteSettings } from '@/lib/site';
import { contactChatUrl, type ContactFields } from '@/lib/contact';
import { SiteIcon } from './site-icon';

export function ContactForm({ locale, settings }: { locale: string; settings: SiteSettings | null }) {
  const copy = siteCopy(locale), ar = locale === 'ar';
  const [prepared, setPrepared] = useState<{ url: string; message: string } | null>(null);
  const [error, setError] = useState('');
  const chat = contactChatUrl(settings?.whatsapp, locale);
  const email = settings?.email;
  const available = !!chat || !!email;
  return <form id="contact-form" className="contact-form premium-contact-form" onChange={() => { setPrepared(null); setError(''); }} onSubmit={event => {
    event.preventDefault();
    if (!available) return;
    const values = new FormData(event.currentTarget);
    const fields = Object.fromEntries(['name', 'email', 'phone', 'request', 'message'].map(key => [key, String(values.get(key) || '').trim()])) as ContactFields;
    if (chat) {
      const result = contactChatUrl(settings?.whatsapp, locale, fields);
      if (result) setPrepared(result);
      else setError(ar ? 'راجع بياناتك واختصر تفاصيل الرسالة ثم حاول مجدداً.' : 'Vérifiez vos coordonnées et raccourcissez votre message avant de réessayer.');
    } else if (email) {
      const message = `${fields.name}\n${fields.email}\n${fields.phone}\n${fields.request}\n\n${fields.message}`;
      setPrepared({ message, url: `mailto:${encodeURIComponent(email)}?subject=${encodeURIComponent('Contact — LAAMACH MARBRE')}&body=${encodeURIComponent(message)}` });
    }
  }}>
    <p className="eyebrow">{ar ? 'حدثنا عن فكرتك' : 'Parlez-nous de votre idée'}</p>
    <h2>{ar ? 'لنمنح مشروعك طابع الحجر' : 'Donnons de la matière à votre projet'}</h2>
    <p>{ar ? 'شاركنا المواد التي تهمك والأبعاد والمكان، لنناقش معك التفاصيل.' : 'Une matière, des dimensions, un lieu : partagez les premiers détails pour échanger avec notre équipe.'}</p>
    <fieldset disabled={!available}>
      <div className="form-row">
        <label>{copy.name}<span className="required-label">{ar ? 'مطلوب' : 'Requis'}</span><input name="name" autoComplete="name" required maxLength={120} dir="auto" placeholder={ar ? 'الاسم الكامل' : 'Votre nom complet'} /></label>
        <label>{copy.requestType}<select name="request" required defaultValue=""><option value="" disabled>{ar ? 'اختر موضوع طلبك' : 'Choisissez votre demande'}</option><option>{copy.informationRequest}</option><option>{copy.quoteRequest}</option><option>{copy.otherRequest}</option></select></label>
        <label>{ar ? 'البريد الإلكتروني (اختياري)' : 'E-mail (facultatif)'}<input name="email" type="email" autoComplete="email" maxLength={254} dir="ltr" placeholder="vous@exemple.ma" /></label>
        <label>{copy.phoneOptional}<input name="phone" type="tel" autoComplete="tel" maxLength={40} dir="ltr" placeholder="+212 …" /></label>
      </div>
      <label>{ar ? 'تفاصيل مشروعك' : 'Les détails de votre projet'}<textarea name="message" rows={5} required maxLength={1800} dir="auto" placeholder={ar ? 'ما الحجر الذي يلهمك؟ أين يقع مشروعك وما الأبعاد المطلوبة؟' : 'Quelle pierre vous inspire ? Où se situe le projet et quelles dimensions envisagez-vous ?'} /></label>
      {chat && <div className="contact-attachment-note"><SiteIcon name="image" /><div><strong>{ar ? 'صور أو مخططات؟' : 'Des photos ou des plans ?'}</strong><p>{ar ? 'يمكنك إرفاقها مباشرة داخل واتساب بعد فتح المحادثة.' : 'Vous pourrez les joindre directement dans WhatsApp après l’ouverture de la discussion.'}</p></div></div>}
      <p className="contact-channel-note"><SiteIcon name={chat ? 'whatsapp' : 'mail'} />{chat ? (ar ? 'حضّر رسالتك، ثم افتح واتساب لإرسالها إلى فريقنا.' : 'Préparez votre message, puis ouvrez WhatsApp pour l’envoyer à notre équipe.') : (email ? copy.mailNote : (ar ? 'يمكنك تصفح المواد والمشاريع خلال تحديث معلومات الاتصال.' : 'Explorez nos matières et nos réalisations pendant la mise à jour des coordonnées.'))}</p>
      <button className="button bronze contact-submit" type="submit">{ar ? 'تحضير الرسالة' : 'Préparer mon message'}<SiteIcon name={chat ? 'whatsapp' : 'mail'} /></button>
    </fieldset>
    {error && <p role="alert" className="contact-form-error">{error}</p>}
    {prepared && <section className="contact-message-review" aria-label={ar ? 'مراجعة الرسالة' : 'Vérifier le message'} aria-live="polite"><label>{ar ? 'رسالتك جاهزة للمراجعة' : 'Votre message est prêt à être vérifié'}<textarea readOnly value={prepared.message} rows={6} dir="auto" /></label><a className="button bronze" href={prepared.url} target="_blank" rel="noopener noreferrer">{chat ? (ar ? 'فتح واتساب' : 'Ouvrir WhatsApp') : copy.send}<SiteIcon name={chat ? 'whatsapp' : 'mail'} /></a></section>}
  </form>;
}
