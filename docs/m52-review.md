# M5.2 — mobile-first design review

2026-10-18. Review submission; client design approval remains outstanding. Preview: [French](http://127.0.0.1:3103/fr) / [Arabic](http://127.0.0.1:3103/ar).

## Skill and design system

The installed UI UX Pro Max skill was read completely (739 lines). Architecture-focused generation, editorial typography, Next.js image guidance and focused keyboard/accessibility guidance informed [the LAAMACH design system](../design-system/laamach-marbre/MASTER.md). The first industrial-orange recommendation was rejected. The architecture result was adapted to the client's authoritative prototypes rather than adopting exaggerated typography or unrelated fonts. The CLI's hard-link persistence operation was unavailable in the sandbox; the verified client-specific synthesis was saved as a normal Markdown file. Installed skill files remain user-owned and were not changed or included in the application commit.

## Page-by-page changes

| Page | Composition and UX changes |
| --- | --- |
| Home | Mobile headline and discovery actions precede a clear architectural image. Activity panels become full image/copy stories rather than squeezed desktop splits. Six featured materials match the reference rhythm. Actual records replace repeated filler cards; larger material captions and trust copy remain legible. Desktop retains the hero, activities, collections, materials, portfolio, trust, consultation and footer sequence. |
| Catalogue | Compact phone banner, search, category rail, accessible filter dialog and two-column material cards; 320px uses one column. Real result counts and M3 category/finish/search/sort behavior remain authoritative. Desktop keeps the reference sidebar and four columns. Responsive image sizes reflect the actual card width. |
| Product detail | Large real gallery, labelled zoom modal, readable name/reference/category, native size/finish controls and local request preparation. Permitted custom bounds and actual units drive validation. Distinct material-for-project action. Phone quotation action respects safe areas; it returns to document flow while other keyboard controls have visible focus. Static future 3D/table remain below selection information on mobile. |
| Projects | Architectural photo-first portfolio, clearer category controls and larger captions. Phone uses one legible portfolio column, desktop three. Missing records do not produce nine repetitive fictional cards. |
| Project detail | Responsive main gallery and selectable photo/video choices, desktop split restored, metadata and materials retained. Prominent similar-project interface shows the identity/reference and collects optional city, dimensions text and description. No actual installation association is invented. |
| Services | Six image-led editorial topics stack naturally on phones; the five-step process becomes a readable vertical sequence and returns to a horizontal desktop band. Benefit strip, portfolio and consultation retain the reference order. |
| About | Phone story prioritizes readable narrative and a large material study. Desktop restores the three-part story, four values, asymmetric montage and four-column portfolio row. No unsupported history or manufacturing claim is introduced. |
| Contact | Readable contact/preparation cards, labelled stacked mobile form, desktop split and two-column fields, honest location diagram and consultation band. Existing email-composer behavior remains disabled without a configured recipient. |

Shared: 16px mobile body copy, self-hosted Cairo, existing French editorial typography, deeper bronze controls, visible focus, larger touch targets and language links, consistent borders and minimal shadows. The header exposes a quotation/contact path on phones. No package dependency was added.

## Before / after evidence

All nine desktop prototypes were studied, including both product-detail variants. Before images below are from the preceding committed M5 design. Mobile has no supplied prototype: the same visual system is adapted from 320/360/390 upward.

| Page | Before desktop | After desktop | Before mobile | After mobile |
| --- | --- | --- | --- | --- |
| Home | [Image](../.tools/m5-revision/pass3/ar-home-1440.png) | [Image](../.tools/m52/final/ar-home-1440.png) | [Image](../.tools/m5-revision/pass3/ar-home-390.png) | [Image](../.tools/m52/final/ar-home-390.png) |
| Products | [Image](../.tools/m5-revision/pass3/ar-products-1440.png) | [Image](../.tools/m52/final/ar-products-1440.png) | [Image](../.tools/m5-revision/pass3/ar-products-390.png) | [Image](../.tools/m52/final/ar-products-390.png) |
| Product | [Image](../.tools/m5-revision/pass3/ar-product-detail-1440.png) | [Image](../.tools/m52/final/ar-product-detail-1440.png) | [Image](../.tools/m5-revision/pass3/ar-product-detail-390.png) | [Image](../.tools/m52/final/ar-product-detail-390.png) |
| Projects | [Image](../.tools/m5-revision/pass3/ar-projects-1440.png) | [Image](../.tools/m52/final/ar-projects-1440.png) | [Image](../.tools/m5-revision/pass3/ar-projects-390.png) | [Image](../.tools/m52/final/ar-projects-390.png) |
| Project | [Image](../.tools/m5-revision/pass3/ar-project-detail-1440.png) | [Image](../.tools/m52/final/ar-project-detail-1440.png) | [Image](../.tools/m5-revision/pass3/ar-project-detail-390.png) | [Image](../.tools/m52/final/ar-project-detail-390.png) |
| Services | [Image](../.tools/m5-revision/pass3/ar-services-1440.png) | [Image](../.tools/m52/final/ar-services-1440.png) | [Image](../.tools/m5-revision/pass3/ar-services-390.png) | [Image](../.tools/m52/final/ar-services-390.png) |
| About | [Image](../.tools/m5-revision/pass3/ar-about-1440.png) | [Image](../.tools/m52/final/ar-about-1440.png) | [Image](../.tools/m5-revision/pass3/ar-about-390.png) | [Image](../.tools/m52/final/ar-about-390.png) |
| Contact | [Image](../.tools/m5-revision/pass3/ar-contact-1440.png) | [Image](../.tools/m52/final/ar-contact-1440.png) | [Image](../.tools/m5-revision/pass3/ar-contact-390.png) | [Image](../.tools/m52/final/ar-contact-390.png) |

Equivalent French screenshots use `fr-` in the final capture directory: 32 final screenshots in total. Local screenshots/client assets are intentionally excluded from the source commit. Pass one found an inherited 320px notice offset and header/contrast problems; pass two identified desktop About and gallery layout regressions. Both were corrected before final compiled-build captures. Final verification checks 16 localized routes across 320, 360, 375, 390, 430, 768, 1024, 1280 and 1440: 144 page/viewport combinations, including image loading and absence of horizontal overflow.

## Three request demonstrations

- Material quotation: [FR](../.tools/m52/fr-material-request.png) / [AR](../.tools/m52/ar-material-request.png). Existing isolated fixtures demonstrate standard sizes, custom bounds 5–40 × 10–60 cm, two finishes and actual fixture units. These are synthetic QA facts, not specifications of the seven client products.
- Similar project: [FR](../.tools/m52/fr-similar-project.png) / [AR](../.tools/m52/ar-similar-project.png). Optional city, free-text dimensions and description remain local.
- Material for a new project: [FR](../.tools/m52/fr-material-project.png) / [AR](../.tools/m52/ar-material-project.png). Separate product action, retaining material identity while collecting project context rather than presenting a duplicate quantity form.

Each interface explicitly says preparation is local and nothing is sent. A validated local summary can be edited. No request persistence, WhatsApp send, server submission, customer data collection endpoint, quotation calculation or availability promise was added. Pending client product units/formats remain pending; illustrative database requirements are not presented as confirmed facts.

## Accessibility

WCAG 2.2 AA is a target, not a claim of full conformance. Browser keyboard checks cover navigation/filter dialogs, Escape, focus return, quotation-dialog focus containment, invalid-field associations, focused error summaries, review announcements and gallery controls. Form labels and native required semantics are exposed. Native dialogs provide modal background isolation. Extended repeated-Tab checks exposed a browser-chrome focus escape and hidden-submit focus loss; explicit containment is now shared by request, image, navigation and filter dialogs, and review/edit transitions move focus to visible content. The mobile request action reserves bottom safe-area space and avoids obscuring other visible keyboard focus. Reduced-motion rules disable optional motion; video remains native, selected-only and never autoplays.

Additional scoped DOM checks cover missing image alternatives, unnamed visible buttons/links, unlabelled visible form controls and one H1 per page. Axe/Lighthouse are not installed; no automated full-WCAG or screen-reader certification is claimed. Real assistive-technology/device testing and deployment network measurements remain outstanding.

## Copy and client content

The existing professional French/Arabic M5 editorial content and SEO remain editable through the existing site-content/admin architecture; the earlier [copy inventory](m5-copy-review.md) remains the review source. M5.2 refines conversion labels and local form/error/review wording in both languages. UI strings describe actions rather than inventing company capabilities. No business record or published actual application content was changed.

Seven real supplied product groups and 17 real photographs remain in the isolated presentation preview. Names/grouping, taxonomy, origin, properties, formats, finishes, units, suitability, availability and pricing still need client confirmation. Company contact details, approved logo, actual project photographs/metadata and confirmed service capabilities are still missing. Generated architectural presentation images are labelled temporary illustrations, never presented as verified completed work or imported as client-supplied M2 assets. No real product photograph is asserted as proof of a completed project.

## Tests and preservation

- Backend: 166 tests / 663 assertions passed, 285.77 seconds.
- Frontend: 19 tests passed, including bounds/permission/unit validation and pending-data behavior.
- Chromium: all 28 cases verified. Initial run: 25 passed; two test-title encoding errors and one cold-start backend-unavailable page failed. Encoding was corrected and API readiness checked; all three targeted reruns passed. The four new FR/AR request cases passed, including local-only requests, custom validation and modal keyboard behavior.
- Final production build, TypeScript and ESLint passed. Pint passed. Composer validation passed with the existing exact-version warnings for Laravel/Filament; dependencies/lockfiles unchanged.
- Normal-mode AI image delivery returns 404. No Three.js, GLB/PBR or WhatsApp requests were observed in browser tests.
- All 22 original asset hashes and all 255 read-only reference baseline files were unchanged.
- Actual application remains at 28 tables, three private drafts, seven unapproved private media assets, fourteen private variants, zero public variants and zero projects/editorial records/users. No backend implementation/schema/security/publication rule was changed.
- The populated test database was backed up before regressions; exact synthetic fixtures were removed and all 28 preview tables restored with verified counts.

M6 retains the actual 3D runtime. M7 retains transmission, contextual WhatsApp formatting and backend quotation integration. Neither was started. No cart, payment, customer account or stock feature was added.

## Final scoped audit and mobile observations

All 16 localized pages had one H1, no missing image alt attributes, no unnamed visible buttons/links, no unlabelled visible form controls and no horizontal overflow in the scoped DOM audit. This is narrower than an axe or full WCAG audit.

Local Chromium viewport 390 × 844, production build, no CPU or network throttling, one observational navigation per route. These are local lab observations, not Lighthouse grades or field percentiles. CLS was 0 in each observed navigation window. INP was not measurable from this navigation-only sample and is **not reported as zero**.

| Page | FR observed LCP | AR observed LCP | Observed CLS |
| --- | --- | --- | --- |
| / | 1.592 s | 1.360 s | 0 |
| /products | 0.888 s | 1.388 s | 0 |
| /products/taza-beige | 1.080 s | 1.448 s | 0 |
| /projects | 0.808 s | 0.948 s | 0 |
| /projects/inspiration-facade | 0.916 s | 0.904 s | 0 |
| /services | 0.640 s | 0.820 s | 0 |
| /about | 0.620 s | 0.644 s | 0 |
| /contact | 0.516 s | 0.816 s | 0 |

The local API cold-start timeout observed in the first browser case remains an environment/performance limitation; production API deadlines were not relaxed to conceal it. Public deployment and physical mobile performance need separate measurement. Source isolation and whitespace checks passed; the installed skill bundle was excluded from the application-source audit and retained untouched.

Post-fix compiled-build keyboard verification passed 160 repeated Tab/Shift+Tab containment checks across both languages, covering request entry/review, image lightbox, navigation and filter dialogs. Pending units remained withheld; local review, Escape/focus return and mobile action geometry passed. The 28-case synthetic regression results above precede this focused follow-up; backend and catalogue selection logic were unchanged.
