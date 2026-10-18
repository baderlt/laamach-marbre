# M5 visual revision and bilingual copy review

Date: 2026-10-18. Scope stops at M5. This is a review submission, not a claim of client design approval.

Preview: [French](http://127.0.0.1:3102/fr) · [Arabic](http://127.0.0.1:3102/ar).

## Page changes and prototype comparison

All nine supplied desktop prototypes were inspected, including both product-detail alternatives; V2 guides the final detail composition. No mobile prototype was supplied. Mobile adapts the same visual system deliberately.

| Page | Final visual correction |
| --- | --- |
| Home | Larger architectural hero with dual CTAs and a material study rail; two image-led activity panels with checklists; six collection stories, six selected products, five portfolio positions, four trust points and an image-led contact band. |
| Products | Dark architectural banner, four-point strip, left desktop filter panel, upper search/sort/category controls, four-column catalogue with three visual rows and contained bottom CTA. Mobile has a filter dialog and two-column cards. |
| Product detail | Short material banner and breadcrumbs; gallery/thumbnails opposite product facts, formats, finishes, units and CTA; static cube reservation and refined technical table; related projects and five related product positions. Mobile selection details precede the viewer/table. |
| Projects | Larger hero, icon category controls retaining multi-category OR filtering, three-column architectural cards and twelve visual portfolio positions, pagination and contact band. |
| Project detail | Hero-integrated metadata; selected image/video gallery with secondary montage positions; five material positions, four related-project positions and clearly placed contact CTA. |
| Services | Hero, four-point benefit strip, six image cards, a connected five-step process band, five portfolio positions and contact CTA. Copy explains preparing a request without promising unverified capabilities. |
| About | Three-part image/story composition, four selection principles, asymmetric material montage, four portfolio positions and contact CTA. No factory, team, history or certification is invented. |
| Contact | Four contact/preparation cards, image/form split with two-column desktop fields, designed location diagram/address composition, contact band and shared footer. No fictitious location is mapped. |

Shared changes: warm ivory, charcoal and restrained bronze; thin borders, tighter reference-based card ratios, larger image presence, refined spacing, a compact responsive header and five-column footer. Self-hosted Cairo improves Arabic typography; its complete SIL OFL licence is included with the font files. French retains its existing type system.

The comparison loop is recorded in [initial differences](m5-visual-revision-differences.md) and [pass-one review](m5-visual-pass1-review.md). Pass one restored the compositions; pass two corrected hero overlap, typography, RTL cube/arrows, process icons, toolbar sorting and footer rhythm. Final pass three corrected mobile product ordering and confirmed fresh loaded-media screenshots. Layout is materially closer; it is not pixel-identical because confirmed portfolio imagery, product facts, brand assets and company contact information are still missing.

## Final screenshots

Each link opens a full-page capture from the final build. Desktop width: 1440; mobile width: 390. Every route was also checked at 430, 768 and 1024.

| Page | AR desktop | AR mobile | FR desktop | FR mobile |
| --- | --- | --- | --- | --- |
| Home | [Image](../.tools/m5-revision/pass3/ar-home-1440.png) | [Image](../.tools/m5-revision/pass3/ar-home-390.png) | [Image](../.tools/m5-revision/pass3/fr-home-1440.png) | [Image](../.tools/m5-revision/pass3/fr-home-390.png) |
| Products | [Image](../.tools/m5-revision/pass3/ar-products-1440.png) | [Image](../.tools/m5-revision/pass3/ar-products-390.png) | [Image](../.tools/m5-revision/pass3/fr-products-1440.png) | [Image](../.tools/m5-revision/pass3/fr-products-390.png) |
| Product detail | [Image](../.tools/m5-revision/pass3/ar-product-detail-1440.png) | [Image](../.tools/m5-revision/pass3/ar-product-detail-390.png) | [Image](../.tools/m5-revision/pass3/fr-product-detail-1440.png) | [Image](../.tools/m5-revision/pass3/fr-product-detail-390.png) |
| Projects | [Image](../.tools/m5-revision/pass3/ar-projects-1440.png) | [Image](../.tools/m5-revision/pass3/ar-projects-390.png) | [Image](../.tools/m5-revision/pass3/fr-projects-1440.png) | [Image](../.tools/m5-revision/pass3/fr-projects-390.png) |
| Project detail | [Image](../.tools/m5-revision/pass3/ar-project-detail-1440.png) | [Image](../.tools/m5-revision/pass3/ar-project-detail-390.png) | [Image](../.tools/m5-revision/pass3/fr-project-detail-1440.png) | [Image](../.tools/m5-revision/pass3/fr-project-detail-390.png) |
| Services | [Image](../.tools/m5-revision/pass3/ar-services-1440.png) | [Image](../.tools/m5-revision/pass3/ar-services-390.png) | [Image](../.tools/m5-revision/pass3/fr-services-1440.png) | [Image](../.tools/m5-revision/pass3/fr-services-390.png) |
| About | [Image](../.tools/m5-revision/pass3/ar-about-1440.png) | [Image](../.tools/m5-revision/pass3/ar-about-390.png) | [Image](../.tools/m5-revision/pass3/fr-about-1440.png) | [Image](../.tools/m5-revision/pass3/fr-about-390.png) |
| Contact | [Image](../.tools/m5-revision/pass3/ar-contact-1440.png) | [Image](../.tools/m5-revision/pass3/ar-contact-390.png) | [Image](../.tools/m5-revision/pass3/fr-contact-1440.png) | [Image](../.tools/m5-revision/pass3/fr-contact-390.png) |

Screenshots remain local ignored review artifacts, alongside private client inputs. Each visual pass captured 32 screenshots and checked 80 viewport/page combinations. The final pass additionally required every rendered image to load and checked mobile gallery/facts/support order. No horizontal overflow, page errors, autoplay, private-original requests, 3D assets or WhatsApp requests were observed.

## Copy and publication

[The copy inventory](m5-copy-review.md) was prepared before updating published preview content. It contains old/new FR/AR headlines and descriptions, SEO titles/descriptions, CTA text, verified facts and confirmation items for each page, seven product drafts and project-detail wording. General stone-selection guidance is distinguished from product specifications and confirmed company services.

Editable copy is stored through the existing M5 site-content translations and writers, sourced from `config/website-editorial-revision.json`. Product proposals remain marked `editorial-draft-pending-client-confirmation` in `config/product-editorial-review.json`. Public component strings cover interface labels and safe fallback states. H1 hierarchy and image alt text use known product names/editorial captions; internal CTAs retain M5 catalogue/project/contact navigation.

Only the explicitly provisional local test preview was updated. It contains all seven supplied product groups and 17 real photographs (seven covers, ten gallery images). No size-specific source evidence exists. Earlier illustrative dimensions/finishes/technical values were removed; old demo finish records are inactive. Commercial names/grouping, taxonomy, origin, colours, specifications, units, suitability, availability and pricing remain pending confirmation. The preview's internal M3-required `piece` unit is not displayed as a confirmed commercial fact.

The single separately authorized generated courtyard remains labelled as an imaginary AI illustration in local preview heroes/inspiration cards. It never replaces product photos, enters M2 or claims client-supplied provenance. Three inspiration projects explicitly disclaim completed LAAMACH work. Normal-mode AI delivery returns 404 and normal pages contain no generated-image reference or demonstration notice. The three attached quarry PNGs remain unused.

## Verification results

| Check | Result |
| --- | --- |
| Full backend | 166 passed, 663 assertions, 171.18 seconds. Includes existing M1–M4 and M5 media/editorial regressions. |
| Node tests | 16 passed, including locale extensibility, selected-size image fallback, bounded filters/origins, asset mapping, provisional-mode gating and copy-review contracts. |
| Chromium | 24 passed in 1 minute. Includes FR/AR size image switching, draft exclusion, project OR categories, selected-video playback/stopping, admin/media boundaries, responsive pages, navigation, filters, SEO and mobile product ordering. |
| Final populated preview | 16 localized routes, 80 viewport checks, 32 fresh screenshots; all rendered images loaded; zero page errors/overflow/forbidden requests. |
| ESLint / TypeScript / production build | Passed with Node 24. No new dependencies. |
| PHP syntax / Pint | Both preview scripts pass syntax and Pint. |
| Composer | Validation passed; existing warnings for deliberately pinned Laravel and Filament versions remain. Lockfiles/dependencies unchanged. |
| Preview isolation | Actual-application invocation refuses execution. Normal-mode generated image route returns 404; generated image/notice absent from normal HTML. |
| Input integrity | All 22 original file sizes and SHA-256 values unchanged. |
| Read-only reference integrity | All 255 baseline files unchanged. No reference project file was edited or generated. |
| Source isolation / diff | Isolation and whitespace checks passed. Generated Next type configuration restored; private assets, databases, local environment and screenshots excluded from the commit. |

The populated test database was backed up before regressions. Synthetic browser fixtures were removed, then all 28 preview tables were restored and row counts verified. The preview servers were restarted on 3102/8101. The actual application remains at 28 tables, three private draft products, seven private unapproved client media assets, fourteen private variants, zero public variants, zero projects/editorial records and zero users.

M1–M4 backend implementation, publication rules, category hierarchy, flexible size units, media relationships/security and project many-to-many logic are unchanged. The normal writers and authorization remain in use; no schema changes were introduced. No M6 runtime or M7 quotation flow was added.

## Items still requiring the client

- Approve the revised design and bilingual wording.
- Confirm commercial names/grouping and product facts before editorial publication in the actual application.
- Provide the final logo, phone, email, address/social links and confirmed services/capabilities.
- Supply approved real architecture/portfolio photographs and genuine project metadata. Neutral visual slots are not completed projects.
- Contact currently prepares an email through the existing M5 behavior and is disabled without a configured recipient. No server mail delivery, attachments or contextual quotation system is claimed.

Stop after M5. M6 and M7 require separate explicit approval.
