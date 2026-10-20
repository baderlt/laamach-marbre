# M8 verification — PARTIAL; production NO-GO

Scope: local M8 only, on `codex/m8-readiness`. The prior authorized local work is preserved separately in baseline commit `bdd0c5f`. This report belongs to the subsequent M8 commit; obtain its full hash with `git log -1 --format=%H`. No M9 deployment, production indexing, production content publication, R2 connection, DNS or VPS change was performed. Overall website visual approval remains pending.

## Changes and preserved behavior

- Publication-aware SEO endpoint, fresh localized sitemap, canonical/description/OG/Twitter metadata, eligible hreflang and escaped, nonce-bearing JSON-LD. Organization uses supplied identity/contact facts; Product has actual name/reference/images/description, with no invented offers, ratings, availability or certifications. Projects use CreativeWork; detail pages include breadcrumbs.
- Launch-time robots/noindex policy: indexing requires **both** `SITE_ENV=production` and exact `SITE_INDEXING_ENABLED=true`. It remains disabled. API/admin remain noindex. Sitemap reads eligible published content freshly and fails closed on API failure; no unreliable `lastmod` dates or stale persistent sitemap cache. It excludes drafts, missing locales, inactive categories/ancestors and ineligible media using the existing public queries. A 10,000-record cap per kind/locale fails closed rather than silently truncating.
- Exact origin CORS, explicit IP/CIDR trusted proxies, Host validation, request-body bounds, security headers and bounded request/search budgets. Public/admin CSP candidates are **report-only**, not asserted as enforced production protection. No broad script `unsafe-inline` or production `unsafe-eval` was added. GLTF embedded textures require `connect-src blob:`; browser tests verify the uploaded textured GLB has no CSP violations.
- Homepage site/navigation requests reduced from four to one; catalogue requests are deduplicated per server render without a persistent cache. A real-logo 320-pixel WebP variant removes unnecessary logo transfer. Existing responsive stone/product variants and lazy galleries remain intact.
- Stone background updates cached layer transforms directly, preserving the same parallax/reveal calculations and visual assets. This avoids per-frame inherited root CSS-variable updates and associated paint work. Unsupported mask support selects the static fallback.
- Project lightbox video mounts only while open and unmounts on close. The existing material request form also remains available for products with optional/absent finish or size axes; validation continues to use actual product configuration. Backend-unavailable detail pages retain the skip-link target.
- Accessibility fixes add the floating-contact landmark, distinguish navigation landmarks and remove redundant decorative category-image alt text. No approved design, product URLs, translations, physical stone colors, CMS workflow or business data was replaced.

Changed files are available with `git show --stat --oneline HEAD` and `git diff bdd0c5f HEAD --name-only`. Changes are confined to API/public eligibility and security configuration, Next SEO/request/background/media components, their tests, the derived logo and these M8 documents. No dependency or schema migration was added. Generated QA build paths are excluded from the commit.


<details>
<summary>65 changed files</summary>

- apps/api/.env.example
- apps/api/app/Catalogue/PublicCatalogue.php
- apps/api/app/Editorial/PublicSite.php
- apps/api/app/Http/Controllers/CatalogueController.php
- apps/api/app/Http/Controllers/MediaController.php
- apps/api/app/Http/Controllers/ProjectController.php
- apps/api/app/Http/Controllers/SeoController.php
- apps/api/app/Http/Controllers/SiteController.php
- apps/api/app/Http/Middleware/SecurityHeaders.php
- apps/api/app/Http/Middleware/TrustedProxies.php
- apps/api/app/Projects/PublicProjects.php
- apps/api/app/Providers/AppServiceProvider.php
- apps/api/app/Support/SecurityConfiguration.php
- apps/api/bootstrap/app.php
- apps/api/config/cors.php
- apps/api/config/security.php
- apps/api/routes/api.php
- apps/api/tests/Feature/EditorialSectionsTest.php
- apps/api/tests/Feature/ProductViewerTest.php
- apps/api/tests/Feature/ReadinessTest.php
- apps/api/tests/Support/catalogue-browser.php
- apps/web/.env.example
- apps/web/next.config.ts
- apps/web/public/brand/laamach-final-logo-320.webp
- apps/web/src/app/[locale]/layout.tsx
- apps/web/src/app/[locale]/page.tsx
- apps/web/src/app/[locale]/products/[slug]/page.tsx
- apps/web/src/app/[locale]/projects/[slug]/page.tsx
- apps/web/src/app/api/requests/route.ts
- apps/web/src/app/api/search/route.ts
- apps/web/src/app/layout.tsx
- apps/web/src/app/robots.ts
- apps/web/src/app/sitemap.ts
- apps/web/src/components/catalogue-filters.tsx
- apps/web/src/components/catalogue-image.tsx
- apps/web/src/components/floating-whatsapp.tsx
- apps/web/src/components/product-options.tsx
- apps/web/src/components/project-gallery.tsx
- apps/web/src/components/site-footer.tsx
- apps/web/src/components/site-header.tsx
- apps/web/src/components/stone-background.tsx
- apps/web/src/components/structured-data.tsx
- apps/web/src/lib/catalogue-server.ts
- apps/web/src/lib/catalogue.ts
- apps/web/src/lib/environment.ts
- apps/web/src/lib/http-security.ts
- apps/web/src/lib/projects.ts
- apps/web/src/lib/seo.ts
- apps/web/src/lib/site-server.ts
- apps/web/src/lib/site.ts
- apps/web/src/proxy.ts
- docs/m8-operations.md
- docs/m8-performance-results.json
- docs/m8-verification.md
- tests/browser/catalogue-final.spec.ts
- tests/browser/client-projects.spec.ts
- tests/browser/design.spec.ts
- tests/browser/dimension-studio.spec.ts
- tests/browser/foundation.spec.ts
- tests/browser/projects.spec.ts
- tests/browser/readiness.spec.ts
- tests/browser/requests.spec.ts
- tests/browser/stone-background.spec.ts
- tests/browser/viewer.spec.ts
- tests/readiness.test.mjs

</details>

## Measured mobile performance

The machine-readable companion [m8-performance-results.json](m8-performance-results.json) contains all 24 baseline/final samples, medians, variation, transferred bytes and diagnostics. Historical 83/84 scores are background context, not the baseline used for improvement claims.

Environment: Windows, Node 24.19.0, Next 16.4.0 production build, PHP 8.3.6 development HTTP server, isolated MySQL 8.3.0 on loopback 3308, Lighthouse 13.5.0, headless Chromium 156.0.8078.4. Lighthouse's Android/Moto user-agent string is emulated, not a physical device. Viewport 390×844, DPR 1, simulated RTT 150 ms, throughput 1,638.4 Kbit/s and CPU slowdown 4. The saved baseline and final builds use the same frontend port 3106; saved baseline API source uses loopback 8103 and final API source 8102, with identical locked vendor dependencies and the same isolated database/storage. Same frozen seven-product/eight-project/79-private-original snapshot and same stone/photo bytes; public eligibility still hides drafts. Three interleaved homepage runs per locale, one catalogue/product/project sample per locale per stage. No other test/build workload was deliberately run during measurement. The Windows QA server's storage environment was corrected for synthetic fixtures; final measurement uses the restored frozen snapshot and byte-identical media. Local CPU/HTTP timing variation remains a limitation.

<!-- M8_METRICS_START -->
| Page | Score | LCP | CLS | TBT | FCP | Speed Index |
|---|---:|---:|---:|---:|---:|---:|
| FR home median | 92 → 96 | 2.981 → 2.728 s | 0.0000 → 0.0000 | 177.0 → 106.5 ms | 1.258 → 1.216 s | 1.725 → 1.248 s |
| AR home median | 85 → 86 | 3.819 → 3.757 s | 0.0000 → 0.0190 | 148.5 → 111.5 ms | 2.116 → 2.111 s | 2.116 → 2.111 s |
| FR catalogue (one run) | 85 → 90 | 3.781 → 3.457 s | 0.0000 → 0.0000 | 225.0 → 120.5 ms | 1.260 → 1.249 s | 1.653 → 1.249 s |
| FR product (one run) | 89 → 88 | 3.428 → 3.485 s | 0.0000 → 0.0000 | 166.0 → 192.5 ms | 1.252 → 1.255 s | 2.022 → 1.562 s |
| FR project (one run) | 81 → 90 | 4.410 → 3.521 s | 0.0000 → 0.0000 | 218.0 → 106.5 ms | 1.258 → 1.253 s | 2.418 → 1.253 s |
| AR catalogue (one run) | 75 → 81 | 4.779 → 4.610 s | 0.0000 → 0.0291 | 274.0 → 96.5 ms | 2.121 → 2.113 s | 2.121 → 2.113 s |
| AR product (one run) | 84 → 81 | 4.067 → 4.609 s | 0.0000 → 0.0000 | 135.5 → 114.5 ms | 1.817 → 2.109 s | 2.593 → 2.109 s |
| AR project (one run) | 76 → 80 | 5.123 → 4.616 s | 0.0000 → 0.0000 | 206.0 → 140.0 ms | 2.130 → 2.111 s | 2.130 → 2.111 s |

| Page | Total transferred bytes | Image requests | Image transferred bytes |
|---|---:|---:|---:|
| FR home | 889,142 → 845,851 | 14 → 14 | 654,709 → 609,829 |
| FR catalogue | 811,732 → 768,534 | 11 → 11 | 571,810 → 527,262 |
| FR product | 679,665 → 636,492 | 8 → 8 | 430,441 → 385,177 |
| FR project | 997,414 → 953,329 | 19 → 19 | 751,736 → 707,176 |
| AR home | 1,061,481 → 1,017,990 | 15 → 15 | 679,274 → 634,458 |
| AR catalogue | 959,162 → 915,843 | 11 → 11 | 571,810 → 527,262 |
| AR product | 947,209 → 904,037 | 9 → 9 | 550,352 → 505,152 |
| AR project | 1,145,242 → 1,101,007 | 19 → 19 | 751,736 → 707,176 |

baseline FR: score 88–93, LCP 2.942–3.383 s, TBT 137–207 ms, benchmarkIndex 1222.5–2297. baseline AR: score 85–88, LCP 3.386–3.826 s, TBT 142–153 ms, benchmarkIndex 1128.5–1503. final FR: score 95–96, LCP 2.707–2.866 s, TBT 98–107 ms, benchmarkIndex 2272–2695. final AR: score 85–86, LCP 3.757–3.758 s, TBT 101–169 ms, benchmarkIndex 2144.5–2651.

The initial baseline was FR 71 / AR 68 at median benchmarkIndex 514.5 / 511; it is retained in the JSON history and private raw reports. The repeated pair above uses the same saved baseline code under the later machine conditions. Run-to-run variation and single representative samples limit causal conclusions. Initial GLB, MP4 and sharp-detail requests were zero throughout both primary sets.
<!-- M8_METRICS_END -->

The actual homepage LCP element is `header#top` in both locales, not an assumed hero heading. Resource discovery/priority, the logo, server/API waterfalls, render delay, critical CSS, Arabic fonts and main-thread tasks were inspected. Next's automatic fetch deduplication is disabled by an explicit AbortSignal, so primitive-key React request caching retains the timeout while avoiding duplicate metadata/page calls. Two critical CSS files remain; Arabic Cairo fonts use swap. FR homepage CLS is zero; final AR homepage median CLS is **0.0190** (range 0–0.0190), and the AR catalogue sample is 0.0291. Lighthouse identifies the late Arabic font swap at the hero heading. These are regressions from the repeated baseline's zero, remain below 0.1, and are disclosed rather than hidden by a typography/design change.

FR product LCP/TBT and AR product LCP/score regress in the one-run samples; these are not proof of a causal code regression. Even after repeating the baseline, CPU calibration still differs: homepage median benchmarkIndex is FR 1450.5→2355.5 and AR 1233.5→2593.5. Thus **the timing/score comparison remains confounded**, not a controlled claim of the displayed gains or regressions. The initial baseline and intermediate samples are retained. Transfer savings, unchanged shared framework chunks, reduced API calls and the controlled paint ablation are firmer evidence. Initial JavaScript is 182,734→182,623 transferred bytes across ten script requests in both locales; React/Next/GSAP main-thread work remains. Do not interpret lower TBT here as a demonstrated CPU-cost reduction.

Field p75 LCP/INP and RUM were not measured. TBT is a lab metric, not INP. Good LCP ≤2.5 seconds, INP ≤200 ms and consistent ≥90 scores across languages/pages are not established. Production performance remains PARTIAL.

Initial mobile measurements request no GLB, MP4 or sharp-detail crop. Image counts and bytes include browser near-viewport lazy loading; this is not a claim that all below-fold images remain unfetched. Both disabled and enabled-but-unopened viewers were checked for absent Three.js runtime/model transfer; videos are absent from listings and conservatively loaded in detail galleries.

## Background interaction and fallbacks

Built desktop probe: Chromium headless, 1440×900, software rendering, no physical-GPU claim. Initial stone requests: one base; first pointer interaction: exactly one detail crop added. Warm pointer trace: **zero Paint records**, zero large-cull Paint records and no interaction long tasks. A controlled in-browser comparison recorded 298→7 Paint records and 930→16 raster tasks; the final built warm trace separately verifies the implemented fix. This is not a mobile-LCP attribution.

| Sequence | Frames | Median interval | p95 | Maximum |
|---|---:|---:|---:|---:|
| First reveal | 93 | 16.7 ms | 16.8 ms | 16.8 ms |
| Pointer | 127 | 16.7 ms | 16.7 ms | 16.8 ms |
| Scroll | 84 | 16.7 ms | 33.4 ms | 150 ms |

Zero application rAF callbacks occurred during 700 ms of settled idle. Offscreen and emulated hidden states deactivate interaction. Touch/mobile, reduced motion, Save-Data, low-memory, no-JavaScript and unsupported-mask fallback paths were exercised by browser tests. There is no permanent idle animation loop. Scroll spikes remain: **sustained 60 fps is unverified/PARTIAL**, despite the improved warmed pointer trace.

## Media, 3D, video and administration

Both synthetic uploaded textured GLB and procedural slab/block paths were rendered, including changed real proportions, orbit/zoom/reset, bounded touch behavior, keyboard controls, idle-frame stability, return to photos, failure recovery and canvas disposal. No fake 3D settings were assigned to real draft products. Uploaded models with no admin-entered dimensions correctly omit dimension guides; configured procedural dimensions retain them and agree with M7. Mobile QA uses browser/device emulation and SwiftShader, not a real phone GPU.

Synthetic video tests verify actual playback, native controls, no autoplay, `preload=none`, configured/absent posters, no MP4 requests before play, paused old media on switching, and lightbox close disposal. Backend tests exercise bounded H.264/AAC validation, immutable publication, checksums and local HTTP ranges. These checks do not validate Cloudflare range delivery.

The complete backend suite covers super-admin/content-manager policy boundaries and direct unauthorized actions for catalogue/project/editorial publication, protected site/WhatsApp settings, media/video/GLB approval/publication, private uploads, references and deletion. Chromium also signs into the isolated admin, uploads a synthetic GLB and verifies it remains private, pending and unpublished. Original production/user credentials are neither copied into this report nor needed. Authentication cookies remain encrypted, HttpOnly, host-only, SameSite=Lax and Secure in production. CSRF remains enabled; anonymous admin/private-media requests are rejected.

M7 checks include forged identities, unassigned/cross-product size/finish identifiers, unpublished contexts, wrong units, decimal/custom-size bounds, unsafe text/control characters, oversized input and messages, Host/Origin misuse, disabled destination/fallbacks, canonical URL encoding, no-store and full-message copy fallback. No personal request input is persisted and no test claims WhatsApp message delivery.

Private originals and authenticated previews remain separate from published, versioned immutable variants; original checksums and approval/reference safeguards remain in place. Public media exposes only exact configured origins, correct MIME/cache/ETag/range metadata. There was **no live R2/CDN test**. Staging must verify private-bucket access rejection, real public origin/TLS, CORS/preflight, WebP/AVIF, GLB embedded textures, binary integrity, MP4 206/416 ranges and cache invalidation/versioning, as detailed in the operations runbook.

## Security limits and staging work

- Host and forwarded-header spoofing, explicit proxy configuration rejection, API throttling, exact CORS, header policies and oversized requests are covered by tests. Existing 16 KiB streamed Next enquiry limit remains; backend 60 MiB declared-body guard complements the required Nginx/PHP limits. It is not a replacement for a reverse-proxy limit on chunked bodies.
- CSP is intentionally report-only. Public nominal pages and uploaded GLB were checked for violations. Admin framework inline/Alpine behavior still needs a real staging nonce/CSP review before enforcement; no full-admin zero-violation claim.
- HSTS remains off locally and opt-in for production HTTPS. TLS, real forwarded-header trust, secure cookies behind PHP-FPM, authoritative distributed/per-client rate limiting and production error/log policy require staging validation. Next's budgets are per-process aggregate circuit breakers, not distributed per-IP protection. The backend sees the Next server IP for server-side fetches; tune/test that aggregate API quota under realistic traffic before launch.
- Dependency audits found zero reported locked Composer advisories/abandoned packages and zero npm production vulnerabilities at audit time. This is not a penetration-test or future vulnerability guarantee.

## Accessibility, responsiveness and visual review

All eight populated public page types in FR/AR were checked at 320, 360, 390, 430, 768, 1024 and 1440 pixels. Axe 4.14.0 completed **112 page/viewport checks plus 28 interactive-state checks**, with zero automated violations after the landmark/alt fixes. Widgets include navigation, filter/search dialogs, contact/enquiry review/errors, video and 3D. Existing and additional browser checks cover headings/labels, skip targets, keyboard/focus trapping/restoration, error announcements, reduced motion, touch layouts and RTL.

Thirty-two real-content screenshots at 390/1440 and desktop/mobile contact sheets were inspected: FR/Arabic reading order, alignment, image crops, text readability and no horizontal overflow. Long collection labels wrap tightly on the existing six-column desktop layout; no redesign was introduced. This is local screenshot review, **not final client visual approval**. Axe incomplete results include image-backed contrast, `aria-required-children` review and `video-caption`; manual contrast, timed-caption/speech assessment, screen-reader/assistive-technology testing and real-device checks remain. Zero automated violations is not WCAG 2.2 AA certification.

## Verification ledger

| Check actually performed | Result |
|---|---|
| Complete Laravel suite, isolated MySQL 3308 | **205 passed, 994 assertions**; includes schema/database guards and direct authorization/media/M7 boundaries |
| Complete frontend unit suite | **43 passed, 0 failed, 0 skipped** |
| Chromium public/real-content group | **37 passed, 0 failed**, final build 7, across nine files |
| Chromium synthetic foundation/catalogue/projects/design/requests/viewer group | **42 passed cumulatively** across six files: first corrected-harness run 38 passed, four stale copy/optional-dimension assertions corrected and four targeted reruns passed; no skipped tests |
| Axe 4.14.0 | **140 completed checks, zero automated violations**; manual incomplete items above |
| Production build / TypeScript / ESLint | Passed on the final source |
| Pint, changed PHP files | Passed |
| Pint, complete repository | **Fails one unchanged baseline file**, `app/Filament/Resources/ProjectForms.php`: line endings/unary/not spacing. Its bytes match the baseline; left untouched under the no-unrelated-formatting rule |
| Composer validation | Schema/lock valid; strict exit 1 for two existing exact-pin recommendations (`filament/filament` 5.10.1, `laravel/framework` 13.35.0); pins preserved |
| Composer locked / npm production advisory audit | Zero reported advisories/abandoned packages; zero npm production vulnerabilities |
| Source isolation / diff checks | Passed: **630 source files**, one intentional forbidden-database test fixture, no external source links or tracked private environments; `git diff --check` passed |
| Physical phone, field INP/RUM, production FPM/TLS/Nginx, live R2/CDN, backup restore rehearsal | **Not performed** |

Harness corrections are not application regressions: Windows PHP needed environment visibility for the separate QA media root; old selectors expected a removed language nav and checkbox/form filters; obsolete copy was updated to the current project wording; hidden lazy images no longer block screenshot decoding indefinitely. Contact-empty-state coverage explicitly disables and restores only the disposable fixture's destination. Existing dimension, media approval, request and security assertions remain, with added exact eligible-link/CSP checks. No tests were skipped or business validations relaxed.

Private evidence is under ignored `.tools/m8`: full backend/unit/browser logs and JSON, raw Lighthouse JSON, traces, screenshots, axe outputs and sanitized integrity reports. These local paths contain temporary test infrastructure; do not package `.tools`, fixture credentials, logs, SQL or originals into a release.

## Integrity and operations

Read-only hash comparison: **255 reference-checkout files and all 145 pre-existing client input files unchanged**. All **79 preview private originals** match their baseline hashes. The original preview's **28 tables** were compared read-only: only the operational `cache` table changed; the other 27 tables, including business rows, settings and publication state, match. Application logs accumulated normal local diagnostics; no original media or business records were overwritten.

The frontend inventory check initially exposed two already-missing organized aliases. They were restored exclusively as byte-identical copies of existing client files with the expected recorded SHA-256, without modifying a source, inventing a photo or changing publication. All 113 inventoried source entries now validate. Raw aliases remain outside the M8 code commit.

The destructive/resettable test environment is separate loopback MySQL 3308, a distinct data directory/account/key and a separate Laravel storage root. The reset helper checks the actual server port, database name and resolved data directory before modifying only that isolated schema. The original populated preview on 3306 was never used for RefreshDatabase or fixture preparation. Its snapshot was copied read-only and restored only into the isolated instance after verification.

[m8-operations.md](m8-operations.md) documents Ubuntu/Nginx/Next/PHP-FPM/MySQL requirements, environment/secret handling, FFmpeg/GLB bounds, exact-origin R2/CORS/range tests, upload/time limits, permissions, proxy/TLS/HSTS/CSP staging, health/logs and encrypted backup/restore/rollback. It is a future rehearsal runbook, not an executed deployment or tested recovery-time claim. No queue/cron service was invented.

## Production go/no-go

| Area | Status | Remaining requirement |
|---|---|---|
| Functional correctness | PASS locally | Staging regression checks under actual service topology |
| Media integrity | PASS locally | Real R2/CDN rehearsal |
| Performance | PARTIAL | LCP threshold, reliable CPU/TBT comparison and sustained scroll pacing; field INP absent |
| SEO | PASS locally | Approved real canonical/CDN origins and later explicit indexing approval |
| Accessibility | PARTIAL | Manual contrast/captions, screen reader and physical-device review |
| Application security | PARTIAL | Staging CSP/admin inline review, TLS/proxy/cookies and rate-limit/load validation |
| Admin permissions | PASS locally | Confirm actual named accounts/roles in staging |
| R2/CDN integration | UNVERIFIED | Authorized staging credentials/buckets and real delivery tests |
| Content readiness | PARTIAL | Owner verification of company/contact facts, translations, gallery provenance, captions and any pending draft media |
| Visual approval | BLOCKED | Final business approval of the overall site |
| Operations/backup | PARTIAL | Authorized Linux/service and independent restore/rollback rehearsal, agreed recovery objectives |
| Real-device validation | UNVERIFIED | Actual iOS/Android touch, scrolling, video, WebGL and assistive technology |

## M8 acceptance

- [x] Technical SEO implemented and locally tested; indexing remains launch-gated.
- [x] Critical public/admin/API/media/request boundaries reviewed and hardened; CSP enforcement/staging limitations explicit.
- [x] Existing business/media/editorial/WhatsApp flows exercised; no production data publication.
- [x] Comparable baseline/final mobile measurements and network diagnostics recorded; performance targets remain PARTIAL.
- [x] Background appearance and static fallback behavior retained; measured pointer improvement and scroll limits disclosed.
- [x] Uploaded/procedural GLB, video validation/publication and deferred transfer safeguards exercised.
- [x] Responsive and automated accessibility audits completed; manual and real-device gaps explicit.
- [x] Operations, backup/restore and rollback documentation prepared; production rehearsal not claimed.
- [ ] Final visual/content approval, staging/CDN/security/recovery and physical-device validation complete.
- [ ] Good field Core Web Vitals established and explicit production launch/indexing approval received.

M8 code and local verification are delivered with the limitations above. **Overall PARTIAL; production NO-GO. Stop at M8.**
