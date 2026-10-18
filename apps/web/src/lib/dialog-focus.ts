import type { KeyboardEvent } from 'react';

export function trapDialogFocus(event: KeyboardEvent<HTMLDialogElement>) {
  if (event.key !== 'Tab') return;
  const dialog = event.currentTarget;
  const controls = Array.from(dialog.querySelectorAll<HTMLElement>('a[href],button,input,select,textarea,[tabindex]')).filter(control => control.tabIndex >= 0 && !control.matches(':disabled') && control.getClientRects().length > 0);
  const first = controls[0]; const last = controls.at(-1);
  const active = document.activeElement;
  if (!first) { event.preventDefault(); dialog.focus(); return; }
  if (!controls.includes(active as HTMLElement) || (!event.shiftKey && active === last) || (event.shiftKey && active === first)) {
    event.preventDefault();
    (event.shiftKey ? last : first)?.focus();
  }
}
