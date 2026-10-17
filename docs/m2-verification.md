# M2 verification — 2026-10-17

Scope: media foundation and staged asset mapping only. No M3+ schema, catalogue/project APIs, business resources, real client content or production provider changes were introduced. The reference repository was read-only throughout.

## 1. Commit

The implementation commit is the commit introducing this report (`git log -1 --format=%H -- docs/m2-verification.md`). The final handoff supplies its exact hash. No push/deployment was performed.

## 2. Files and migration

New backend files, relative to `apps/api/`:

```text
app/Console/Commands/ImportMedia.php
app/Console/Commands/MediaCapabilities.php
app/Filament/Resources/MediaAssetResource.php
app/Filament/Resources/MediaAssetResource/ManageMediaAssets.php
app/Http/Controllers/MediaController.php
app/Media/AssetImporter.php
app/Media/ImageProcessor.php
app/Media/MediaConfiguration.php
app/Media/MediaLibrary.php
app/Media/MediaPath.php
app/Media/MediaStorage.php
app/Media/PublishedMedia.php
app/Models/MediaAsset.php
app/Models/MediaImport.php
app/Models/MediaReference.php
app/Models/MediaTranslation.php
app/Models/MediaVariant.php
app/Policies/MediaAssetPolicy.php
config/media.php
database/migrations/2026_10_17_000003_create_media_tables.php
resources/views/admin/media-details.blade.php
tests/Feature/MediaTest.php
```

New docs: `docs/media-foundation.md` and this report. Existing provider/routes/filesystem/environment/composer files wire the M2 features. Existing foundation schema/storage assertions reflect M2 without relaxing database identity guards. Test storage is process-isolated, test PHP has an explicit 512 MB limit, and browser regression adds anonymous media-boundary coverage. README and local setup point to M2 documentation. No frontend product functionality was added.

## 3. Database tables

Added `media_assets`, `media_variants`, `media_translations`, `media_imports`, `media_references`. The per-source import ledger and reference registry are needed for idempotent staged mappings and FK-backed deletion protection; no batch table was added.

Both local databases were checked: exactly `cache`, `cache_locks`, the five media tables, `migrations`, `sessions`, `users`. Application users/media records: zero. No product/category/project business tables exist. Only the additive M2 migration was applied; no existing application data was deleted.

## 4. State model

Private originals are immutable. Processing is pending/ready/failed; client-supplied declaration and pending/approved/rejected approval are separate. Only a super-admin can approve public usage and publish a ready revision. Public immutable variants have a publication revision/time; an asset selects an active revision. Unpublish clears active DTO references while preserving public/history/cache semantics. Published objects and referenced/imported assets are protected from ordinary deletion.

## 5–6. Formats, limits and profiles

JPEG/PNG/WebP inputs validated by content and full decoding. WebP output verified; AVIF encode/decode also passed on PHP 8.3.6/GD here, but default output remains WebP. AVIF requires explicit worker verification and opt-in. Ceiling: 20 MiB, 8192 per side, 20 MP; preflight memory budget 256 MiB bounded by PHP memory. Recommended PHP worker limit 512 MiB.

Versioned longest-edge profiles: thumb 320, card 800, gallery 1600, banner 2560, social 1200. Preserve aspect ratio/no upscale; mapped roles normally select two profiles. Profile settings and runtime fingerprint contribute to immutable key versions. Originals retain metadata privately; public variants strip it through re-encoding.

## 7–8. Local storage and R2

Disjoint non-served private/published local disks, no public symlink. Exact local public-variant route supports stable paths, ETag and one-year immutable cache headers. Production DTOs use direct media-domain URLs; local proxy route is disabled in hosted environments. Authorized private preview/download routes are no-store.

S3 adapter 3.35.3 and AWS SDK 3.401.2 installed; five necessary dependencies added, **zero existing locked versions/dist references changed**. R2 endpoint, auto region, keys, separate private/published buckets and HTTPS media origin are configured and validated. Local/test credentials are blank; no real R2 connection, bucket, CDN or credentials were used. Configuration, local equivalents and fake R2 publication were tested. See [provider verification procedure](media-foundation.md#r2-preparation) and [Cloudflare public-bucket documentation](https://developers.cloudflare.com/r2/buckets/public-buckets/).

## 9. Import usage

From `apps/api`:

```powershell
php artisan laamach:media-import ../../client-assets
php artisan laamach:media-import ../../client-assets --apply --client-supplied
php artisan laamach:media-capabilities
```

Default dry-run, explicit apply, checksums, validated optional manifest, approved recursive roots, ambiguity reports, duplicate/replay detection, per-file summary and preserved editorial text/mappings. Future target references are staged only. Programmatic local dry-runs on **both databases** each reported accepted 1/skipped 0/failed 0 with identical before/after media counts and disk inventories.

## 10–11. Administration and security

Media index/inspect/text/provenance/process/approve/publish/unpublish/protected delete implemented. Active content managers can inspect/edit text/process; super-admin approval/publication/deletion gates are enforced in action handlers. No raw disk/object-key editing or anonymous application upload route.

Coverage includes signature/MIME spoofing, corruption, dimension/pixel/byte/memory limits, forged bomb dimensions, traversal, unsafe manifest fields, unknown locales, duplicate/replay, checksum/key immutability, private/public disk confusion, private preview authorization, metadata removal, rollback cleanup, references and deletion. Windows junctions are rejected via canonical path checks because this PHP runtime reports `is_link=false` for junctions. The junction regression fixture passed; no symlink test was skipped.

## 12–14. Exact verification results

| Check | Result |
| --- | --- |
| Full backend: `php artisan test --log-junit=../../.tools/m2-backend-final.xml` | **95 passed, 263 assertions, 0 skipped**, final duration 29.26 s |
| M2 MediaTest subset in that run | **56 tests, 184 assertions** |
| M1 existing unit/configuration/foundation tests in that run | **39 tests, 79 assertions** |
| Frontend `npm test` | **5 passed**, 0 skipped |
| `npm run typecheck` | Passed |
| `npm run lint` | Passed, warnings forbidden |
| `php vendor/bin/pint --test` | Passed |
| `composer validate --strict --no-check-all` | Passed (preserves the M1 exact-version style policy) |
| `composer audit --locked --format=summary` | No security vulnerability advisories found |
| `npm run build` | Webpack production build passed |
| Chromium production-build regression | **8 passed**, 0 skipped/flaky/unexpected, 0 retries; 18.9 s |
| `npm run check:isolation` / `git diff --check` | Passed |
| Reference integrity | **255 source/config/environment files checked, 0 changed** |
| Effective database identities/schema | Both own databases verified; exactly M1+M2 tables |
| Private storage/local published paths/cache headers | Passed in generated-fixture tests |
| Real local default dry-run without credentials | Passed, zero DB/storage mutations in both environments |
| Runtime class paths | Media asset and S3 adapter resolved inside this repository |

Selected Node 24.19 and the existing ignored project PHP INI copy; global runtimes/configuration were not changed. The local PHP copy now uses the documented media worker memory limit. QA ports 3101/8101 were stopped after browser verification. No full-suite tests were run concurrently with browser QA against the test database.

## 15. Clean network Composer reproducibility

The M1 limitation is preserved. A fresh lockfile-based install was attempted in `.tools/m2-network-install` with an empty separate cache, `--prefer-dist --no-scripts`, without changing any package versions. GitHub API archives repeatedly failed authentication; the unsuccessful attempt was stopped instead of continuing fallback downloads for every package. The initial adapter install also hit a 300-second Git source timeout. A clean network-only install is **not verified**.

For the five newly required adapter dependencies, official GitHub codeload archives at the exact locked commit references downloaded successfully into this project's own cache; ordinary Composer installation from that cache passed. Existing dependencies retain M1's documented cache provenance. This is not a runtime dependency on the reference project, and does not establish a successful clean all-package network install.

## 16. Remaining limits

- No real R2/CDN/bucket/CORS/connectivity or deployed worker verification; authorized provider checks are documented for later.
- Source intake is a trusted local read-only delivery tree during an import. It is not designed for hostile concurrent filesystem replacement.
- No crash-orphan sweeper, exceptional public purge workflow or immediate cached-copy revocation. Private writes have transaction rollback cleanup; a process crash can still require operator cleanup. Published retention is intentional.
- Cropping/focal metadata is staged; M2 fits images and does not infer orientation/art direction. Use correctly oriented client sources.
- Full GLB/PBR/3D/video handling, business attachment records/APIs, final photography/pages and deployment remain later scope.
- Clean network-only Composer install remains unverified due external download failures. Existing Windows Webpack/Turbopack limitation from M1 remains documented.

## 17. M2 acceptance checklist

- [x] Media assets/variants/translations and required ledger/reference relationships.
- [x] Content-based validation, bounded memory/dimensions/bytes and metadata-free responsive derivatives.
- [x] Real WebP encoding; AVIF gated by real round-trip verification and disabled by default.
- [x] Private/published separation, immutable stable versioned keys and one-year caching.
- [x] Safe dry-run/idempotent apply, explicit mapping/provenance, no editorial overwrite.
- [x] Ready for later real client source trees without inventing business records or requiring content now.
- [x] Local/tests need no R2 credentials; S3 integration and fail-closed hosted configuration prepared.
- [x] Safe Filament media administration and reference-protected deletion.
- [x] M1 tests/isolation preserved; full backend/frontend/browser/build checks pass.
- [x] No M3+ implementation. Stop at M2; M3 requires explicit approval.
