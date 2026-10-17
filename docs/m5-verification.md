# M5 verification — 2026-10-17

M5 is implemented and verified locally. Initial real media is safely staged as private media on incomplete draft products. Public launch content remains pending. No push, deployment, M6 viewer or M7 quotation integration was performed.

## 1. Commit

The final handoff supplies the exact implementation commit. Resolve it with `git log -1 --format=%H -- docs/m5-verification.md`. Approved M4 base: `a673094df9f00986c18d108dbb488ec5257fa060`.

## 2–6. Discovered folders, corrections, covers, galleries and sizes

The supplied directory is `produits`, with seven organized folders under `categoris-hjar`. All nine PNG prototypes were inspected before implementation. Their screenshots cover home, catalogue, two product-detail alternatives, projects, project detail, services, about and contact. They are desktop Arabic references; mobile layouts extend the same visual system.

Folder organization already existed before M5. M5 made no further source-folder renames. The existing normalization and visual selections are retained below. Each selected cover is `cover.jpeg`; galleries are `gallery/01.jpeg`, `02.jpeg`, etc., in the stated order. Original filenames identify the unchanged client source photos.

| Original folder | Corrected folder | Cover source | Gallery sources, in order | M5 disposition |
| --- | --- | --- | --- | --- |
| grey bnikhiran | grey-bnikhiran | IMG_3850.JPG.jpeg | None | Held: spelling |
| hajar jbal taza | hajar-jbal-taza | IMG_3853.JPG.jpeg | IMG_3851.JPG.jpeg | 2 private imports |
| noir azilal | noir-azilal | IMG_3868.JPG.jpeg | IMG_3869.JPG.jpeg | 2 private imports |
| noir bnikhiran | noir-bnikhiran | IMG_3849.JPG.jpeg | IMG_3854.JPG.jpeg; IMG_3848.JPG.jpeg | Held: spelling |
| noir khnifra | noir-khnifra | IMG_3865.JPG.jpeg | IMG_3864.JPG.jpeg | Held: spelling |
| taza biege | taza-beige | IMG_3863.JPG.jpeg | IMG_3862.JPG.jpeg; IMG_3879.JPG.jpeg; IMG_3878.JPG.jpeg | Held: grouping |
| taza grey | taza-grey | IMG_3871.JPG.jpeg | IMG_3870.JPG.jpeg; IMG_3872.JPG.jpeg | 3 private imports |

The Hajar cover shows the broad face with less unused ground than its near duplicate. Noir Azilal retains its clearer representative face, with the other camera view as a gallery image. Taza Grey retains the unobstructed broad face and its two alternate views. Detailed quality findings and the original-to-organized path trace remain in `produits/organisation-report.md` and `produits/organisation-inventory.json`.

There are **zero size-specific mappings**: no explicit physical size evidence was supplied. Pixel dimensions and visible sample proportions were not treated as product measurements. M3's size ownership, labels, explicit measurements/units, custom-size bounds, dedicated-image switching and product-cover fallback remain in force. A confirmed future mapping uses an existing `size_id`; the importer cannot invent a size.

## 7. Review files and quality findings

No additional files were moved or deleted in M5. These five previously preserved files remain under `produits/_review`:

- `hajar-jbal-taza/IMG_3852.JPG.jpeg`: near duplicate of the selected Hajar cover.
- `taza-beige/IMG_3074.PNG`: screenshot with blurred foreground material.
- `taza-beige/IMG_3403.PNG`: screenshot requiring editorial review.
- `taza-grey/IMG_3402.PNG`: screenshot requiring editorial review.
- `non-image/categoris hjar - Shortcut.lnk`: preserved without following or executing it.

The original inspection found no corrupt images, exact binary/decoded-primary duplicates or images below an 800-pixel short edge. Seventeen usable camera images and four review images were found. All **22 original files / 84,135,602 bytes** still match the stored SHA-256 and byte length. No source recompression was performed; M2 creates separate responsive variants.

Ten usable images from the four uncertain product folders are **held mappings**, not newly rejected files. They remain in their organized source folders and are excluded from staging/import. The user's explicit decision was to hold Bnikhiran/Khnifra spellings and all Taza Beige grouping for review.

## 8. Import dry-run

The reviewed, M2-compatible manifest is `config/client-product-assets.json`: seven entries with explicit product slug, role, order, SHA-256 and `client_supplied=true`. `config/client-product-assets-review.json` records the ten held mappings, review paths and excluded quarry images. Byte-identical copies of only the seven accepted files were staged privately under ignored `client-assets/products`; `client-assets/assets-map.json` matches the reviewed manifest.

Actual local dry-run: **accepted 7; skipped 0; duplicate 0; failed 0; ambiguous 0**. Ten intentionally held mappings were excluded before this clean run, rather than passed through as approved inputs. Evidence: `.tools/m5-real-dry-run.json`.

## 9. Applied media imports

Actual apply: **accepted 7; skipped 0; duplicate 0; failed 0; ambiguous 0**. Seven immutable originals, fourteen private WebP variants, seven import ledger entries and seven protected product references exist. All seven assets have client-supplied provenance and ready processing; **zero are approved, active or publicly published**. Evidence: `.tools/m5-real-apply.json`.

The M2 importer remains the media boundary. Its optional checksum/provenance/size-ID fields preserve older manifests. The new console-only `laamach:stage-client-products` workflow requires an explicit provenance assertion, a clean preflight and exactly one cover per product. Retries reuse M2 ledgers; existing product slugs return `preserved-existing` instead of overwriting editorial changes. Failed media apply creates no product records and reports that any private media already ingested can be retried.

From `apps/api`, with the project's configured PHP runtime:

```powershell
php artisan laamach:stage-client-products ../../client-assets --client-supplied
# Review the dry-run before choosing apply:
php artisan laamach:stage-client-products ../../client-assets --client-supplied --apply
```

Neither command publishes media. New drafts attach private ready images only. For an explicitly mapped existing size, its dedicated cover remains pending until its image satisfies the existing M3 approved/public media rule; no supplied size mapping required this path.

## 10–11. Business records and unguessed facts

Created exactly three local **draft** products: `hajar-jbal-taza`, `noir-azilal`, `taza-grey`. Names are the clear supplied folder labels with readable capitalization, in the configured fallback locale only. Opaque `LM-` references are generated through the existing writer. Covers and four gallery references are attached. No existing business records were overwritten.

No categories, dimensions, measurement/quantity units, finishes, descriptions, origins, technical values, stock, availability, prices, custom-size permission, featured flags, Arabic product translations or projects were invented. The four uncertain products were not created. Drafts remain invisible publicly until the administrator supplies the required facts/translations and deliberately approves/publishes media and records.

One additive migration creates `site_contents` and permits a **null category on incomplete drafts only**. A database check requires a category for published products; the writer still requires an active child under an active root and at least one explicit allowed quantity unit for publication. Non-null draft categories must still be children. Ready private client images are allowed on draft product covers/galleries; the public eligibility gate and size/category/finish media rules are unchanged. Reversing the migration refuses while uncategorized drafts exist; assign genuine categories before reversal.

Final local application schema: **28 tables**. Counts: 3 draft products, 3 product translations, 0 categories/sizes/units/finish assignments/projects/editorial records/users, 7 assets, 14 variants, 7 references and 7 imports. No viewer, quotation, cart, payment, customer or stock tables were introduced.

## 12. Pages implemented

All eight routes render through the shared editorial/media architecture in configured French and Arabic RTL:

- `/{locale}`: hero, two activities, categories, featured products/projects, usages, editorial trust section, contact CTA and footer.
- `/{locale}/products`: hero, literal name search, parent/child/finish filters, explicit alphabetical/editorial sorting, horizontal categories, premium cards, pagination and mobile filter dialog.
- `/{locale}/products/{slug}`: hierarchy breadcrumbs, responsive cover/gallery, standard sizes and image fallback, custom-size information, finishes, allowed units, technical facts when entered, related projects and a static reserved 3D container.
- `/{locale}/projects`: multiple-category OR filtering, responsive navigation and project cards.
- `/{locale}/projects/{slug}`: hero, supplied metadata, mixed photo/video gallery, confirmed materials, related projects and contact CTA placement.
- `/{locale}/services`: editorial service cards and ordered process section.
- `/{locale}/about`: story composition, values/services and related editorial sections.
- `/{locale}/contact`: configured contacts, contact composition, location placeholder/map link and email-composer form.

The contact form prepares a user-controlled email via `mailto:` when an editorial email exists. It does not persist or send messages server-side and is visibly disabled when the address is missing. CTAs navigate to contact; no contextual quote transfer or WhatsApp link is implemented.

## 13. Prototype comparison and screenshots

Production-mode Chromium ran against loopback Next/Laravel servers and a guarded **separate test database**. The test fixture temporarily used two checksum-verified Hajar camera photos for product layout only. Product names, categories, dimensions and project records were explicitly synthetic test content. Project photos/video were solid-colour test fixtures, not client projects. These fixtures were removed after QA; the application database retained its real private drafts.

Thirty-two final full-page screenshots are retained in `.tools/m5-screenshots`: all eight routes × FR/AR × 1440/390 pixels. Layout assertions also cover **430, 768 and 1024 pixels** on every route, for 80 page/width combinations. Captures scroll and decode lazy images before recording. These private/local screenshots are QA evidence, not shipped production assets.

| Prototype | Comparison and deliberate limits |
| --- | --- |
| home.png | Slim dark strip, white navigation, ivory hero, bronze actions, two activities, collection/product/project rows, usages, trust, CTA and charcoal footer. Sparse data centres the single featured card; absent content is not fabricated to fill rows. |
| products list.png | Four-column desktop catalogue with separate sidebar; mobile uses two columns, horizontal categories and a native filter sheet. Neutral hero replaces unapproved architectural photography. |
| product page v1.png / v2.png | V2 supplies the main two-column composition; hierarchy from V1 is retained. Cover/thumbnails, options, facts and reserved viewer share its visual system. No fake stock, prices, quotes, wishlist or active 3D controls. |
| projects page.png | Category controls, large photo cards, restrained borders and pagination; OR filtering still supports several categories. No real project photography was supplied. |
| project page.png | Hero, metadata, selected gallery, material cards, related projects and CTA placement. Video loads only after selection and never autoplays. |
| our service page.png | Six service positions, five ordered process positions, feature projects and CTA; pending labels/photos are clearly shown as missing editorial content. |
| about us page.png | Hero, split story imagery, values/services and featured projects. No fabricated history, totals, certifications or team claims. |
| contact us page.png | Contact cards, two-column form composition and location area; missing address/phone/email/map remain explicit empty states. |

Visual review covered every major desktop page plus home/catalogue/product/project mobile captures. Iteration corrected Arabic type scale, breadcrumb alignment, footer ordering, sparse homepage rows and lazy-image screenshot loading. No horizontal overflow remained. This is a structural/style comparison, **not a claim of pixel-identical photography or typography**: approved logo, architectural imagery and company copy are still missing, and no separate mobile prototype/font files were supplied.

Example evidence files: `ar-home-1440.png`, `ar-products-1440.png`, `fr-products-temporary-product-1440.png`, `ar-projects-1440.png`, `ar-projects-synthetic-project-1440.png`, `ar-services-1440.png`, `ar-about-1440.png`, `ar-contact-1440.png`, plus their 390-pixel counterparts and the other locale.

## 14–15. Real media and remaining placeholders

Seven genuine supplied product photos were ingested; no AI product images, stock photos or internet replacements were generated/downloaded. None are public yet because business completeness and usage approval remain unresolved. Production data currently yields honest catalogue/project empty states rather than test records.

The three supplied quarry PNGs are excluded. The user explicitly chose neutral placeholders; inspection also found generation-related metadata markers. Prototype imagery is reference-only. No photograph was extracted from prototypes for production.

Pending: site logo; approved architectural/homepage hero images; activity/category/service/about imagery; real project photography/video; verified service/process/company copy; phone, email, address, social profiles and map URL; actual categories, units and localized business facts for the draft products. These can be replaced through existing admin/media flows. The static 3D block remains reserved for M6.

## 16. Admin/editorial implementation

Filament **Website content** manages one record per key: `site`, `home`, `products`, `projects`, `services`, `about`, `contact`. Configured-locale text includes titles, eyebrow, plain body, SEO title/description, global tagline/address/footer and homepage contact CTA copy. Ordered sections cover activities, services, trust, process and story, with bounded icons/internal links and approved M2 images. Global fields manage logo, phone, email, HTTPS map link and supported HTTPS social profiles. Existing Product/Project resources retain featured controls and category/order management.

Only active authorized administrators/managers edit content; super-admin authorization is required to publish. Inputs are whitelisted and bounded, duplicate/changed page keys are rejected, text renders escaped, public output omits draft/ineligible media and raw storage keys. Transactional M2 usage references protect hero/logo/section images from unsafe deletion. Media choices expose filenames/IDs rather than object keys. No editorial facts or administrator credentials were seeded.

## 17–19. Tests, tooling and regression results

| Check | Final result |
| --- | --- |
| Full backend `vendor/phpunit/phpunit/phpunit` | **166 tests, 663 assertions; 0 failures/errors/skips**, 1:58.262; JUnit `.tools/m5-backend-final.xml` |
| Node frontend `npm test` | **13 passed**, 0 failures/skips |
| Chromium `playwright test` | **24 passed**, 1.7 minutes, 1 worker |
| Final screenshot capture after lazy-loading correction | **2 passed**, 43.9 seconds; 32 screenshots refreshed |
| Foundation after exact QA cleanup | **9 tests, 49 assertions passed**; test business/media/editorial/user counts all zero |
| Production `npm run build` | Pass; all eight page routes build, dynamic locale routing retained |
| `npm run typecheck` | Pass |
| `npm run lint` | Pass, no warnings |
| `vendor/laravel/pint/builds/pint --test --dirty` | Pass |
| `composer validate` | Valid, exit 0; existing exact Laravel/Filament pin warnings |
| `composer validate --strict` | Exit 1 for those pre-existing pin warnings; no schema/lock error; dependency constraints unchanged |
| `npm run check:isolation` | Pass; only the existing intentional forbidden-database test exception |
| `git diff --check` | Pass |
| Source integrity | 22 original files unchanged; 255 inspected reference-project hashes unchanged |

All 155 prior backend tests, 10 prior Node tests and 18 prior browser scenarios remain represented in the passing suites. Existing browser selectors were adapted to the new page structure without removing their behavioural assertions. No package/Composer dependencies were added or changed.

New coverage verifies checksum-pinned real mappings, clean preflight/apply/idempotency/editorial preservation, cover/gallery assignment, explicit size ownership and approval, missing category/units on drafts only, publication gates, private-media non-exposure, editorial authorization/validation/SEO, all routes, FR/AR direction, five viewport widths, no horizontal overflow, keyboard dialogs/focus, two-column mobile cards, filter-preserving language links, no autoplay, no external/AI/stock image requests and no 3D/GLB/WhatsApp requests. Existing actual MP4 playback/pause/range/publication tests pass.

## 20. Known limitations

- Public catalogue and project content remain empty until genuine business data and media approval/publication are completed. The three real products are deliberately drafts, not launch-ready products.
- The four held product groups need confirmation; no English requirement or hardcoded business category was introduced. Enabling another locale still requires configuration/UI copy and translations, without schema redesign.
- Contact uses the user's email composer only; no delivery/persistence claim is made. It remains disabled without the company email.
- Production media/R2 credentials, CDN cache hits/CORS and remote deployment were not accessed or verified. Existing immutable published variants/stable hashed URLs and private-original isolation are preserved and locally tested.
- Chromium desktop emulation covers the requested widths; physical devices and other browser engines were not tested. System fonts substitute for unavailable brand font files.
- Next's build emits the existing Node CommonJS/ES-module experimental warnings; the build exits successfully. Existing M1 noindex/nofollow launch protection remains.
- Original photos/prototypes, private staging bytes, test artefacts and local environment files are ignored and excluded from the source commit. They need a separate authorized private handover/backup.

## 21. M5 acceptance checklist

- [x] Inspect all supplied prototypes/products and verify read-only reference access before code.
- [x] Implement all eight pages and required M5 editorial controls using the approved visual direction.
- [x] Preserve hierarchy, sizing, project many-to-many categories, publication, authorization and M2 media security.
- [x] Validate mappings, run clean dry-run, then apply seven private client imports and create three minimum drafts.
- [x] Hold uncertain names/grouping and preserve all original/review files.
- [x] Keep missing business facts/media explicit; do not publish incomplete/ambiguous records.
- [x] Verify FR/AR RTL, five widths, screenshots and required regression/build/lint/format checks.
- [x] Keep the reference project unchanged; no AI/stock production imagery or dependency additions.
- [x] Stop after M5; no M6/M7, cart, payments, accounts or stock implementation.
- [ ] Client/editorial completion and public launch approval: pending the real inputs listed above.
