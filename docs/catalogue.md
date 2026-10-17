# M3 catalogue

M3 provides an authoritative product domain and functional responsive UI. The application database is not seeded with client products or invented business claims. No final visual prototype has been implemented.

## Hierarchy, identities and schema

`product_categories` has UUID identity, explicit stable globally unique slug, nullable `parent_id`, active flag, sort order, optional M2 cover FK and timestamps. A null parent means root; a non-null parent must point to a root. The writer serializes hierarchy changes and model checks reject self-parenting, cycles, third levels and promotion of a child containing products to a root. Root deletion is blocked while it has children; child deletion is blocked while products reference it. Database self/FK constraints complement application rules. Products always reference a child, never a root.

`products` has UUID, opaque unique `LM-` reference with 12 random uppercase alphanumeric characters, explicit unique slug, child category, draft/published status, first publication timestamp, featured flag, order, optional cover FK and custom-size policy/bounds. References are immutable. Previously published slugs are immutable even after returning to draft, and names never generate URL changes. Publication time is set on first publication; scheduled publication editing is not exposed.

`product_category_translations`, `product_translations`, `product_size_translations` and `finish_translations` use unique owner/locale rows. Configured locale keys only; no fixed language columns. The configured fallback locale's name/size label is required for administrative completeness. Other entirely blank translation forms are omitted. Public business content uses **strict requested-locale eligibility**: missing product, child or parent names hide that product in that locale. Untranslated size labels and finishes are omitted. New enabled locales require configuration/UI copy and translations, not a database redesign.

Product translations include name, description, colour, origin, SEO title/description and up to 20 plain localized technical label/value pairs. Labels are bounded to 120 characters, values to 1000; no density/absorption/usage values are invented. Text renders escaped, not as trusted HTML. SEO data is stored/exposed; comprehensive SEO/launch work remains later scope.

Other M3 tables: `product_sizes`, `product_units`, `finishes`, `product_finishes`. No extra media storage model or business gallery table is needed: M2 `media_references` carries product galleries and all owner/slot usage records. Ten new tables total. There are no project, renderer, WhatsApp, order, cart, payment, customer or inventory tables.

Indexes cover slugs/references, parent/category FKs, active/order, status/publication date, featured/order, owner/locale, product/size order and unique unit/finish joins. `CatalogueWriter` is the write boundary for admin and future internal integrations; it validates full aggregates, rejects unknown fields, enforces policies, uses transactions/locks and synchronizes translations, size ownership and media references. Models are mass-assignment guarded. Do not bypass it using bulk SQL or unrestricted relationship writes.

## Standard sizes and custom-size policy

Each size belongs to one product and has UUID, translated/admin label, positive explicit width and length, optional positive height/thickness, required measurement unit, active flag, order and optional published size cover. Values use decimal(12,4), with validation from 0.0001 to 1,000,000. Measurement-unit registry is `mm`, `cm`, `m` in `config/catalogue.php`; there is no implicit unit or centimetre default. The new confirmed physical-size requirement supersedes the earlier label-only size plan. Labels such as `4` are still valid labels when explicit dimensions/unit are supplied; their terminology is not inferred.

Sizes are limited to 40 per product; editing IDs must belong to that product. Removing a size through the aggregate removes its translations/references atomically. Active means the company wants that standard option shown/selectable; it promises no live inventory or commercial availability. Zero standard sizes is allowed.

Custom policy is independent of standard sizes. `custom_size_allowed=false` requires all custom fields null; unexpected residual bounds are rejected by the backend. The admin toggle explicitly clears bounds when disabled, while validation remains authoritative. When true, explicit `custom_measurement_unit`, positive min/max width and min/max length are all required, with minimum ≤ maximum. Database checks back up null/positive/range semantics. Optional thickness bounds were not added because they are not confirmed necessary.

Public custom-size presentation is informational and non-actionable: custom dimensions can be requested, with feasibility confirmed by the company. M3 exposes authoritative bounds but collects no custom request and calculates no price or manufacturing promise. No stock quantities or public lead-time estimates exist.

## Quantity units and finishes

`product_units` explicitly assigns one or more registry codes to each product: `m2`, `m3`, `linear_m`, `piece`. These are future quotation quantity units, distinct from measured dimensions. Unsupported or duplicate assignments are rejected. No M7 quotation persistence or WhatsApp flow exists.

Commercial finishes have UUID, slug, activation/order, localized name/description and optional approved M2 representative cover. `product_finishes` is many-to-many with per-assignment active/default/order; at most one active default is allowed. Invalid/duplicate IDs and inactive default assignments are rejected. Inactive registry finishes or inactive assignments disappear publicly. Referenced finishes cannot be deleted. A commercial finish is not a renderer preset; no PBR placeholder was needed in M3.

## Media and image switching

All cover fields select approved, client-supplied, actively published M2 images by UUID. The searchable chooser exposes filenames/IDs, never raw object keys. Product galleries permit 12 images, ordered by drag/drop; size galleries were not added because optional size covers plus the shared product gallery satisfy the confirmed behaviour without duplicate relationships.

Owner covers and gallery slots are registered in `media_references` within the same transaction while locking assets. Foreign keys/reference records protect M2 deletion. Gallery/reference cleanup accompanies owner deletion; media itself is retained. Staged M2 imports remain staging metadata; administrators deliberately attach approved assets rather than automatically inventing catalogue facts from filenames.

Public DTOs contain only the active approved revision's optimized variants, URL, dimensions, format, revision/hash and localized alt. Private keys/originals/provenance internals are not serialized. A product disappears publicly if an assigned product or active-size image becomes ineligible; an ineligible category cover hides that branch. An ineligible finish/representative image removes that finish. Unpublish and immutable-cache retention continue to follow M2 semantics.

Initial detail image is product cover or a neutral text placeholder. Selecting a size chooses its dedicated cover, then product cover, then placeholder. Gallery selection can be reset to the current cover; selecting another size resets gallery selection. Finish selection never changes the image. Optimized M2 WebP variants use native responsive `srcset` directly from the media origin; image bytes are not proxied through Next. Size images are loaded when selected rather than all being preloaded.

## Publication and administration

Public product eligibility requires published status, effective publication date, active root and child, requested-locale product/category names, validated size/custom rules and eligible assigned public media. Drafts and inactive hierarchy branches are excluded by database queries, including detail URLs. Empty images are allowed and display neutral placeholders.

Filament resources:

- `/admin/product-categories`: root/child creation and editing, parent chooser, explicit slug, translations/description/SEO, cover, activation and ordering. Delete checks children/products.
- `/admin/finishes`: reusable translated finishes, optional cover, activation/order; delete checks assignments.
- `/admin/products`: child chooser, content/technical/SEO translations, measured size repeater with optional dedicated images, custom toggle/bounds, quantity units, finish assignments, product cover/gallery, featured/order/status. Nested editing preserves existing size IDs and rejects foreign-product IDs.

Active content managers can edit catalogue drafts/categories/finishes. Product publication and deletion require a super-admin. M2 usage approval still requires its existing super-admin workflow. Actions enforce authorization server-side, not just through visibility. There is no public mutation endpoint or seeded administrator.

## Public API

All endpoints are GET-only, no-store and under `/api/v1`:

| Route | Behaviour |
| --- | --- |
| `/product-categories?locale=fr` | Ordered active root/child tree with localized content, optional covers and eligible finish filter registry |
| `/products?locale=fr&parent=parent-slug&category=child-slug&finish=finish-slug&page=1&per_page=12` | Eligible product cards and pagination meta |
| `/products/{slug}?locale=ar` | Reference/content, parent/child, cover/gallery, active translated measured sizes/size covers, custom policy/bounds, quantity units, eligible finishes and technical facts |

Filters use stable slugs, combine with AND, and preserve URL semantics. Unknown slugs return an empty list. Default page size 12, allowed 1–24, page 1–10000. Disabled locales/invalid bounded input return 422; hidden or unknown details return 404. No complex search, sale filter or custom-size filter was added; neither is needed for the confirmed minimum catalogue scope. There are no prices, availability quantities, lead times, projects, renderer manifests or quotation links in these DTOs.

Reads eager-load translations, hierarchy and only active media revisions. List-query counts are tested constant as page size grows, including products with media. Pagination bounds keep product payloads limited; dimensions/size counts/gallery/technical facts are separately bounded.

## Functional frontend and boundaries

Routes are `/{locale}/products` and `/{locale}/products/{slug}`. The M1 shell links to the catalogue. Categories/filters come from the API, so adding categories requires no frontend code change. Parent/child/finish filters use GET URLs and explicit Apply, with pagination retaining filters. Server Components fetch no-store with the existing bounded backend timeout. Missing backend responses show a localized unavailable state; missing/draft details return 404.

Only gallery/size/finish controls are client components. French and Arabic RTL, mobile layouts, native keyboard controls, pressed-size states and neutral placeholders are supported. The design is intentionally plain and replaceable for the later approved visual prototype. It does not load Three.js, GLB, PBR, project/video data or WhatsApp logic.

No project implementation, full custom request form, public lead-time/stock system, commerce, final homepage/design or production content was added. Real R2/CDN connectivity and the existing clean-network Composer limitation remain as recorded in M2.
