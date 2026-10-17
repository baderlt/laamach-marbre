# M3 verification — 2026-10-17

M3 only: authoritative product catalogue, two-level product categories, measured sizes, size-specific images, custom-size constraints, quantity units, finishes, approved M2 media, admin, APIs and functional responsive UI. No M4+ implementation or final visual prototype.

## 1. Commit and file inventory

The final handoff provides the exact local implementation commit. It is also available with `git log -1 --format=%H -- docs/m3-verification.md`. No push or deployment was performed.

New backend areas, relative to `apps/api`:

```text
app/Catalogue/CatalogueWriter.php
app/Catalogue/PublicCatalogue.php
app/Models/CatalogueRecord.php
app/Models/CatalogueTranslation.php
app/Models/ProductCategory.php
app/Models/Product.php
app/Models/ProductSize.php
app/Models/ProductUnit.php
app/Models/Finish.php
app/Policies/CataloguePolicy.php
app/Http/Controllers/CatalogueController.php
app/Filament/Resources/CatalogueForms.php
app/Filament/Resources/CatalogueResource.php
app/Filament/Resources/ProductCategoryResource.php
app/Filament/Resources/ProductResource.php
app/Filament/Resources/FinishResource.php
app/Filament/Resources/CataloguePages/ManageCategories.php
app/Filament/Resources/CataloguePages/ManageProducts.php
app/Filament/Resources/CataloguePages/ManageFinishes.php
config/catalogue.php
database/migrations/2026_10_17_000004_create_catalogue_tables.php
tests/Feature/CatalogueTest.php
tests/Support/CatalogueFixtures.php
tests/Support/catalogue-browser.php
```

Frontend additions: `src/lib/catalogue.ts`, `src/lib/catalogue-server.ts`, `src/components/catalogue-image.tsx`, `src/components/product-options.tsx`, `src/app/[locale]/products/page.tsx`, `src/app/[locale]/products/[slug]/page.tsx`. Root tests add `tests/catalogue.test.mjs` and `tests/browser/catalogue.spec.ts`. Documentation adds `docs/catalogue.md` and this report.

Existing providers/API routes register M3. M2's published DTO now supports eager-loaded active variants; its reference model gains the asset relationship needed for catalogue reads. The M1 schema assertion explicitly includes M3 tables without relaxing database guards. The shell gains a catalogue link; CSS remains plain and responsive. No dependency versions changed.

## 2–3. Migration and schema

One additive migration adds ten tables: `product_categories`, `product_category_translations`, `products`, `product_translations`, `product_sizes`, `product_size_translations`, `product_units`, `finishes`, `finish_translations`, `product_finishes`.

M2 media assets, variants, translations and references are reused. There is no duplicate product/size gallery storage table; cover FKs and registered M2 owner/slots provide references. The resulting schema has exactly 20 M1+M2+M3 tables. Application catalogue/media/user counts remain zero after verification; browser fixtures are test-only and cleaned afterwards. No project, 3D, WhatsApp, order/cart/payment/customer tables or stock/price/lead-time columns exist.

## 4–6. Hierarchy, products and measured sizes

UUID root/child hierarchy enforced through the transactional writer, model checks and FK/self constraints. Products require children; third levels/cycles/self-parenting and unsafe category deletion are rejected. Inactivating a parent hides its entire branch publicly. Translations, SEO, cover, activation and order are admin managed.

Products have opaque immutable `LM-` references, explicit slugs locked after first publication, publication timestamp, featured/order, child category and localized content/technical facts. No price/inventory/customer fields.

Per-product sizes have positive explicit width/length, optional height/thickness, required `mm`/`cm`/`m`, translated label, active/order and optional cover. No centimetre default or invented semantic terminology. Cross-product size IDs are rejected atomically. Active means a displayed standard option, not guaranteed stock.

## 7–9. Images and custom dimensions

M2 covers/gallery/size covers are assigned by approved published asset UUID only. Initial cover → neutral placeholder; selected size cover → general product cover → placeholder. Gallery can return to the cover, size changes reset gallery selection, and finish changes leave the image unchanged. Browser tests load and decode the actual synthetic optimized files, rather than merely comparing placeholder labels. Unselected large images are not preloaded.

Custom-size false rejects unexpected bounds. True requires explicit measurement unit and positive ordered width/length min/max. Public detail exposes bounds and a neutral feasibility statement only. No custom request form, promised manufacturing, timing or custom pricing.

## 10–12. Units, finishes and media integrity

Per-product quotation units are explicitly chosen from `m2`, `m3`, `linear_m`, `piece`; unsupported/duplicate units fail validation. These are separate from size measurements. Registry changes also fail closed on public reads.

Reusable localized commercial finishes support active/order/optional media plus many-to-many assignment activation/default/order. At most one active default; inactive finishes/assignments are excluded. No renderer preset or PBR integration.

M2 reference registration and cover FKs protect deletion. Unpublishing referenced product/active-size/category images hides the affected public record/branch; private originals never reach DTOs. Catalogue media queries load only active immutable revisions and remain constant in query count as list size grows.

## 13–15. Admin, API and routes

Filament: `/admin/product-categories`, `/admin/products`, `/admin/finishes`. Forms manage translations/SEO, hierarchy, sizes/images, custom bounds, quantity units, finish assignments, covers/gallery and publication. Nested edit testing verifies size identity and persisted dimensions/units. Active content managers edit drafts/categories/finishes; product publication and deletion require super-admin authorization. Hidden fields are backed by authoritative validation. No raw object-key editing or anonymous CRUD endpoint.

GET API: `/api/v1/product-categories`, `/api/v1/products`, `/api/v1/products/{slug}`. Configured `locale`; list filters `parent`, `category`, `finish` use slugs. Pagination defaults to 12, maximum 24, page maximum 10000. Drafts, inactive hierarchy, missing requested-locale content and invalid public media are excluded. Detail includes measured sizes, dedicated image DTOs, custom policy/bounds, quantity units, finishes and actual entered technical information.

Frontend: `/{locale}/products`, `/{locale}/products/{slug}`. Server-rendered data, URL-based GET filters/pagination, limited client controls, neutral placeholders, FR/Arabic RTL and mobile layouts. No Three.js/GLB/PBR/project/video/WhatsApp imports or new runtime dependencies.

## 16–18. Verification results

| Check | Result |
| --- | --- |
| Serial full PHPUnit run | **125 tests, 388 assertions**, 0 failures/errors/skips; 23.067 s, 92 MB |
| M3 catalogue tests within full run | **30 tests, 125 assertions** |
| Existing M1/M2 tests within full run | **95 tests, 263 assertions** |
| Frontend unit tests | **8 passed**, 0 skipped |
| Production-build Chromium | **12 passed**, 0 skipped/retried; 10.6 s; includes 4 M3 FR/AR scenarios and 8 existing regressions |
| TypeScript | Passed |
| ESLint | Passed with warnings forbidden |
| Pint | Passed |
| Composer validation | `validate --strict --no-check-all` passed; manifest/lock unchanged |
| Webpack production build | Passed; catalogue/list detail routes included |
| Isolation / diff whitespace | Passed |
| Reference repository integrity | 255 captured files checked; zero changes |
| Post-QA guarded foundation reset | **9 tests, 49 assertions**, passed; synthetic catalogue/media/user rows cleared |

An overlapping development test attempt produced a transient test-schema failure. Those runs were stopped and are **not** counted as successful verification. The definitive direct PHPUnit run above completed serially before browser fixture preparation. Final QA cleanup is followed by a guarded foundation test reset; no browser session runs concurrently with database-refresh tests.

### Reproducing browser fixtures

Use PHP with GD/WebP and Node 24. From `apps/api`, with QA servers stopped and the dedicated test database ready:

```powershell
php vendor/phpunit/phpunit/phpunit
php tests/Support/catalogue-browser.php prepare --env=testing
php artisan serve --env=testing --host=127.0.0.1 --port=8101 --no-reload
```

In another terminal, set the frontend QA origins to ports 3101/8101 as in local setup, build/start the frontend on 3101, then run `npm run test:browser` at repository root. Fixtures use factories and GD-drawn solid images; no production claims or photography. The preparation script validates the test connection and effective database name before writes, requires local disks and an empty test catalogue, and records exact created IDs in ignored test storage.

After stopping both QA servers, from `apps/api`:

```powershell
php tests/Support/catalogue-browser.php cleanup --env=testing
php vendor/phpunit/phpunit/phpunit --filter=FoundationTest
```

Cleanup removes only recorded synthetic catalogue objects/files after reference checks. The guarded test refresh removes remaining fixture media/user database rows. The script is outside application commands and refuses the application database. Never run browser QA and database-refresh tests concurrently.

## 19. Schema and isolation verification

Both effective database identities and table lists were inspected. Exactly 20 M1+M2+M3 tables; no third-level categories or products on roots. All inspected catalogue/media/user counts are zero in both databases after QA cleanup. No forbidden commerce/stock/lead-time columns. No real client data was imported. Configured locale/media/test-database safeguards remain intact; R2 credentials are still blank and no external provider was contacted. QA servers are stopped after verification. Final source isolation covers 142 files, with only the existing intentional forbidden-database test fixture exception.

## 20. Remaining limitations

- This is a functional neutral UI, awaiting the approved visual prototype. Final homepage/design and comprehensive launch SEO remain later work.
- Full custom-size request/quotation, WhatsApp, projects, 3D/PBR/video, checkout/cart/payment/customer accounts, stock and public lead-time systems are absent by scope.
- Size galleries and optional sale/custom filtering were not needed; the confirmed size-cover fallback, product gallery and required filters are complete.
- Staged M2 import mappings do not automatically create/attach business facts; admins deliberately assign approved images.
- Existing M2 live-R2/CDN and clean network-only Composer limitations remain. No connectivity or clean-network installation is newly claimed; no dependency changes were needed.

## 21. M3 acceptance checklist

- [x] Two-level admin-managed parent/child hierarchy, no third level/cycles and safe deletion.
- [x] Admin-managed translated products with stable references/slugs and publication eligibility.
- [x] Explicit measured standard sizes, activation/order and cross-product isolation.
- [x] Dedicated size images and deterministic product-cover/neutral fallback.
- [x] Custom-size flag, explicit units and valid width/length bounds.
- [x] Product-specific quotation quantity units and reusable commercial finishes.
- [x] Approved immutable M2 media/reference protection; no original/private leakage.
- [x] Hierarchy/catalogue/detail APIs, required filters and bounded pagination/query counts.
- [x] Functional responsive FR/Arabic RTL catalogue/detail with tested image switching.
- [x] M1/M2 regression checks preserved; no stock or public lead-time system.
- [x] No M4+ or final design implementation. Stop after M3; M4 needs explicit approval.
