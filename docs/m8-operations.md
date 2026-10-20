# M8 operations preparation — deployment not authorized

This is a runbook for a later approved staging rehearsal and M9 deployment. No VPS, DNS, TLS, production bucket or production account was changed during M8. The visual design still needs final business approval. Keep indexing disabled until the actual launch is explicitly approved.

## Runtime and identity

- Node 24.x, npm lockfile, Next 16.4.0, React 19.3.0. Use `npm ci`, not a dependency upgrade during release.
- PHP 8.3+ with Composer 2, PDO MySQL, mbstring, OpenSSL, ctype, fileinfo, JSON, tokenizer, XML/DOM, curl and GD with WebP. Verify actual PHP-FPM and CLI versions/extensions agree. Install only required platform packages.
- MySQL 8, utf8mb4; independent application database/account/key and independent test account/database. No global MySQL grants. Use a separate migration identity where operationally possible; runtime DDL access is unnecessary.
- FFmpeg and ffprobe must be executable by PHP-FPM. Video inspection/remux is synchronous, capped at 45 seconds, 50 MiB, 60 seconds, 1920 maximum dimension, 30 fps. Existing validation requires supported MP4/H.264/AAC content. No HLS or automatic video transcoding service exists.
- GLB validation is built in (8 MiB file, 1 MiB JSON, 100k triangles/vertices, bounded textures/nodes/accessors and eight-second validation budget). Test software WebGL does not establish real mobile GPU support.
- Media processing is synchronous/CLI. There is no required queue worker or scheduled quotation job. Check `php artisan schedule:list` before provisioning cron; do not invent a queue/Redis dependency.

## Environment inventory

Frontend `apps/web/.env.example`:

| Variable | Policy |
|---|---|
| `SITE_URL` | Exact canonical public origin. HTTPS public host for staging/production; never infer it from a request header. |
| `LARAVEL_API_URL` | Server-only API origin. Its hostname must match the backend `APP_URL` Host allowlist: use the configured HTTPS API vhost, or resolve that hostname privately to the internal listener. Do not substitute a loopback IP Host for a canonical API hostname. Never `NEXT_PUBLIC_*`. |
| `API_TIMEOUT_MS` | 500–8000; default 3000. Tune only with measured server latency. |
| `SITE_ENV` | `local`, `staging`, `production`; independent of Next's `NODE_ENV=production` build mode. |
| `SITE_INDEXING_ENABLED` | **false**. Only exact `true` plus `SITE_ENV=production` enables indexing. Rebuild/restart and verify HTML, headers and robots together after explicit launch approval. |
| `MEDIA_ORIGINS` | Comma-separated exact published CDN origins, HTTPS outside local. No wildcard, credentials, paths or private R2 endpoint. |
| `HSTS_ENABLED` | false until HTTPS/reverse proxy verified; production opt-in only. No inherited includeSubDomains/preload. |
| `NEXT_DIST_DIR` | Optional separate build output for QA. |
| `PROVISIONAL_CLIENT_PREVIEW` | Local approved preview assets only; not a production media substitute. |

Backend `apps/api/.env.example`: `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG=false`, canonical `APP_URL`; independent `DB_CONNECTION/HOST/PORT/DATABASE/USERNAME/PASSWORD`; `SESSION_DRIVER=database`, `CACHE_STORE=database`; `CORS_ALLOWED_ORIGINS`, `TRUSTED_PROXIES`, `HSTS_ENABLED`; `MEDIA_PRIVATE_DISK`, `MEDIA_PUBLISHED_DISK`; `FFMPEG_BINARY`, `FFPROBE_BINARY`; `R2_ENDPOINT`, `R2_REGION`, `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_PRIVATE_BUCKET`, `R2_PUBLISHED_BUCKET`, `R2_PUBLISHED_URL`.

Hosted environments require the configured private/public R2 disks. Use separate staging buckets and credentials for rehearsals. Keep `.env`, SQL dumps, session data, R2 keys and application keys outside release archives/Git, mode 0600 or equivalent restricted group access. Never print them during diagnostics. Preserve the existing `APP_KEY` on releases and rollbacks; rotating it can invalidate encrypted sessions/settings.

## Build and application preparation (future rehearsal)

Run as the release user in a versioned release directory, with the correct environment already supplied:

```sh
node --version
php --version
(cd apps/api && composer install --no-dev --prefer-dist --optimize-autoloader)
npm ci
npm run build
cd apps/api
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan laamach:media-capabilities
php artisan schedule:list
```

Composer commands belong in `apps/api`; npm commands belong at the repository root. Do not run `migrate:fresh`, test fixtures or PHPUnit against populated preview/staging/production. Back up and review pending migrations before an explicitly authorized `php artisan migrate --force`.

Run Next from `apps/web`: `../../node_modules/.bin/next start --hostname 127.0.0.1 --port 3100`. Use a dedicated non-root systemd service, explicit WorkingDirectory/environment, restart policy and graceful shutdown. PHP-FPM and Next must bind only the intended local/private interfaces. MySQL must not be internet-exposed.

Laravel serves only `apps/api/public`. Deny dotfiles, `.env`, vendor/source/storage paths, backups and arbitrary PHP scripts. Only `public/index.php` goes to PHP-FPM. Do not create a public `storage` symlink: originals/previews are private and authenticated media routes enforce provenance/publication.

Give PHP-FPM write access only to Laravel `storage` and `bootstrap/cache`, with restricted temporary-upload directories (typically directories 0750/files 0640, owned by the service group). Next needs its own writable cache only if the selected deployment mode uses it. Never use world-writable 0777. Run media processing under the same bounded identity and PHP memory budget (at least the pipeline's 256 MiB image budget plus application overhead; validate 512 MiB in staging).

## Nginx/TLS policy to validate in staging

Use explicit `server_name` values plus a default server that rejects unknown hosts. Obtain/renew valid certificates and test renewal before setting HSTS. Send trusted, overwritten headers, never append arbitrary client-forwarded values:

```nginx
# http context: tune with measured demand; these are initial protective limits.
limit_req_zone $binary_remote_addr zone=stone_public:10m rate=10r/s;
limit_req_zone $binary_remote_addr zone=stone_enquiry:10m rate=12r/m;
limit_req_status 429;

# Inside the public HTTPS server:
client_max_body_size 64m;
location = /api/requests {
    client_max_body_size 16k;
    limit_req zone=stone_enquiry burst=5 nodelay;
    proxy_pass http://127.0.0.1:3100;
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-Host $host;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Forwarded-For $remote_addr;
}
location / {
    limit_req zone=stone_public burst=30 nodelay;
    proxy_pass http://127.0.0.1:3100;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-Host $host;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Forwarded-For $remote_addr;
}
```

This is a policy fragment, **not a complete deployable server config**. Configure the separate API/admin HTTPS vhost and its exact PHP-FPM socket, `SCRIPT_FILENAME`, front-controller `try_files`, TLS, logging and protected paths. If using Cloudflare, allow only its verified ingress ranges at the origin, configure trusted real-IP ranges explicitly, and overwrite forwarded headers after resolving the real client. Never use `TRUSTED_PROXIES=*`; the application accepts explicit IP/CIDR and deliberately ignores forwarded Host. Keep private API routing compatible with `APP_URL` Host validation.

PHP `upload_max_filesize=50M`, `post_max_size=64M`, writable private `upload_tmp_dir`, and a staging-validated 120-second PHP/FPM/proxy processing budget allow the bounded 45-second remux. Livewire accepts at most 50 MiB and authenticated uploads are throttled 10/min. The application rejects bodies above 60 MiB; public enquiry parsing separately caps actual streamed bytes at 16 KiB. A lying/absent Content-Length is handled by the reverse proxy/PHP upload limits, not just the application header check.

Laravel public API limits are 120 requests/min/client using its configured shared cache. Server-to-server reads sharing one source IP also share this quota; staging must verify/tune it for expected aggregate frontend traffic. An array cache is suitable only for isolated tests; it cannot enforce limits between PHP requests. Next public enquiry/search limits are bounded process-wide circuit breakers (120/240 per minute), not distributed user identification. Nginx is the authoritative per-client protection across Next workers. Rate-limit responses and outage handling require staging tests under realistic traffic.

## CSP, sessions and caching

Public HTML sends nonce-based **report-only** CSP; runtime/JSON-LD scripts share a fresh nonce. Production has no script unsafe-inline/unsafe-eval. Style attributes remain allowed because the existing animation/viewer uses bounded CSS values. Approved self/CDN images, media and WebGL blob resources are allowlisted. Static brand/background files do not need nonce/CSP document policies. Backend HTML uses a restrictive report-only candidate; Filament framework inline scripts/styles still require a staging compatibility review before enforcement. Do not claim enforced CSP or add unsafe script allowances to hide violations. Configure a privacy-reviewed report collector before using `report-uri`/`report-to`; none is currently enabled.

Cookies are encrypted, HttpOnly, host-only, SameSite Lax, Secure in production. CSRF form/Livewire token validation stays enabled; no public registration. Admin, Livewire and JSON API responses are private/no-store; enquiry responses are no-store and never persist form input. Public immutable media variants may be cached for one year by their content/revision keys. Never cache authenticated previews/original downloads at a shared proxy. Preserve Range/If-Range/ETag and content types for MP4/GLB delivery.

## R2/CDN staging checklist (not yet executed)

1. Provision independent staging private/public buckets and a least-privilege scoped key. Deny public access to originals and processing scratch; prevent private bucket listing/public URLs. Use only approved variant keys in the public bucket.
2. Set the exact HTTPS published origin in `R2_PUBLISHED_URL` and frontend `MEDIA_ORIGINS`. Validate DNS/TLS and object cache rules. Never expose the private S3 endpoint as a social-image URL.
3. Configure bucket/CDN CORS for exact frontend origins, GET/HEAD/OPTIONS, Range/If-Range/If-None-Match, exposed Content-Range/Accept-Ranges/Content-Length/ETag. Credentials are not required. Test both permitted and hostile origins.
4. Ingest known client-supplied test originals; verify hashes, private download authorization, approval roles, variant revision, content-type/cache/ETag, failure rollback and referenced-deletion protection. Do not grant synthetic configurations to real draft products.
5. Request real MP4 `Range: bytes=0-1023`; require 206 and correct Content-Range/length, unsatisfiable range 416, seeking/playback/pause/close behavior on iOS/Android. Confirm CDN is not ignoring/compressing range responses.
6. Activate one approved uploaded GLB cross-origin; compare downloaded binary hash and validate textures/CORS, missing/corrupt object fallback and cancellation. Test unopened pages transfer no GLB/Three.js/video bytes.
7. Measure cold/warm CDN delivery and cache hits independently from local PHP media routes. Do not describe local loopback results as CDN evidence.

AVIF is opt-in only after `laamach:media-capabilities` succeeds on the actual runtime; existing immutable WebP variants remain valid. There is no image recoloring or generated substitute for client stone photographs.

## Backups, restore and rollback

Before a release, create an encrypted consistent MySQL backup (transactional snapshot with schema/migration state), private-original object inventory/hashes, public variant inventory, protected environment/key backup and release commit/lockfiles. Retain previous immutable objects while any database/release may reference them. Enable appropriate independent bucket versioning/retention and lifecycle rules that never expire referenced originals. Store copies off the VPS with restricted access and documented retention; agree recovery objectives with the owner.

Rehearse restore into a **separate** database and buckets: restore rows, original/variant hashes, private/public separation, settings decryption, admin login/roles, public eligibility, videos/ranges/GLB and enquiry preparation. Record duration and data-loss window. A backup command succeeding is not a tested restore. M8 imported a local MySQL snapshot into its isolated QA instance; it did not rehearse the complete Linux/offsite/R2 recovery procedure or establish a production recovery time.

Rollback by switching to the previous verified release/build and its compatible database state, preserving `APP_KEY`, uploads and settings. Rebuild cached configuration, restart/reload only the owned services, then verify health/public/admin/media. Do not blindly run migration rollback after accepting writes: inspect schema compatibility and use the rehearsed data-recovery plan. Keep indexing off throughout an unapproved rehearsal/rollback.

## Health, logs and later deployment verification

Check `/api/v1/health` for generic `ok`/database-connected JSON, Next FR/AR HTTP 200, invalid slug 404, admin guest redirect/login, private original rejection, and fresh sitemap/publication changes. Health output must not contain connection details. Inspect Laravel `storage/logs`, Next service journal, PHP-FPM error/slow logs and Nginx access/error logs with restricted retention. Redact query/form bodies, cookies and authorization data from operational capture.

After future authorization: `nginx -t`, service status, `curl -I` public FR/AR/admin/API/assets, robots/sitemap HTML metadata consistency, CSP report review, `curl -H 'Range: bytes=0-1023'` against an approved video, binary GLB checks, the scoped test suite in an independent environment, Lighthouse and physical-device keyboard/touch/video/3D tests. These commands are preparation only; M8 does not execute M9.
