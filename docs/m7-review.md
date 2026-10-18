# M7 — WhatsApp quotations and project enquiries

Implemented locally on 18 October 2026. M6 and deployment remain postponed. The existing visual system and M1–M5 business/publication/media rules are preserved.

## Configuration and activation

In Filament **Website content**, edit or create the **site** record as an active super-admin. Enter the real WhatsApp international number, enable requests globally, enable the required individual flows, and publish the record. Telephone, email and the localized contact page remain the manual alternatives managed by M5.

`site_contents.data.whatsapp` contains `number`, `enabled`, `material`, `similar`, `application`. No migration, new database table or dependency is required. Number formatting permits a leading `+`, international `00` prefix, spaces, parentheses and hyphens; storage/public delivery normalizes to 8–15 digits beginning with a nonzero digit. Validation checks syntax, not ownership or WhatsApp registration.

The destination and switches are protected by a super-admin policy in the writer, including direct calls, and disabled for other admin roles in Filament. Omission during an editorial update preserves existing protected configuration under the record lock. Invalid, missing, unpublished, globally disabled or per-flow-disabled settings fail closed.

**No real destination was supplied. Neither the business database nor the restored preview has a WhatsApp number enabled.** No example number was installed into either. Fictional numbers exist only in test code and disposable QA fixtures. Localized introductions are fixed, reviewable source strings; arbitrary executable or interpolated admin templates are not supported.

## Three workflows

| Entry | Interface and message |
| --- | --- |
| A: material quotation | Authoritative product title/reference/URL; assigned standard size or explicitly permitted custom width/length; assigned finish when present; positive quantity and assigned unit; optional city and description. |
| B: similar project | Authoritative project title/reference/URL; optional intended city, approximate dimensions/area and description. Expresses inspiration without claiming authorship or identical specifications. |
| C: this material in my project | Authoritative product title/reference/URL; optional project type, intended application, city, dimensions and description. Distinct from the quantity-based material quotation. |

Standard-size selection still switches the existing M3 gallery image. An unlabeled unit is never interpreted or fabricated. Custom dimensions use the product’s configured measurement unit and inclusive bounds. Quantity units are separately restricted to assigned `m2`, `m3`, `linear_m`, `piece`, with French/Arabic labels. Products without finishes have no finish selector; fabricated or stale finish/size IDs are rejected even when lists become empty. Provisional photo records cannot bypass incomplete material specifications through the preparation endpoint.

## Transport, validation and privacy

`POST /api/requests` accepts bounded JSON only, with a 16,384-byte streaming limit and the configured site origin. It fetches fresh, existing public site and product/project APIs, verifies the flow and selections, and builds the localized message. Visitor-supplied references, canonical URLs, unknown fields, array-shaped flow values, disabled locales and unsafe slugs are rejected. Canonical URLs use configured `SITE_URL`, including when the incoming Host header is forged.

Quantities/custom dimensions must be positive decimal values below one billion with at most six decimal places, and custom dimensions must satisfy the configured product bounds. Text limits: city/project type/application 160, project dimensions 200, description 4,000 characters. Control characters, bidi overrides/isolates in visitor text, and line breaks in single-line fields are rejected. Generated direction isolates preserve reference/URL/dimension order in Arabic. Absent optional fields are omitted.

The review contains the complete editable-by-returning-to-form message. The explicit user link opens `https://wa.me/{digits}?text={encodeURIComponent(message)}` with no opener or referrer. Opening WhatsApp never means delivery. Copy confirmation only means clipboard copy. If the encoded URL exceeds **8,000 ASCII characters**, no oversized link is emitted: the full message remains available for copy and manual contact. If clipboard access fails, the complete text is focused and selected for manual copying. Nothing is truncated.

Preparation responses are `no-store`. Visitor details are not persisted, sent to Laravel, logged by the feature, put in analytics, or submitted to a messaging provider. No customer/order/quotation/enquiry records, tokens, WhatsApp authentication, automatic sends or delivery confirmations were added.

## Verification

| Check | Result |
| --- | --- |
| Full Laravel `artisan test --compact` | **171 tests / 702 assertions passed**. All 166 pre-existing backend tests retained. |
| Frontend `npm test` | **26 passed**, including all 19 pre-existing tests. |
| Chromium | **33 scenarios verified**: 24 unchanged catalogue/design/foundation/project cases passed in the full run; the 9 M7 cases passed after the RTL correction. Final strict flow-type hardening was additionally verified by unit tests and a live endpoint rejection check. The initial four new-test failures were corrected for Windows clipboard CRLF and waiting for asynchronous review; no application assertion was weakened. |
| TypeScript | `npm run typecheck` passed; final production build also passed its TypeScript stage. |
| ESLint | `npm run lint` passed, zero warnings. |
| Pint | Full `vendor/bin/pint --test` passed. |
| Composer | `composer validate --no-check-publish` passed. Strict validation reports the existing exact-version-pin warnings for Laravel/Filament; dependency constraints were preserved. |
| Production | `npm run build`, webpack, passed with the stateless request route. Generated Next type-configuration changes were restored. |
| Isolation | Application source isolation passed; user-installed skill files excluded using the existing temporary check configuration. All 255 reference-source hashes match; all 22 original client-asset hashes match. |
| Data | All 28 preview tables matched the pre-test snapshot immediately after restore. After reopening the preview, all 27 non-session tables still match exactly; normal existing media/admin browsing added five framework sessions, with no submitted request data. Those live sessions were preserved. Business database remains 3 draft products, 7 unapproved/private media assets, 14 private variants, no editorial settings and no quotation/order/customer tables. |

Browser coverage includes valid FR/AR messages, all three entry points, standard image switching, custom bounds, invalid quantity/units, unassigned size/finish, absent finishes, optional omission, forged identity/Host/origin, unpublished context, oversized payloads/messages, clipboard and manual-copy fallbacks, changed configuration, no external WhatsApp/3D requests and no fake delivery status. The restored real preview was separately checked in FR/AR: all three entry points offer contact fallback, and preparation returns 409 without an active destination.

## Accessibility and screenshots

The existing native dialogs retain Escape, focus containment/restoration, focus on review and linked error summary, explicit labels and associated errors. Controls follow the existing typography/ivory/gold palette. The obsolete fixed mobile material button was made an in-flow action to prevent overlap with footer, navigation or dialogs. Narrow screens use a scrollable bottom-aligned dialog; desktop uses the existing centered dialog.

Both languages and all three forms passed width checks at **360, 390, 430, 768, 1440px**, including no horizontal overflow, direction, labeled controls, practical non-checkbox control heights of at least 44px, keyboard operation and Chromium accessibility-tree dialog naming. Additional 360×480 checks reached the submit button without obstruction. Sampled visible dialog text contrast was at least **6.47:1**. Reduced-motion styling is preserved. This is scoped evidence toward WCAG 2.2 AA, not an accessibility certification or physical-device/screen-reader audit.

Screenshots are local QA artifacts under `.tools/m7/screenshots/` (synthetic business records, clearly separate from restored real preview). Representative files:

- `fr-material-360.png`, `ar-material-360.png`
- `fr-similar-430.png`, `ar-similar-430.png`
- `fr-application-768.png`, `ar-application-768.png`
- `fr-similar-1440-viewport.png`, `ar-similar-1440-viewport.png`
- `fr-material-review-390.png`, `ar-material-review-390.png`
- `fr-material-360-short.png`, `ar-material-360-short.png`
- `fr-missing-number-product.png`, `ar-missing-number-project.png`

Desktop/mobile captures were inspected directly. The observed Arabic dimension reversal was corrected and recaptured. Form labels, close control, copy action, wrapping and scrolling remain usable within the existing design.

## Changed files

- `apps/api/app/Editorial/{WhatsAppSettings,SiteContentWriter,PublicSite}.php`
- `apps/api/app/Policies/SiteContentPolicy.php`
- `apps/api/app/Filament/Resources/SiteContentResource.php`
- `apps/api/tests/Feature/WhatsAppSettingsTest.php`
- `apps/api/tests/Support/catalogue-browser.php`
- `apps/web/src/lib/{whatsapp,request-draft,site}.ts`
- `apps/web/src/components/{request-draft,product-options}.tsx`
- `apps/web/src/app/api/requests/route.ts`
- `apps/web/src/app/[locale]/products/[slug]/page.tsx`
- `apps/web/src/app/[locale]/projects/[slug]/page.tsx`
- `apps/web/src/app/m52.css`
- `tests/whatsapp.test.mjs`
- `tests/browser/requests.spec.ts`
- `docs/m7-review.md`

## Message examples

These are the exact preparation examples from disposable QA product/project records, not real company facts or completed work. Display-only examples below omit generated direction-control marks; runtime Arabic output includes them.

### FR — material

```text
Bonjour, je souhaite obtenir un devis pour ce matériau.

Produit : Temporary product
Référence : LM-PNT2PGUN3PXP
Lien : http://127.0.0.1:3101/fr/products/temporary-product
Dimensions du matériau : 5 × 60 cm
Finition : Temporary finish
Quantité : 2.5 m²
Ville de mon projet : Fès

Pourriez-vous me confirmer les possibilités et les conditions de cette demande ? Merci.
```

### FR — similar

```text
Bonjour, je souhaite discuter d’un projet inspiré de cet exemple.

Projet de référence : Synthetic project
Référence : LP-3KC5YVIEVU40
Lien : http://127.0.0.1:3101/fr/projects/synthetic-project
Ville de mon projet : Fès
Dimensions envisagées pour mon projet : 20 m², à confirmer

Je souhaite étudier un projet similaire, avec des caractéristiques à définir ensemble. Merci.
```

### FR — application

```text
Bonjour, je souhaite utiliser cette pierre dans mon projet.

Produit : Temporary product
Référence : LM-PNT2PGUN3PXP
Lien : http://127.0.0.1:3101/fr/products/temporary-product
Type de projet envisagé : Villa
Utilisation envisagée : Façade
Ville de mon projet : Fès

Pourriez-vous me confirmer les possibilités et les conditions de cette demande ? Merci.
```

### AR — material

```text
السلام عليكم، أود طلب عرض سعر لهذا المنتج.

المنتج : اختبار Temporary product
المرجع : LM-PNT2PGUN3PXP
الرابط : http://127.0.0.1:3101/ar/products/temporary-product
مقاس المادة : 5 × 60 cm
التشطيب : اختبار Temporary finish
الكمية : 2.5 متر مربع
مدينة مشروعي : Fès

المرجو تأكيد الإمكانيات والشروط المتاحة لهذا الطلب. شكراً.
```

### AR — similar

```text
السلام عليكم، أرغب في مناقشة مشروع مستوحى من هذا المثال.

المشروع المرجعي : اختبار Synthetic project
المرجع : LP-3KC5YVIEVU40
الرابط : http://127.0.0.1:3101/ar/projects/synthetic-project
مدينة مشروعي : Fès
الأبعاد المقترحة لمشروعي : 20 m², à confirmer

أرغب في دراسة مشروع مشابه، مع تحديد خصائصه معكم. شكراً.
```

### AR — application

```text
السلام عليكم، أود استعمال هذا الحجر في مشروعي.

المنتج : اختبار Temporary product
المرجع : LM-PNT2PGUN3PXP
الرابط : http://127.0.0.1:3101/ar/products/temporary-product
نوع المشروع المقترح : Villa
الاستخدام المقترح : Façade
مدينة مشروعي : Fès

المرجو تأكيد الإمكانيات والشروط المتاحة لهذا الطلب. شكراً.
```

## Acceptance and remaining setup

- [x] Three distinct user-initiated WhatsApp workflows and FR/AR messages.
- [x] Fresh authoritative context, assigned options, explicit custom units and bounds.
- [x] Protected global/per-flow settings and fail-closed behavior.
- [x] Review, safe encoding, full-text copy and manual contact fallbacks.
- [x] Mobile/RTL/keyboard/accessibility checks and real Chromium screenshots.
- [x] M1–M5 regressions, build/type/lint/PHP/Composer/isolation verification.
- [x] Original source/media and preview/business data preserved; no new persistence.
- [ ] Business activation: a super-admin must configure the real company number and desired switches in the published site record.
- [ ] Real WhatsApp account/device behavior and actual delivery remain untested; no real message was sent.

M7 implementation is complete locally. M6, another redesign and deployment were not started. Preview: http://127.0.0.1:3104/ar (French: /fr).
