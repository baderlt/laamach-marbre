'use client';

import { siteCopy } from '@/lib/site';
import { SiteIcon } from './site-icon';

export function ContactForm({ locale, email }: { locale: string; email?: string | null }) {
  const copy = siteCopy(locale);
  return <form id="contact-form" className="contact-form" onSubmit={event => {
    event.preventDefault(); if (!email) return;
    const values = new FormData(event.currentTarget);
    const message = `${values.get('name')}\n${values.get('email')}\n${values.get('phone') || ''}\n${values.get('request') || ''}\n\n${values.get('message')}`;
    window.location.href = `mailto:${encodeURIComponent(email)}?subject=${encodeURIComponent('Contact — LAAMACH MARBRE')}&body=${encodeURIComponent(message)}`;
  }}><h2>{copy.message}</h2><p>{email ? copy.mailNote : copy.mailUnavailable}</p><fieldset disabled={!email}><div className="form-row"><label>{copy.name}<input name="name" autoComplete="name" required maxLength={160} /></label><label>{copy.requestType}<select name="request"><option>{copy.informationRequest}</option><option>{copy.quoteRequest}</option><option>{copy.otherRequest}</option></select></label><label>{copy.yourEmail}<input name="email" type="email" autoComplete="email" required maxLength={254} /></label><label>{copy.phoneOptional}<input name="phone" type="tel" autoComplete="tel" maxLength={40} /></label></div><label>{copy.message}<textarea name="message" rows={6} required maxLength={4000} /></label><button className="button bronze" type="submit">{copy.send}<SiteIcon name="mail" /></button></fieldset></form>;
}
