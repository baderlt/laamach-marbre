# M2 media foundation

Only media-domain functionality exists. Import targets are staged references, not product/category/project records. No client photography, stock or generated marketing images are included. Automated fixtures are programmatically drawn test images.

## Schema and state

`2026_10_17_000003_create_media_tables.php` adds five tables:

| Table | Responsibility |
| --- | --- |
| media_assets | UUID identity; immutable private original disk/key, SHA-256, content MIME, filename/source path, bytes/dimensions/nullable duration; client-supplied declaration; processing and approval state; approver/time; active published revision; import time |
| media_variants | UUID; asset FK; private/published state and disk; profile/version; format; immutable key/checksum/bytes/dimensions; candidate revision, publication revision/time; processing metadata |
| media_translations | Asset FK + unique configured locale; optional alt/caption/credit. No fixed language columns |
| media_imports | Unique source-root hash + relative source; checksum, asset FK and validated staged mapping JSON. Per-file ledger supports retries, duplicates and observability without a batch table |
| media_references | Asset FK and optional variant FK; owner type/key/slot unique. Future owners can register references without introducing their business tables now |

Processing states: `pending`, `ready`, `failed`. Approval states: `pending`, `approved`, `rejected` (reserved for later review workflows). Provenance is explicit `client_supplied`; CLI `--client-supplied` records the operator's declaration, never ownership or usage approval. New assets always start pending approval. Super-admin approval records the actor/time and requires confirmed client provenance. Publication separately requires that approval and a ready derivative revision. Originals never become public. Replacements are new assets; original identity fields cannot be edited.

Private includes originals, drafts, unapproved derivatives and any future quarantine. Rejected input is reported and not persisted. Published contains only approved derivatives; files are re-encoded through a fresh GD canvas. Model/texture/video type concepts and nullable duration are reserved, but intake rejects them until their respective validators exist.

## Storage and immutable delivery

Local disks:

- `local`: `apps/api/storage/app/private`, including `originals/{uuid}/{sha256}` and private `images/...` working copies.
- `local_published`: `apps/api/storage/app/published`, approved image variants only.
- No storage symlink and no automatic local disk serving. Both roots must be disjoint and are outside the web document root (`apps/api/public`).
- Tests use isolated fake roots; source fixtures use a per-process UUID directory.

Published keys are `images/{uuid}/{profile-version}/{sha256}.webp` (or verified opt-in `.avif`). The profile-version includes explicit version, settings/encoder/runtime fingerprint and revision fingerprint. Changed bytes, selected profiles or processing configuration produce another namespace/key. Existing bytes are checksum-checked and never overwritten. Writes are serialized by the asset row lock; SHA-256/disk-key uniqueness prevents conflicting records. Candidate private writes register rollback cleanup. A process crash can leave an unreferenced private object; no scheduled orphan deletion is introduced in M2.

`MediaLibrary::process()` returns a candidate revision; `publish()` promotes that complete set and updates the asset's active revision. Historic revisions remain immutable. Normal public URLs do not expire or need signatures. `PublishedMedia::forAsset()` exposes only active approved variants with URL, dimensions, format, profile, revision and hash, ready for future DTOs. Production URLs go directly to the configured CDN origin. No catalogue API exists.

Local `/media/images/...` resolves an exact published variant database row and validated key on `local_published`. It never serves arbitrary paths, originals, private variants or remote disks. It returns content type, ETag and `public, max-age=31536000, immutable`. This local convenience route is disabled in staging/production. `/admin/media-preview/{uuid}` serves re-encoded thumbnails only; `/admin/media-original/{uuid}` forces attachment download. Both require an active allowed administrator and return private/no-store responses.

Unpublish clears the active revision from DTOs; existing public URLs and cached copies remain available. No immediate revocation promise is made. Published rows/objects are retained and cannot be edited or deleted through ordinary admin actions, even after unpublish. Exceptional legal removal needs a separately reviewed reference check, object deletion and CDN purge procedure; no Cloudflare API coupling or purge job is included.

Deletion of private unreferenced assets uses a transaction and locks the asset. Model hooks and database restrict FKs protect staged imports/references. Referenced/private variants also cannot be deleted. Future owner writes must lock the asset and register `media_references` within their owner transaction; variant ownership is validated. Do not bypass model events with bulk SQL deletes. Private files are removed after commit; failed storage deletion requires operator retry, not rollback of already committed relational data.

## Formats, limits and processing

Input: actual JPEG, PNG or WebP. Filename extensions/browser MIME are not trusted. SVG, GIF, TIFF, AVIF input, GLB, KTX2/PBR and video are currently rejected. Original filenames are metadata, never storage keys. SHA-256 is computed for inventory and stored originals/variants.

Configured hard ceilings in `config/media.php`:

- Original: 20 MiB, at most 8192 pixels on either side and 20 million pixels total.
- Pipeline memory budget: 256 MiB, additionally constrained by PHP's effective `memory_limit`. Recommended worker PHP limit: 512 MiB. Actual usable input may be smaller than dimension ceilings on a constrained worker.
- Preflight estimate reserves source pixels × 8 + output pixels × 8 + twice the byte ceiling + 16 MiB overhead, plus current allocated PHP memory. Dimensions are checked before decoding. Decoder warnings, failed full decode and corrupt data are rejected.
- Processing is synchronous, one source and one derivative canvas at a time, with a bounded selected profile set. Do not run concurrent media processing without sizing worker RAM. No queue/job service is added.
- Inventory: 5000 entries, manifest: 1 MiB; split larger deliveries. Reject symlinks, missing/non-regular sources, relative traversal, URL/key control characters and paths outside approved roots. Keep the operator-supplied source tree read-only while importing; it is a trusted local directory, not a hostile concurrent upload mount.

Output: WebP quality 85 by default. Fresh resampling/re-encoding strips EXIF/GPS/comments and other original metadata. Original EXIF is retained privately. Orientation/crop/art direction are not inferred: provide correctly oriented sources; focal/crop manifest fields are stored for later editorial integration and do not alter M2's fit derivatives.

Run `php artisan laamach:media-capabilities` on every worker image. It performs actual WebP/AVIF encoding, MIME/dimension checks and decoding. Each generated output is also round-trip checked. AVIF is disabled in default `formats`; it may be added in `config/media.php` only after the deployed worker's check passes (quality 60). Local PHP 8.3.6/GD passed both encoders, but this proves no other host's capabilities. Changing the encoder/runtime settings changes the version fingerprint; increment `encoder_version` after an encoder library change not represented by the runtime version.

| Profile (v1) | Longest edge | Roles |
| --- | ---: | --- |
| thumb | 320 | Admin previews and standard image sets |
| card | 800 | Category/product/project covers/cards |
| gallery | 1600 | Product/project galleries and brand general |
| banner | 2560 | Hero and banner |
| social | 1200 | Explicit social preview |

Preserve aspect ratio and never upscale. Each mapped role selects two profiles (social selects one), rather than generating every size. Profiles, role mappings, quality, versions, limits and formats are configurable. No asset is automatically attached to a business entity or automatically published.

## Client intake and manifest

Expected source tree (ignored by Git):

```text
client-assets/
  assets-map.json
  brand/hero/
  brand/banners/
  brand/general/
  categories/
  products/
  projects/
```

The importer inventories only those six approved subtrees, checksums every encountered regular file and validates image content. Files without explicit mappings are reported as ambiguous/skipped; no business facts come from filenames. A manifest is optional for inventory but required for importing a mapped asset. The minimal JSON list is:

```json
[
  {
    "source": "products/future-reference/cover.jpg",
    "target_type": "product",
    "target_key": "future-reference",
    "role": "product_cover",
    "order": 1,
    "translations": {
      "fr": {"alt": "Description fournie par le client", "caption": null, "credit": null},
      "ar": {"alt": "وصف يقدمه العميل"}
    },
    "focal": {"x": 0.5, "y": 0.5},
    "crop": "contain"
  }
]
```

Required: source, target_type (`brand`, `category`, `product`, `project`), opaque target_key (letters/digits/underscore/hyphen, max 120), role and integer order (0–10000). Source tree, target type and role must agree. Optional translations accept only currently configured locale codes and alt/caption/credit; enabling another locale needs no migration. Optional focal coordinates are 0–1, crop is contain/cover. Unknown fields, duplicate source entries, unsafe paths, conflicting roles and malformed JSON are rejected. Target keys are staged references, not proof an entity exists. Missing manifest sources are reported failed.

From `apps/api`:

```powershell
# Default: validation/inventory only, no persistence or derivative files.
php artisan laamach:media-import ../../client-assets

# Explicit private import. Declaration does not approve public usage.
php artisan laamach:media-import ../../client-assets --apply --client-supplied
```

JSON output includes accepted/skipped/failed counts and source/checksum/status/reason per file. Validation failures return a nonzero exit code; valid files may have committed independently, so retries are safe. Invalid manifest structure fails before any import. No manifest means inventory plus ambiguity reports.

Repeated root/source/checksum skips existing data; source changes at the same path fail without overwriting editorial values. Supply a new filename/version for replacement content. Duplicate bytes reuse the asset and stage another source mapping; no duplicate original is created. Translations are first-created only and never overwrite subsequent admin edits. Stored mappings are preserved on replay; editorial remapping/attachment is deferred to the later business milestones. Moving a delivery to a new root creates another ledger entry but still deduplicates content.

## Filament management

`/admin/media-assets` shows safe preview, filename, MIME, dimensions, bytes, provenance, processing/approval/publication and reference counts. Inspect lists variant metadata, source/approval time, private download, staged mappings and usage. Content managers can inspect, edit configured locale text and process selected profiles. Super-admins additionally confirm provenance, approve usage, publish a selected revision, unpublish and delete eligible unreferenced private assets. Action handlers enforce policies in addition to UI visibility. No raw storage disk/key field or anonymous upload endpoint is offered. No default account is seeded.

## R2 preparation

Installed `league/flysystem-aws-s3-v3` with AWS SDK; no runtime filesystem points outside this repository. Blank R2 credentials are valid for local/tests. Set these values only in this application's private environment:

```dotenv
MEDIA_PRIVATE_DISK=r2_private
MEDIA_PUBLISHED_DISK=r2_published
R2_ENDPOINT=https://ACCOUNT_ID.r2.cloudflarestorage.com
R2_REGION=auto
R2_ACCESS_KEY_ID=
R2_SECRET_ACCESS_KEY=
R2_PRIVATE_BUCKET=laamach-marbre-private
R2_PUBLISHED_BUCKET=laamach-marbre-published
R2_PUBLISHED_URL=https://media.YOUR_DOMAIN
```

Staging/production fail closed without R2 selection, separate buckets, required credentials and clean HTTPS origins. The private bucket must have **no** public custom domain or public r2.dev access. Only the published bucket receives the public media custom domain. Configure credentials scoped to the two project/environment buckets. Do not reuse another project's credentials. Published writes set content type and immutable Cache-Control; publication never requests a public ACL on an original.

Cloudflare's [public bucket documentation](https://developers.cloudflare.com/r2/buckets/public-buckets/) describes enabling caching through a custom domain; its r2.dev endpoint is for development. Deployment must configure appropriate cache rules and image CORS if needed; application headers alone do not prove the live CDN is configured. No actual R2 credentials or connectivity were used in M2.

Later, with explicit project authorization: create separate test-prefix assets; check private anonymous GET denial, published custom-domain GET/content/MIME/checksum/Cache-Control, stable URL without signature, cache hit and correct CORS; republish changed content and verify new key plus old bytes unchanged; check unpublish retention and approved cleanup. Never test by making the private bucket public. Record results separately from filesystem fake tests.

Reserved future namespaces: `models/{uuid}/{validation-version}/{sha256}.glb` and `textures/{uuid}/{profile-version}/{sha256}.ktx2`. Full GLB/PBR validation and 3D belong to M6. Videos, their transcoding/approval policy, business attachments/APIs, CDN provisioning/purge, bulk editorial remapping, orphan lifecycle automation and final page design remain unimplemented.
