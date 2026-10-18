'use client';

import { useEffect, useId, useRef, useState } from 'react';
import { trapDialogFocus } from '@/lib/dialog-focus';
import type { Product } from '@/lib/catalogue';
import { validateDraftText, validateMaterialDraft, quantityUnits, type DraftFields } from '@/lib/request-draft';
import { requestAvailable, unitLabel, type PreparedRequest, type RequestKind } from '@/lib/whatsapp';
import { phoneHref, siteCopy, type SiteSettings } from '@/lib/site';

const messages = {
  fr: { material: 'Demander un devis matière', similar: 'Je souhaite un projet similaire', application: 'Utiliser cette pierre dans mon projet', note: 'Vos choix servent uniquement à préparer le message. Aucun envoi automatique. Envoyez-le vous-même dans WhatsApp.', size: 'Format standard', custom: 'Format personnalisé', width: 'Largeur', length: 'Longueur', finish: 'Finition', quantity: 'Quantité', unit: 'Unité de quantité', city: 'Ville de votre projet (facultatif)', dimensions: 'Dimensions ou surface envisagées (facultatif)', projectType: 'Type de projet (facultatif)', use: 'Utilisation envisagée (facultatif)', description: 'Décrivez votre besoin (facultatif)', review: 'Vérifier le message', ready: 'Votre message est prêt à être vérifié', edit: 'Modifier mes choix', choose: 'Choisir', pending: 'Les formats, finitions et unités doivent être confirmés avant un devis matière.', invalid: 'Vérifiez les champs indiqués avant de continuer.', fieldError: 'Choisissez une valeur autorisée et respectez les limites affichées.', details: 'Votre sélection', whatsapp: 'Ouvrir WhatsApp', copy: 'Copier le message', copied: 'Message copié. Collez-le dans WhatsApp et envoyez-le vous-même.', copyFailed: 'La copie automatique est indisponible. Le texte est sélectionné : copiez-le manuellement.', message: 'Message complet à copier', oversized: 'Ce message est trop long pour un lien fiable. Copiez le texte complet et contactez-nous manuellement.', unavailable: 'Les demandes WhatsApp ne sont pas disponibles pour le moment.', contact: 'Autres moyens de contact', phone: 'Téléphoner', email: 'Écrire un e-mail', busy: 'Vérification des informations…', failed: 'La préparation est indisponible. Réessayez ou utilisez les coordonnées de contact.', decimal: 'Valeur positive, jusqu’à 6 décimales et inférieure à 1 milliard.', privacy: 'Évitez les informations sensibles. Le message est préparé sans être enregistré sur notre site.', noStock: 'Le format sélectionné ne garantit ni stock ni disponibilité.' },
  ar: { material: 'اطلب عرض سعر للمادة', similar: 'أريد مشروعاً مثل هذا', application: 'أريد استعمال هذا الحجر في مشروعي', note: 'تُستخدم اختياراتك لتحضير الرسالة فقط، دون إرسال تلقائي. أرسلها بنفسك داخل واتساب.', size: 'المقاس القياسي', custom: 'مقاس مخصص', width: 'العرض', length: 'الطول', finish: 'التشطيب', quantity: 'الكمية', unit: 'وحدة الكمية', city: 'مدينة مشروعك (اختياري)', dimensions: 'الأبعاد أو المساحة المقترحة (اختياري)', projectType: 'نوع المشروع (اختياري)', use: 'الاستخدام المقترح (اختياري)', description: 'صف احتياجك (اختياري)', review: 'راجع الرسالة', ready: 'رسالتك جاهزة للمراجعة', edit: 'تعديل الاختيارات', choose: 'اختر', pending: 'يجب تأكيد المقاسات والتشطيبات والوحدات قبل طلب عرض سعر للمادة.', invalid: 'راجع الحقول المشار إليها قبل المتابعة.', fieldError: 'اختر قيمة مسموحًا بها والتزم بالحدود المعروضة.', details: 'اختياراتك', whatsapp: 'افتح واتساب', copy: 'انسخ الرسالة', copied: 'نُسخت الرسالة. الصقها في واتساب وأرسلها بنفسك.', copyFailed: 'النسخ التلقائي غير متاح. تم تحديد النص لنسخه يدوياً.', message: 'النص الكامل للنسخ', oversized: 'الرسالة طويلة ولا يمكن فتحها برابط موثوق. انسخ النص الكامل وتواصل معنا يدوياً.', unavailable: 'طلبات واتساب غير متاحة حالياً.', contact: 'وسائل تواصل أخرى', phone: 'اتصل هاتفياً', email: 'راسلنا بالبريد', busy: 'جارٍ التحقق من المعلومات…', failed: 'تعذر تحضير الرسالة. حاول مجدداً أو استخدم معلومات الاتصال.', decimal: 'قيمة موجبة بأقصى حد 6 منازل عشرية وأقل من مليار.', privacy: 'تجنب المعلومات الحساسة. تُحضّر الرسالة دون حفظها في موقعنا.', noStock: 'اختيار المقاس لا يضمن المخزون أو التوفر.' },
};

type Props = { locale: string; kind: RequestKind; identity: { title: string; reference: string }; slug: string; canonical: string; settings: SiteSettings | null; product?: Product; pending?: boolean; sizeId?: string | null; finishId?: string; onSize?: (value: string) => void; onFinish?: (value: string) => void };
export function RequestDraft({ locale, kind, identity, slug, canonical, settings, product, pending = false, sizeId, finishId, onSize, onFinish }: Props) {
  const copy = messages[locale as keyof typeof messages] || messages.fr;
  const dialog = useRef<HTMLDialogElement>(null); const summary = useRef<HTMLDivElement>(null); const trigger = useRef<HTMLButtonElement>(null);
  const message = useRef<HTMLTextAreaElement>(null); const reviewPanel = useRef<HTMLDivElement>(null); const controller = useRef<AbortController | null>(null);
  const id = useId(); const [errors, setErrors] = useState<string[]>([]); const [custom, setCustom] = useState(false);
  const [review, setReview] = useState<PreparedRequest | null>(null); const [busy, setBusy] = useState(false); const [copyStatus, setCopyStatus] = useState('');
  useEffect(() => {
    if (!dialog.current?.open) return;
    if (review) reviewPanel.current?.focus();
    else dialog.current.querySelector<HTMLElement>('form input:not(:disabled),form select:not(:disabled),form textarea:not(:disabled)')?.focus();
  }, [review]);
  useEffect(() => () => controller.current?.abort(), []);
  const material = kind === 'material' && product;
  const available = requestAvailable(settings?.whatsapp, kind) && !(material && pending);
  const error = (name: string) => errors.includes(name) ? <span className="field-error" id={`${id}-${name}-error`}>{copy.fieldError}</span> : null;
  const attrs = (name: string) => ({ id: `${id}-${name}`, name, 'aria-invalid': errors.includes(name) || undefined, 'aria-describedby': errors.includes(name) ? `${id}-${name}-error` : undefined });
  const fallback = <nav className="request-contact" aria-label={copy.contact}><a href={`/${locale}/contact`}>{copy.contact}</a>{phoneHref(settings?.phone) && <a href={phoneHref(settings?.phone)}>{copy.phone}</a>}{settings?.email && <a href={`mailto:${settings.email}`}>{copy.email}</a>}</nav>;
  if (!available) return <div className={`request-entry request-${kind} request-unavailable`}><p><strong>{copy[kind]}</strong></p><p>{material && pending ? copy.pending : copy.unavailable}</p>{fallback}</div>;
  async function submit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault(); if (busy) return;
    const data = new FormData(event.currentTarget); const get = (name: string) => String(data.get(name) || '').trim();
    const fields: DraftFields = { size: get('size'), custom, width: get('width'), length: get('length'), finish: get('finish'), quantity: get('quantity'), unit: get('unit'), city: get('city'), dimensions: get('dimensions'), description: get('description'), projectType: get('projectType'), application: get('application') };
    const invalid = material ? validateMaterialDraft(product, fields) : validateDraftText(fields);
    setErrors(invalid); if (invalid.length) { requestAnimationFrame(() => summary.current?.focus()); return; }
    const keys = material ? ['size', 'custom', 'width', 'length', 'finish', 'quantity', 'unit', 'city', 'description'] : kind === 'similar' ? ['city', 'dimensions', 'description'] : ['city', 'dimensions', 'description', 'projectType', 'application'];
    const payload = Object.fromEntries(keys.map(key => [key, fields[key as keyof DraftFields]]));
    controller.current = new AbortController(); setBusy(true);
    try {
      const response = await fetch('/api/requests', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ kind, slug, locale, fields: payload }), signal: controller.current.signal });
      const result = await response.json() as { errors: string[]; prepared?: PreparedRequest };
      if (!response.ok || !result.prepared) { setErrors(result.errors?.length ? result.errors : ['unavailable']); requestAnimationFrame(() => summary.current?.focus()); }
      else { setReview(result.prepared); setCopyStatus(''); }
    } catch (error) {
      if (!(error instanceof DOMException && error.name === 'AbortError')) { setErrors(['unavailable']); requestAnimationFrame(() => summary.current?.focus()); }
    } finally { setBusy(false); }
  }
  return <div className={`request-entry request-${kind}`}><button ref={trigger} type="button" className={`button ${kind === 'material' || kind === 'similar' ? 'bronze' : 'outline'}`} onClick={() => dialog.current?.showModal()}>{copy[kind]}</button>
    <dialog onKeyDown={trapDialogFocus} ref={dialog} className="request-dialog" aria-labelledby={`${id}-title`} aria-describedby={`${id}-disclosure`} onClose={() => { controller.current?.abort(); setReview(null); setErrors([]); setCopyStatus(''); trigger.current?.focus({ preventScroll: true }); }}>
      <div className="drawer-heading"><h2 id={`${id}-title`}>{copy[kind]}</h2><button type="button" className="icon-button" aria-label={siteCopy(locale).close} onClick={() => dialog.current?.close()}>×</button></div>
      <p className="request-identity"><strong>{identity.title}</strong><bdi>{identity.reference}</bdi><a href={canonical}>{canonical}</a></p><p id={`${id}-disclosure`} className="request-disclosure">{copy.note}</p>
      <div ref={reviewPanel} tabIndex={-1} hidden={!review} className="request-review"><h3>{copy.ready}</h3><label htmlFor={`${id}-message`}>{copy.message}<textarea id={`${id}-message`} ref={message} readOnly value={review?.message || ''} rows={12} /></label>
        {review?.oversized && <p role="status">{copy.oversized}</p>}
        <div className="request-review-actions">{review?.url && <a className="button bronze" data-whatsapp href={review.url} target="_blank" rel="noopener noreferrer" referrerPolicy="no-referrer">{copy.whatsapp}</a>}
        <button type="button" className="button outline" onClick={async () => { try { await navigator.clipboard.writeText(review!.message); setCopyStatus(copy.copied); } catch { message.current?.focus(); message.current?.select(); setCopyStatus(copy.copyFailed); } }}>{copy.copy}</button><button type="button" className="button outline" onClick={() => { setReview(null); setCopyStatus(''); }}>{copy.edit}</button></div><p role="status" aria-live="polite">{copyStatus}</p>{fallback}</div>
      <form hidden={!!review} noValidate onSubmit={submit} aria-busy={busy}>
        {!!errors.length && <div ref={summary} tabIndex={-1} role="alert" className="error-summary"><p>{errors.some(name => ['unavailable', 'invalid'].includes(name)) ? copy.failed : copy.invalid}</p>{errors.filter(name => !['unavailable', 'invalid'].includes(name)).map(name => <a key={name} href={`#${id}-${name}`}>{name === 'application' ? copy.use : copy[name as keyof typeof copy] || copy.invalid}</a>)}{errors.includes('unavailable') && fallback}</div>}
        <fieldset className="request-form-fields" disabled={busy}><legend>{copy.details}</legend>
        {material && <>
          {product.custom_size_allowed && product.custom_size && <label className="custom-toggle"><input {...attrs('custom')} type="checkbox" checked={custom} onChange={event => setCustom(event.target.checked)} />{copy.custom}{error('custom')}</label>}
          {custom && product.custom_size ? <div className="form-row">{(['width', 'length'] as const).map(axis => <label key={axis} htmlFor={`${id}-${axis}`}>{copy[axis]} ({product.custom_size!.measurement_unit})<input {...attrs(axis)} required type="number" step="any" inputMode="decimal" min={product.custom_size![`min_${axis}`] ?? undefined} max={product.custom_size![`max_${axis}`] ?? undefined} /><small>{product.custom_size![`min_${axis}`] ?? '—'} – {product.custom_size![`max_${axis}`] ?? '—'} {product.custom_size!.measurement_unit}</small>{error(axis)}</label>)}</div> : product.sizes.length > 0 && <label htmlFor={`${id}-size`}>{copy.size}<select {...attrs('size')} required value={sizeId || ''} onChange={event => onSize?.(event.target.value)}><option value="">{copy.choose}</option>{product.sizes.map(size => <option key={size.id} value={size.id}>{size.label}</option>)}</select>{error('size')}</label>}
          {product.finishes.length > 0 && <label htmlFor={`${id}-finish`}>{copy.finish}<select {...attrs('finish')} required value={finishId || ''} onChange={event => onFinish?.(event.target.value)}><option value="">{copy.choose}</option>{product.finishes.map(finish => <option key={finish.id} value={finish.id}>{finish.name}</option>)}</select>{error('finish')}</label>}
          <div className="form-row"><label htmlFor={`${id}-quantity`}>{copy.quantity}<input {...attrs('quantity')} required type="number" min="0.000001" max="999999999.999999" step="any" inputMode="decimal" /><small>{copy.decimal}</small>{error('quantity')}</label><label htmlFor={`${id}-unit`}>{copy.unit}<select {...attrs('unit')} required defaultValue={product.quantity_units[0] || ''}>{product.quantity_units.filter(unit => quantityUnits.includes(unit as typeof quantityUnits[number])).map(unit => <option key={unit} value={unit}>{unitLabel(unit, locale)}</option>)}</select>{error('unit')}</label></div><p className="data-note">{copy.noStock}</p>
        </>}
        {kind === 'application' && <div className="form-row"><label htmlFor={`${id}-projectType`}>{copy.projectType}<input {...attrs('projectType')} maxLength={160} />{error('projectType')}</label><label htmlFor={`${id}-application`}>{copy.use}<input {...attrs('application')} maxLength={160} />{error('application')}</label></div>}
        <label htmlFor={`${id}-city`}>{copy.city}<input {...attrs('city')} maxLength={160} autoComplete="address-level2" />{error('city')}</label>
        {kind !== 'material' && <label htmlFor={`${id}-dimensions`}>{copy.dimensions}<input {...attrs('dimensions')} maxLength={200} />{error('dimensions')}</label>}
        <label htmlFor={`${id}-description`}>{copy.description}<textarea {...attrs('description')} maxLength={4000} rows={4} />{error('description')}</label><p className="data-note">{copy.privacy}</p><button type="submit" className="button bronze" disabled={busy}>{busy ? copy.busy : copy.review}</button>
        </fieldset>
      </form>
    </dialog>
  </div>;
}
