# M4 verification — 2026-10-17

M4 is complete locally. M5 has not started. Functional UI only; no final visual prototype, WhatsApp, 3D or real client project import. No push or deployment.

## 1. Commit

The final handoff supplies the exact local implementation commit. Resolve it with `git log -1 --format=%H -- docs/m4-verification.md`. The approved M3 base is `d51f2351cec7d574b6cb544b402f9d409b9ce476`.

## 2. Files and migration

29 new files and 25 existing files changed. No Composer/npm dependencies added. New backend files, relative to `apps/api`:

```text
app/Filament/Resources/CataloguePages/ManageProjectCategories.php
app/Filament/Resources/CataloguePages/ManageProjects.php
app/Filament/Resources/ProjectCategoryResource.php
app/Filament/Resources/ProjectForms.php
app/Filament/Resources/ProjectResource.php
app/Http/Controllers/ProjectController.php
app/Media/VideoLibrary.php
app/Media/VideoProcessor.php
app/Models/Project.php
app/Models/ProjectCategory.php
app/Models/ProjectGalleryItem.php
app/Projects/ProjectWriter.php
app/Projects/PublicProjects.php
config/livewire.php
config/projects.php
config/video.php
database/migrations/2026_10_17_000005_create_project_tables.php
tests/Feature/ProjectsTest.php
tests/Feature/VideoTest.php
tests/Support/ProjectFixtures.php
```

Frontend additions: `src/app/[locale]/projects/page.tsx`, `src/app/[locale]/projects/[slug]/page.tsx`, `src/components/project-cards.tsx`, `src/components/project-gallery.tsx`, `src/lib/projects.ts`. Root tests: `tests/projects.test.mjs`, `tests/browser/projects.spec.ts`. Documentation: `docs/projects.md` and this report.

Existing M2 media classes extend ingestion, processing, key validation, streaming publication, local range delivery, importer mappings and authenticated admin upload. Existing catalogue models/DTOs/writer extend confirmed-project relationships and deletion protection. Providers/routes register M4 resources and policies. Existing browser fixtures now prepare/clean M3 and M4 together; Foundation schema expectations explicitly add M4. Product detail, shell and plain responsive CSS integrate the new routes/related section. The existing M3 test no longer forbids the now-authorized `projects` DTO field; all other M3 expectations remain.

## 3. Tables

One additive migration adds exactly seven tables: `project_categories`, `project_category_translations`, `projects`, `project_translations`, `project_category_project`, `project_products`, `project_gallery_items`. M2 media tables are reused unchanged. Local application migration applied successfully; no production database was accessed.

## 4. Project category model

Flat independent usage categories with UUID, explicit slug, translations, activation, order, optional approved cover and SEO. CRUD and safe deletion run through the authorized transactional writer. No hardcoded example categories or client claims are seeded.

## 5. Project model

UUID, immutable opaque `LP-` reference, explicit stable published slug, draft/published status, retained first publication timestamp, featured/order, optional entered location/year/area with explicit unit, cover and timestamps. Configured-locale title, description, summary, area display and SEO. No invented facts.

## 6. Multiple categories

Composite-key `project_category_project`, publication requires at least one active eligible category, OR filters via EXISTS, no join duplicates. One inactive category leaves another eligible assignment public; all inactive hides the project. Requested-locale eligibility applies to both project and returned categories.

## 7. Product relationship

Composite-key `project_products`, explicitly selected confirmed products, ordered display, restrictive deletion. Public project detail includes at most 12 eligible materials; inverse product detail at most six eligible project cards. Drafts are excluded in both directions; no inferred matching or recursive detail DTOs.

## 8. Photos

Existing M2 approved published client-supplied responsive image variants, registered cover/gallery/poster references and protected deletion. No cover yields a neutral placeholder. Original/private paths never enter public DTOs.

## 9. Video architecture

Real authenticated Filament upload, content-based bounded inspection, full decode, private original, private metadata-stripped faststart MP4 runtime, separate super-admin approval/publication, immutable streamed published storage and direct CDN DTO. Global and stream metadata stripping is asserted against synthetic private metadata. No heavy transcoding or parallel media ownership system.

## 10. Supported format and limits

MP4 `video/mp4`, exactly one H.264/avc1 yuv420p track, optional one mono/stereo AAC track at most 48 kHz, no other tracks. Maximum 50 MiB, 60 seconds, 1920-pixel longest side, 2,073,600 pixels, 30 average fps; each subprocess timeout 45 seconds. Unsupported formats/codecs are rejected. Signatures, MIME, demux/codec inspection and full decode are all required; filename extension is insufficient.

## 11. FFmpeg/ffprobe status

Both available locally at `8.1.1-full_build-www.gyan.dev`. Real synthetic H.264 and AAC processing passes; Chromium plays the published H.264 fixture. Required production dependency on the PHP processing host; configurable binary paths, fail closed when unavailable. No system tool was installed or global configuration changed.

## 12. Mixed gallery

Ordered image/video associations with localized captions and optional approved manual posters. Only the selected video mounts, with native controls, `playsInline`, `preload="none"`, no autoplay. Gallery changes pause playback before unmount; hidden document pauses playback. No motion is introduced. No video player or transfer on catalogue cards.

## 13. Filament

Project Categories and Projects resources registered. Translations, SEO, activation/order, facts, categories multi-select, confirmed ordered materials, approved cover and drag-ordered mixed gallery are editable. Reference is visible in the table; writer owns creation/immutability. Media offers bounded upload plus existing provenance, processing, approval, publication, inspection/download and protected deletion. Raw storage keys are never entered.

## 14. API and filters

`GET /api/v1/project-categories`, `GET /api/v1/projects`, `GET /api/v1/projects/{slug}` and extended product detail. Enabled `locale`; single `category` or up to eight distinct `categories[]` with OR semantics; pagination default 12, maximum 24, pages 1–10000. List cards omit gallery/video URLs. No featured filter was necessary.

## 15. Frontend

`/{locale}/projects` and `/{locale}/projects/{slug}` implement functional responsive catalogue, URL-based filters, pagination, actual responsive images, optional entered facts, mixed gallery and materials links. FR and AR RTL verified at 390/1280 pixels. Locale enablement stays configuration-driven.

## 16. Product detail

Conditional localized “Projects using this material” section shows at most six eligible cover-only project cards. Draft/unsafe/untranslated projects remain hidden. Stable project reference/title/slug/ordered categories provide future M7 context without enquiry persistence.

## 17. Exact tests

| Check | Result |
| --- | --- |
| Full backend PHPUnit | **155 tests, 589 assertions**, zero errors/failures/skips; 38.812 s, 96 MiB |
| M4 ProjectsTest | 23 tests, 138 assertions, green within full suite |
| M4 VideoTest | 7 tests, 64 assertions, green within full suite |
| Frontend unit tests | **10 passed**, zero failed/cancelled/skipped |
| Full Chromium | **18 passed**, 12.2 s, no retries |

M4 tests cover transactional CRUD, translations, optional facts, references/slugs, category and product relations, OR pagination, inactive categories, bounded inverse relations, constant list queries, media roles, private-media exclusion, captions/posters, real Filament upload/create/edit/delete, MP4 validation/remux/publication, corrupted/MIME-spoofed/oversized input, duration/dimension/fps/codec/tool failures, AAC, importer mapping, immutable versions, range responses and protected deletion.

Browser tests exercise actual FR/AR image decode and video playback, filters, mobile/desktop overflow, controls, no autoplay, conservative preload, pause on selection changes, materials and related-project links, neutral cover/poster behaviour and draft 404. Request listeners assert **zero MP4 requests** on the project catalogue and initial/photo-only detail views.

## 18. Build and static checks

Production Next Webpack build, explicit TypeScript, ESLint with zero warnings, Pint `--test --dirty`, Composer `validate --strict --no-check-all`, source isolation and `git diff --check` pass. Build includes both project routes. Restored only generated `next-env.d.ts` build-path changes to its prior state. Dependencies and lockfiles remain unchanged.

## 19. M1/M2/M3 regressions and isolation

Existing backend tests: M1/M2 **95 tests / 263 assertions** and M3 **30 tests / 124 assertions**, all green in the full suite. Existing 12 Chromium tests remain green; M4 adds six. Source isolation passes with 171 source files, one intentional forbidden-database negative fixture, no external links or tracked private environments. All **255 reference-repository source hashes unchanged**; reference access remained read-only.

## 20. Schema and cleanup

Both `laamach_marbre` and `laamach_marbre_test` inspected: exactly **27 tables**, M1+M2+M3+M4 only. Both many-to-many pivots have the expected two foreign keys. Zero project/category/gallery/pivot rows, product/category/size rows, media assets/variants/references and administrator rows after verification. Browser fixtures cleaned by exact manifest IDs/keys after stopping QA servers; subsequent guarded full backend refresh leaves the test database empty. No 3D, WhatsApp enquiry, quotation, order/cart/payment/customer tables exist.

## 21. Remaining limitations

Live R2/CDN delivery and production deployment were not exercised; existing M2 private/public bucket configuration must be provisioned separately. FFmpeg/ffprobe and private local scratch/temporary upload storage must be available under the PHP service account. Production PHP/web-server upload limits must accommodate 50 MiB plus multipart overhead; request timeouts must accommodate bounded synchronous processing. No format conversion, generated posters, adaptive video delivery or promise of every browser/device codec capability. Existing immutable published URLs cannot be instantly revoked by unpublishing. Existing M2 network-only dependency/deployment verification boundaries remain unchanged.

## 22. Acceptance checklist

- [x] Admin-managed translated project categories and projects, safe deletion and publication.
- [x] Multiple categories, OR filters, duplicate-free pagination, correct inactive-category behaviour.
- [x] Explicit ordered project/material relations and bounded eligible inverse sections.
- [x] Approved M2 photos, real private video upload/validation/remux, explicit publication and immutable URLs.
- [x] Ordered photo/video gallery, manual optional posters and localized captions.
- [x] Catalogue transfers no video; detail uses selected native video controls without autoplay.
- [x] Functional FR/AR RTL and responsive routes; M1/M2/M3 regressions green.
- [x] Synthetic fixtures only, cleaned; reference repository unchanged.
- [x] Required tests/build/static/isolation/schema checks pass.
- [x] No WhatsApp, 3D, real project import, final visual design or M5 implementation.

The working implementation and production setup details are documented in `docs/projects.md`.
