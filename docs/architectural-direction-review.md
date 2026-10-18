# LAAMACH architectural visual direction

The client's latest two supplied screenshots replace the earlier homepage treatment as the visual direction. The separate reference repository was accessed and inspected read-only; its logo, media, copy, company details and integrations were not copied.

Preview: [French](http://127.0.0.1:3104/fr) · [Arabic](http://127.0.0.1:3104/ar).

Changes: deep blue charcoal hero, warm gold actions, editorial French headings, Cairo RTL, numbered desktop route rail, real LAAMACH material swatches, transparent desktop header becoming light on scroll, six-item discovery strip, collection-first homepage, compact collection cards, contrasting supply/project stories and coordinated cards/footer across public pages. Mobile retains readable stacked sections and accessible navigation. Existing editable LAAMACH wording and client photographs remain in use; no business records were changed.

The existing architectural AI image remains explicitly labelled as a temporary local presentation illustration. It is not a verified client building or an imported client-supplied asset. Actual product photos are preserved. Missing commercial specifications, contacts, logo and real project details still need client confirmation.

Screenshots: [French hero](../.tools/stone-direction/fr-hero.png), [Arabic hero](../.tools/stone-direction/ar-hero.png), [French scrolled header/collections](../.tools/stone-direction/fr-scrolled.png), [Arabic scrolled header/collections](../.tools/stone-direction/ar-scrolled.png), [mobile FR](../.tools/stone-direction/final/fr-home-390.png), [mobile AR](../.tools/stone-direction/final/ar-home-390.png). All eight page types in both locales were captured at 1440/390 under `.tools/stone-direction/final` (32 screenshots).

Verification: 19 frontend tests passed; ESLint, TypeScript and production build passed. Browser checks covered 16 localized routes at 320, 360, 375, 390, 430, 768, 1024, 1280 and 1440: 144 combinations, no overflow/page errors/forbidden media requests; rendered images loaded. Targeted FR/AR checks verified hero text/swatch separation, transparent-to-light fixed header state and mobile menu Escape/focus return. The first visual pass exposed an inherited RTL swatch inset; the next corrected it. The scrolled-header comparison also caught a language-link contrast issue, which was corrected.

All 255 reference baseline hashes remained unchanged. Backend, schema, media approval/security, product sizing, project relationships and local request journeys were not modified; the backend suite was not rerun for this presentation-only change. Source isolation and whitespace checks passed. No dependencies added. M6/M7 remain untouched, including the reference screenshot's WhatsApp behavior, which was not added.
