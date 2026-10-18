'use client';

import { trapDialogFocus } from '@/lib/dialog-focus';

import { useEffect, useId, useRef, useState } from 'react';
import type { Product } from '@/lib/catalogue';
import { validateMaterialDraft, type DraftFields } from '@/lib/request-draft';
import { siteCopy } from '@/lib/site';

const messages = {
  fr: { material: 'Préparer un devis matière', similar: 'Je souhaite un projet similaire', application: 'Cette matière pour mon projet', note: 'Préparation locale uniquement : aucune demande ne sera envoyée.', size: 'Format standard', custom: 'Format personnalisé', width: 'Largeur', length: 'Longueur', finish: 'Finition', quantity: 'Quantité', unit: 'Unité', city: 'Ville (facultatif)', dimensions: 'Dimensions du projet (facultatif)', description: 'Décrivez votre projet (facultatif)', review: 'Vérifier mon récapitulatif', ready: 'Récapitulatif local — non envoyé', edit: 'Modifier mes choix', choose: 'Choisir', pending: 'Les formats, finitions et unités restent à confirmer avec LAAMACH.', invalid: 'Vérifiez les champs indiqués avant de continuer.', fieldError: 'Choisissez une valeur autorisée et respectez les limites affichées.', details: 'Votre sélection', standard: 'Standard' },
  ar: { material: 'تحضير طلب سعر للمادة', similar: 'أريد مشروعاً مشابهاً', application: 'هذه المادة لمشروعي', note: 'تحضير محلي فقط؛ لن يتم إرسال أي طلب.', size: 'المقاس القياسي', custom: 'مقاس مخصص', width: 'العرض', length: 'الطول', finish: 'التشطيب', quantity: 'الكمية', unit: 'الوحدة', city: 'المدينة (اختياري)', dimensions: 'أبعاد المشروع (اختياري)', description: 'صف مشروعك (اختياري)', review: 'مراجعة ملخص اختياراتي', ready: 'ملخص محلي — لم يُرسل', edit: 'تعديل الاختيارات', choose: 'اختر', pending: 'تحتاج المقاسات والتشطيبات والوحدات إلى التأكيد مع LAAMACH.', invalid: 'راجع الحقول المشار إليها قبل المتابعة.', fieldError: 'اختر قيمة مسموحًا بها والتزم بالحدود المعروضة.', details: 'اختياراتك', standard: 'قياسي' },
};

export function RequestDraft({ locale, kind, identity, product, pending = false, sizeId, finishId, onSize, onFinish }: { locale: string; kind: 'material' | 'similar' | 'application'; identity: { title: string; reference: string }; product?: Product; pending?: boolean; sizeId?: string | null; finishId?: string; onSize?: (value: string) => void; onFinish?: (value: string) => void }) {
  const copy = messages[locale as keyof typeof messages] || messages.fr;
  const dialog = useRef<HTMLDialogElement>(null); const summary = useRef<HTMLDivElement>(null);
  const id = useId(); const [errors, setErrors] = useState<string[]>([]); const [custom, setCustom] = useState(false); const [review, setReview] = useState<Record<string, string> | null>(null);
  const reviewPanel = useRef<HTMLDivElement>(null);
  useEffect(() => {
    if (!dialog.current?.open) return;
    if (review) reviewPanel.current?.focus();
    else dialog.current.querySelector<HTMLElement>('form input:not(:disabled),form select:not(:disabled),form textarea:not(:disabled)')?.focus();
  }, [review]);
  const material = kind === 'material' && product;
  const error = (name: string) => errors.includes(name) ? <span className="field-error" id={`${id}-${name}-error`}>{copy.fieldError}</span> : null;
  const attrs = (name: string) => ({ id: `${id}-${name}`, name, 'aria-invalid': errors.includes(name) || undefined, 'aria-describedby': errors.includes(name) ? `${id}-${name}-error` : undefined });
  return <div className={`request-entry request-${kind}`}><button type="button" className={`button ${kind === 'material' || kind === 'similar' ? 'bronze' : 'outline'}`} onClick={() => dialog.current?.showModal()}>{copy[kind]}</button>
    <dialog onKeyDown={trapDialogFocus} ref={dialog} className="request-dialog" aria-labelledby={`${id}-title`} onClose={() => { setReview(null); setErrors([]); }}>
      <div className="drawer-heading"><h2 id={`${id}-title`}>{copy[kind]}</h2><button type="button" className="icon-button" aria-label={siteCopy(locale).close} onClick={() => dialog.current?.close()}>×</button></div>
      <p className="request-identity"><strong>{identity.title}</strong><bdi>{identity.reference}</bdi></p><p className="request-disclosure">{copy.note}</p>
      <div ref={reviewPanel} tabIndex={-1} hidden={!review} className="request-review" role="status"><h3>{copy.ready}</h3><dl>{review && Object.entries(review).filter(([, value]) => value).map(([key, value]) => <div key={key}><dt>{key}</dt><dd>{value}</dd></div>)}</dl><button type="button" className="button outline" onClick={() => setReview(null)}>{copy.edit}</button></div>
      <form hidden={!!review} noValidate onSubmit={event => {
        event.preventDefault(); const data = new FormData(event.currentTarget); const get = (name: string) => String(data.get(name) || '');
        const fields: DraftFields = { size: get('size'), custom, width: get('width'), length: get('length'), finish: get('finish'), quantity: get('quantity'), unit: get('unit'), city: get('city'), dimensions: get('dimensions'), description: get('description') };
        const invalid = material ? validateMaterialDraft(product, fields, pending) : ['city', 'dimensions', 'description'].filter(key => get(key).length > ({ city: 160, dimensions: 200, description: 4000 }[key] || 0));
        setErrors(invalid); if (invalid.length) { requestAnimationFrame(() => summary.current?.focus()); return; }
        const selection: Record<string, string> = { [copy.details]: `${identity.title} · ${identity.reference}` };
        if (material) { selection[copy.size] = custom ? `${fields.width} × ${fields.length} ${product.custom_size?.measurement_unit || ''}` : product.sizes.find(size => size.id === fields.size)?.label || copy.pending; selection[copy.finish] = product.finishes.find(finish => finish.id === fields.finish)?.name || ''; if (!pending) selection[copy.quantity] = `${fields.quantity} ${fields.unit}`; }
        selection[copy.city] = fields.city; selection[copy.dimensions] = fields.dimensions; selection[copy.description] = fields.description; setReview(selection);
      }}>
        {!!errors.length && <div ref={summary} tabIndex={-1} role="alert" className="error-summary"><p>{copy.invalid}</p>{errors.map(name => <a key={name} href={`#${id}-${name}`}>{copy[name as keyof typeof copy] || copy.invalid}</a>)}</div>}
        {material && <fieldset><legend>{copy.details}</legend>{pending && <p>{copy.pending}</p>}
          {product.custom_size_allowed && product.custom_size && <label className="custom-toggle"><input type="checkbox" checked={custom} onChange={event => setCustom(event.target.checked)} />{copy.custom}</label>}
          {custom && product.custom_size ? <div className="form-row">{(['width', 'length'] as const).map(axis => <label key={axis} htmlFor={`${id}-${axis}`}>{copy[axis]} ({product.custom_size!.measurement_unit})<input {...attrs(axis)} required type="number" step="any" min={product.custom_size![`min_${axis}`] ?? undefined} max={product.custom_size![`max_${axis}`] ?? undefined} /><small>{product.custom_size![`min_${axis}`] ?? '—'} – {product.custom_size![`max_${axis}`] ?? '—'}</small>{error(axis)}</label>)}</div> : product.sizes.length > 0 && <label htmlFor={`${id}-size`}>{copy.size}<select {...attrs('size')} required value={sizeId || ''} onChange={event => onSize?.(event.target.value)}><option value="">{copy.choose}</option>{product.sizes.map(size => <option key={size.id} value={size.id}>{size.label}</option>)}</select>{error('size')}</label>}
          {product.finishes.length > 0 && <label htmlFor={`${id}-finish`}>{copy.finish}<select {...attrs('finish')} required value={finishId || ''} onChange={event => onFinish?.(event.target.value)}><option value="">{copy.choose}</option>{product.finishes.map(finish => <option key={finish.id} value={finish.id}>{finish.name}</option>)}</select>{error('finish')}</label>}
          {!pending && <div className="form-row"><label htmlFor={`${id}-quantity`}>{copy.quantity}<input {...attrs('quantity')} required type="number" min="0" step="any" inputMode="decimal" />{error('quantity')}</label><label htmlFor={`${id}-unit`}>{copy.unit}<select {...attrs('unit')} required defaultValue={product.quantity_units[0] || ''}>{product.quantity_units.map(unit => <option key={unit}>{unit}</option>)}</select>{error('unit')}</label></div>}
        </fieldset>}
        {kind !== 'material' && <div className="form-row"><label htmlFor={`${id}-city`}>{copy.city}<input {...attrs('city')} maxLength={160} autoComplete="address-level2" />{error('city')}</label><label htmlFor={`${id}-dimensions`}>{copy.dimensions}<input {...attrs('dimensions')} maxLength={200} />{error('dimensions')}</label></div>}
        <label htmlFor={`${id}-description`}>{copy.description}<textarea {...attrs('description')} maxLength={4000} rows={4} />{error('description')}</label><button type="submit" className="button bronze">{copy.review}</button>
      </form>
    </dialog>
  </div>;
}
