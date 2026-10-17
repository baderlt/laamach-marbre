# Local setup (M1 + M2)

## Install

Use Node 24, PHP 8.3+ and MySQL. Verify `node --version` and `php --version` before starting. From the repository root:

```powershell
npm ci
Copy-Item apps/web/.env.example apps/web/.env.local
Copy-Item apps/api/.env.example apps/api/.env
Copy-Item apps/api/.env.example apps/api/.env.testing
cd apps/api
composer install
```

Create each environment only if it does not already exist. Never copy another application's credentials or key. Generated dependencies, builds, local environments and storage are excluded from Git.

## MySQL provisioning

With a local database administrator, create only these databases and separate accounts. Replace both example passwords with different generated values before executing:

```sql
CREATE DATABASE laamach_marbre CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE laamach_marbre_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'laamach_marbre'@'127.0.0.1' IDENTIFIED BY 'REPLACE_WITH_LOCAL_PASSWORD';
CREATE USER 'laamach_marbre_test'@'127.0.0.1' IDENTIFIED BY 'REPLACE_WITH_DIFFERENT_TEST_PASSWORD';
GRANT ALL PRIVILEGES ON laamach_marbre.* TO 'laamach_marbre'@'127.0.0.1';
GRANT ALL PRIVILEGES ON laamach_marbre_test.* TO 'laamach_marbre_test'@'127.0.0.1';
```

These local development grants are database-scoped; production runtime/migration privileges need separate review in M9. No account has global grants or access to another application's database.

Set `.env` to `APP_ENV=local`, URL port `8100`, database/user `laamach_marbre`, and its password. Set `.env.testing` to `APP_ENV=testing`, URL port `8101`, database/user `laamach_marbre_test`, and its separate password. Keep `DB_HOST=127.0.0.1`, `DB_CONNECTION=mysql`, and do not set `DB_URL` or `DB_SOCKET`.

Before testing key generation, configure the testing environment's correct database and environment values. Commands refuse mismatched identities before accessing application data.

From `apps/api`, run:

```powershell
php artisan key:generate
php artisan key:generate --env=testing
php artisan migrate
php artisan migrate --env=testing
php artisan laamach:admin owner@example.invalid --name="Local owner"
```

The administrator command asks for a strong password and confirmation without printing either. It creates a new active super-administrator; it does not overwrite an existing user. There is no public registration or seeded account. M2 adds the Media resource at `/admin/media-assets`; intake is the audited CLI importer. See [media setup](media-foundation.md). No catalogue/business resources exist.

## Local ports and environments

| Purpose | Web | Backend | Database |
| --- | --- | --- | --- |
| Development | 3100 | 8100 | laamach_marbre |
| QA | 3101 | 8101 | laamach_marbre_test |

`apps/web/.env.local` uses `SITE_URL=http://127.0.0.1:3100` and `LARAVEL_API_URL=http://127.0.0.1:8100`. These are server-side origins and must not contain credentials, paths or query strings. Invalid origins/timeouts stop frontend startup/build. Backend configuration refuses another database name or a connection URL override.

Cache prefixes and cookie names are derived from the environment: `laamach_marbre:{environment}:` and `laamach_marbre_{environment}_session`. Sessions are encrypted, host-only and HttpOnly. Production requires HTTPS/debug-off and Secure cookies. Keys, logs, cache, sessions and storage belong only to this application.

The session guard is also environment-namespaced, which isolates Laravel's remember-me cookie. The unused SPA `XSRF-TOKEN` cookie is disabled; server-rendered forms and Livewire retain their ordinary CSRF tokens and validation.

## Locales

`config/locales.json` is shared by the frontend and backend. It defines enabled locale code, display name, direction, default and fallback. Initial entries are French (`fr`, LTR) and Arabic (`ar`, RTL). `/` redirects to the configured default; unsupported locales return 404.

Add a locale entry and its shell translations in `apps/web/src/lib/messages.ts`, then restart/rebuild both applications. Missing shell copy uses the configured fallback; choosing a new fallback requires supplying that fallback's copy. No database change or fixed language enum is required. Filament follows the configured default locale in M1; an admin language selector is not included.

## Storage boundary

The default `local` disk writes only to `apps/api/storage/app/private`. It has no public serving route or public storage link.

`r2_private` and `r2_published` contain configuration placeholders for `R2_ENDPOINT`, `R2_REGION=auto`, access key, secret, private/published bucket names and `R2_PUBLISHED_URL`. Leave credentials/endpoint/domain empty locally.

The S3 adapter, bucket connections, import/publishing pipeline, media tables and CDN delivery are deliberately not implemented or exercised in M1. They belong to M2. The proposed private/public bucket separation is not simulated with a public local directory.

## Checks and production build

From the repository root:

```powershell
npm test
npm run typecheck
npm run lint
npm run build
npm run check:isolation
```

From `apps/api`:

```powershell
php artisan test
php vendor/bin/pint --test
composer validate --strict --no-check-all
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Laravel has no separate JS build in M1. `composer install` publishes Filament's own assets; the config/route/view compilation commands verify its production caches. Use `php artisan optimize:clear` after checking caches before changing local configuration.

`--no-check-all` suppresses Composer's advice against exact version constraints; this foundation intentionally pins the reviewed versions. Schema and lock validation remain enabled.

The default backend test class validates the configuration and effective `SELECT DATABASE()` result before database-refresh traits run. It requires local MySQL exactly `laamach_marbre_test`; URL, socket and read/write overrides are refused. Tests use an isolated private storage root, array cache and array sessions. Negative guard tests intentionally mention forbidden reference database names; these are not connection settings.

## Browser QA

Install Chromium once with `npx playwright install chromium`. Start these two QA processes in separate terminals:

```powershell
cd apps/api
php artisan serve --env=testing --host=127.0.0.1 --port=8101
```

```powershell
$env:SITE_URL='http://127.0.0.1:3101'
$env:LARAVEL_API_URL='http://127.0.0.1:8101'
npm run start --workspace @laamach-marbre/web -- --port 3101
```

Then run `npm run test:browser` from the root. Backend feature tests refresh the test database, so do not use it for valuable content or concurrent manual QA. No browser test submits business data.

Production builds use `.next`; development uses `.next-dev`. Set `NEXT_DIST_DIR=.next-qa` for an additional build if concurrent production-build isolation is needed. Do not run two builds against the same output directory.

## Current Windows verification environment

The default Node on this machine is 23; verification uses the available Node 24.19 runtime. The global PHP INI has an invalid `post_max_size`. Verification uses a private ignored `.tools/php.ini` copy with corrected upload limits and Xdebug disabled; the global INI was not edited. Set `PHPRC` to that absolute file when using this local copy, so PHP subprocesses inherit it. On another machine, use its valid PHP configuration; no `.tools` file is required by application code.

Development and builds explicitly use Next.js's supported Webpack compiler. Turbopack's CSS worker failed its process handshake on this Windows host in both restricted and unrestricted runs; the same source compiled successfully with Webpack.
