# M4 projects and project media

## Data and publication

Project categories are independent, flat, admin-managed records with UUIDs, explicit slugs, configured-locale translations, active/order, optional approved cover and SEO text. Examples are never seeded as business data.

Projects have UUIDs, opaque `LP-` plus 12 random uppercase alphanumeric references, explicit slugs, draft/published status, first-publication time, featured/order, optional year/location and optional positive area paired with an explicit configured unit (`m2` initially). Localized title, description, summary, area/dimensions display and SEO text are entered by an administrator. No facts are derived from filenames. References are immutable; slugs lock at first publication and stay locked after returning to draft.

`project_category_project` has a composite primary key and implements many-to-many assignment. Publication requires at least one active category eligible in the fallback locale. Public reads require a project translation and at least one eligible category in the requested enabled locale. Only eligible categories are returned. One inactive category leaves a project public when another remains eligible; all inactive categories hide it. Newly assigning inactive categories is rejected; existing inactive assignments can remain while another eligible category supports publication.

Multiple category filters use OR semantics through `whereHas`/EXISTS queries, avoiding duplicate pagination rows. Categories are ordered by configured order then UUID; projects by order then UUID. Featured is stored but has no additional public filter in M4.

`project_products` is an explicit confirmed-materials relationship with a composite primary key and display order. It means the client confirms a product was used. A detail response includes at most 12 eligible product cards; product detail includes at most six eligible project cards. Nested card DTOs do not recursively include details. Unpublished, untranslated, inactive-hierarchy or unsafe-media records are excluded. Referenced categories/products cannot be deleted until assignments are removed. Deleting a project removes its associations and M2 reference registrations, without deleting products or media.

All translatable content and caption validation use `config/locales.json` via the existing localization configuration. Initial languages are French and Arabic RTL. English is optional; adding an enabled locale and UI translations requires no database redesign. Missing requested-language project content is hidden rather than fabricated.

## Photos and mixed gallery

M2 retains storage, ownership, approval, publication and protected deletion. Project/category covers are approved published client-supplied images, selected by asset UUID. Mixed `project_gallery_items` contain asset UUID, image/video type, order, optional configured-locale captions and an optional approved image poster for videos. The gallery, cover and posters register owner/slot references in M2. Wrong media types, unapproved/unpublished media and raw storage keys are rejected transactionally. Public eligibility checks both references and actual gallery/cover foreign keys.

No cover produces a neutral placeholder. Posters are manually selected; no poster is fabricated. Images use existing responsive WebP/optional AVIF variants directly from the media origin.

## Video profile and dependency

`config/video.php` deliberately accepts a narrow profile:

| Property | Bound |
| --- | --- |
| Actual MIME/container | `video/mp4`, recognized MP4 `ftyp` brand; MOV/MP4 demuxer only |
| Video | Exactly one H.264 `avc1` track, `yuv420p`, no attached-picture track |
| Audio | None or one AAC mono/stereo track, at most 48 kHz |
| Other tracks | Rejected |
| Original/runtime bytes | 50 MiB each |
| Measured duration | Greater than zero, at most 60 seconds |
| Dimensions | Positive, longest side at most 1920; at most 2,073,600 pixels |
| Average frame rate | Greater than zero, at most 30 fps |
| Each subprocess | At most 45 seconds; decode uses one thread |

These are hard upper ceilings; configuration may lower them. Extensions and browser-reported MIME are insufficient: server `finfo`, signature/brand, ffprobe stream inspection and a complete FFmpeg decode all must pass. Malformed, spoofed, unsupported and oversized content fails closed. Source paths and all object keys retain M2 validation. Network protocols and external MOV data references are disabled on media inputs.

FFmpeg and ffprobe are required **on the PHP processing host**. This environment has both at `8.1.1-full_build-www.gyan.dev`; H.264 video-only and H.264/AAC synthetic files were processed, and the published video-only file was played in Chromium. Set `FFMPEG_BINARY` and `FFPROBE_BINARY` to accessible binaries, preferably absolute paths under production service accounts. Keep those system binaries maintained. Missing/failing/timed-out tools disable video processing with generic validation errors, rather than pretending codec validation succeeded. No Composer/npm dependency was added.

FFmpeg performs stream-copy remuxing only (`-c copy`): it strips global/stream metadata and chapters, selects only the validated video/audio streams and applies MP4 faststart. The runtime output is inspected and completely decoded again. There is no transcoding, automatic resizing, poster extraction, HLS or adaptive bitrate pipeline. Unsupported client video must be converted externally into this profile.

Implementation details follow the official [ffprobe inspection documentation](https://ffmpeg.org/ffprobe.html), [protocol whitelist documentation](https://ffmpeg.org/ffmpeg-protocols.html) and [MOV/MP4 format documentation](https://ffmpeg.org/ffmpeg-formats.html).

## Private and published delivery

Original uploads and remuxed drafts stay on M2 private storage. Only explicit super-admin approval of client provenance and publication can activate a runtime variant. Video variants reuse `media_assets`, `media_variants`, `media_translations` and `media_references`; there is no second media storage system or video-specific table.

Runtime keys are `videos/{asset-uuid}/{processor-version}/{sha256}.mp4`. The processor version fingerprints bounded configuration and installed FFmpeg/ffprobe versions. Changing content/profile/version creates a new object key. Publication streams files to immutable published storage with `Content-Type: video/mp4` and `Cache-Control: public, max-age=31536000, immutable`. Existing content is checksum-checked instead of overwritten. Scratch processing files are private and removed on completion/failure.

Production/staging retain M2's separate private/public R2 buckets and direct CDN URLs; normal published video never passes through Laravel or Next.js. No per-image/video short-lived presigned delivery URLs or private object keys enter public DTOs. The local/testing-only `/media/videos/...` adapter verifies an exact published database variant and supports byte ranges (206); this adapter is unavailable in hosted environments. Unpublishing removes active API references; already published URLs/cache remain available under M2's immutable retention policy.

## Administrator workflow

1. Manage usage categories in **Project Categories**: translate, activate, order and optionally assign approved cover/SEO.
2. Upload MP4 in **Media → Upload project video**. Temporary upload is authenticated, CSRF-protected, signed and throttled; it allows at most 50 MiB and a five-minute upload window. Backend validation and remuxing run before the asset becomes ready. Uploading does not approve or publish it. Client-supplied provenance must be explicitly confirmed.
3. A super-admin approves public usage, then publishes the ready revision using existing M2 controls. Originals remain authorized attachment downloads, never public previews. Videos are not embedded as admin thumbnails.
4. Create/edit **Projects**: explicit slug/reference, translations, draft/publication, featured/order, category multi-select, optional facts, approved cover, confirmed ordered products and drag-ordered photo/video gallery with captions/manual posters. No raw object-key entry exists.
5. Publish only when category/content/media eligibility is satisfied. Content managers can manage drafts; super-admin authorization remains required for publication and deletion. Referenced records/media are protected.

At deployment, PHP `upload_max_filesize` must allow 50 MiB, `post_max_size` and the web-server body limit must allow multipart overhead, and request timeouts must accommodate bounded validation/remux work. The default temporary upload disk must stay private/local on the processing host so FFmpeg can inspect its local path. Hosted R2 originals/runtime use streamed bounded local scratch copies. Do not change M2's private bucket visibility.

Importer `project_video` mappings can explicitly stage files from `client-assets/projects/...`. Dry run validates without mutation. Applying stages private media and mapping records only; it creates no project, category or materials relationship and never approves/publishes automatically.

## API and functional frontend

| Endpoint | Behaviour |
| --- | --- |
| `GET /api/v1/project-categories?locale=fr` | Active translated safe categories, optional covers |
| `GET /api/v1/projects?locale=fr&category=usage-slug` | Lightweight cover/title/reference/category cards |
| `GET /api/v1/projects?categories[]=one&categories[]=two` | OR selection, at most eight distinct slugs |
| `GET /api/v1/projects/{slug}?locale=ar` | Translated details, optional entered facts, ordered mixed gallery, approved video URL/poster and eligible materials |
| `GET /api/v1/products/{slug}` | Adds bounded eligible `projects[]` |

Pagination defaults to 12, at most 24 per page; page is bounded to 1–10000. Unsupported locales/invalid filters return 422. Ineligible details return 404. Lists contain no gallery/video URLs or bytes. Eager loading keeps list query count constant across page size, including media.

`/{locale}/projects` and `/{locale}/projects/{slug}` provide clean functional responsive UI, URL-based checkbox OR filters, pagination, localized text and RTL. Cards render cover images only. Detail initially renders cover; it mounts only the selected gallery video with native controls, `playsInline`, `preload="none"`, no autoplay and optional approved poster. Switching gallery items pauses the video before unmounting. Background document visibility pauses playback. No motion is introduced, including when reduced motion is requested. Product detail shows its related-project section only when eligible projects exist.

Stable project reference, translated title, slug (source for `/{locale}/projects/{slug}`) and ordered categories supply future M7 context. No enquiry is persisted.

**WhatsApp is not implemented yet. Final design is not implemented. 3D is not implemented. Real client project content has not been imported.** Only synthetic fixtures are used for tests, and browser fixture records/media are cleaned afterwards.

## Reproducible checks

From `apps/api`, with PHP 8.3 and the isolated configured test database: `php vendor/phpunit/phpunit/phpunit`. From the repository root: `npm test`, `npm run typecheck`, `npm run lint`, `npm run build`, `npm run check:isolation` and `npm run test:browser` with the M1/M3 loopback QA configuration.

For browser data, run `php tests/Support/catalogue-browser.php prepare --env=testing` from `apps/api`; it prepares M3 and M4 synthetic data together and refuses a populated catalogue or pending fixture manifest. Start the loopback Laravel test server and completed Next production build; run Chromium. Stop both servers, run the same helper with `cleanup --env=testing`, then run the guarded Foundation tests or full backend suite to reset retained test media/users. Do not overlap database refreshes with servers or another test process.
