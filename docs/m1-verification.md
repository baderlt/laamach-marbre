# M1 — Independent Foundation verification

Verified locally on 17 October 2026. M1 only; M2 has not started.

## Created foundation

- Independent Git repository and root npm workspace/lockfile.
- `apps/web`: Next.js App Router, strict TypeScript, Tailwind, minimal localized shell, error/not-found boundaries, validated server configuration and backend readiness integration.
- `apps/api`: Laravel, Filament login/dashboard, scoped administrator policy, hidden-prompt provisioning command, versioned readiness API and environment/test safeguards.
- `config/locales.json`: shared enabled locale/default/fallback/direction metadata.
- `tests`: frontend configuration/unit cases and Chromium acceptance cases; backend tests live under `apps/api/tests`.
- `scripts/check-isolation.mjs`: source identifier, private-environment and source-link checks.
- `deployment`: independent service identity template only.
- `docs/local-setup.md`: actual M1 setup, ports, environments, locales, commands and storage boundary.

Only two application migrations exist: users/sessions and cache/cache-locks. Both independent databases contain exactly `users`, `sessions`, `cache`, `cache_locks` and Laravel's `migrations` table. The application database has no administrator account by default; provisioning is an explicit owner action.

## Reuse decisions

Reused reference patterns: npm workspace separation, server-only bounded API access, locale-aware HTML, strict configuration boundaries, Filament panel/policies, guarded MySQL tests, private storage and infrastructure-cache conventions. Framework entrypoints and the ordinary authentication configuration were selectively transferred and adapted. Core dependency versions match the reviewed working set; unnecessary development tools were removed.

Not transferred: business models/migrations, content, photography, environment files, secrets, accounts, database data, media pipeline, quotations/inbox, 3D studio, application taxonomies, deployment domains or operational release scripts. Missing third-party dependency archives were prepared separately as described below; application business files were not used as dependency artifacts.

## Versions

| Component | Version verified |
| --- | --- |
| Node.js | 24.19.0 |
| Next.js | 16.4.0 |
| React / React DOM | 19.3.0 |
| TypeScript | 5.9.3 |
| Tailwind / PostCSS plugin | 4.3.3 |
| ESLint | 10.13.0 |
| TypeScript ESLint | 8.71.2 |
| React Hooks ESLint plugin | 7.1.1 |
| Playwright | 1.64.0 |
| PHP | 8.3.6 |
| Laravel | 13.35.0 |
| Filament | 5.10.1 |
| Livewire | 4.4.7 |
| MySQL | 8.3.0 |
| PHPUnit | 12.5.39 |
| Pint | 1.32.1 |

All other resolved versions are recorded in the two independent lockfiles. The initial lint configuration pulled in an advisory-affected development dependency; the final smaller lint toolchain installs with zero npm audit findings.

## Executed verification

| Check | Exact result |
| --- | --- |
| npm dependency installation | Passed; final install reports 0 vulnerabilities |
| Composer locked installation | Passed from independent local cache; 141 locked packages |
| Backend complete suite | 39 passed, 76 assertions |
| Final affected backend suite after cookie isolation | 9 passed, 49 assertions; the other 30 cases were unchanged |
| Frontend unit/configuration tests | 5 passed, 0 failed/skipped |
| Final Chromium acceptance suite | 7 passed, 0 failed/skipped, 0 retries |
| TypeScript | Passed |
| ESLint | Passed with zero allowed warnings |
| Pint | Passed after formatting new application files |
| Composer manifest/lock validation | Passed: `validate --strict --no-check-all` |
| Next.js production build | Passed with Webpack |
| Laravel config/route/view compilation | Passed; final auth configuration also recompiled successfully |
| Source isolation | Passed; only explicit negative database fixtures contain forbidden reference database names |
| Database account isolation | Test account denied access to the application database |
| Composer runtime isolation | No PSR-4 autoload path resolves outside this repository |
| Reference preservation | All 255 captured source/configuration/environment hashes matched after implementation |

Backend coverage includes the exact test-database allowlist, override rejection, effective database verification, environment validation, successful administrator authentication, rejected inactive/wrong-password attempts, protected privilege fields, policies, remember-cookie namespace, infrastructure-only schema and private local storage without R2 credentials.

The final browser run verifies configured-root redirection, French/Arabic HTML language/direction, backend availability, navigation, 390/1280px overflow checks, unsupported paths, login fields, absent registration, trusted locale metadata and live CSRF rejection. An earlier added assertion followed an unauthenticated admin redirect; the corrected test exercises the web root's CSRF middleware directly and retains the 419 requirement.

## Routes and isolation

Development servers started and returned verified responses:

- `http://127.0.0.1:3100/fr`: 200, French/LTR shell.
- `http://127.0.0.1:3100/ar`: 200, Arabic/RTL shell from configured metadata.
- `http://127.0.0.1:8100/api/v1/health`: 200, independent application identity and database readiness.
- `http://127.0.0.1:8100/admin/login`: 200.

Production-build browser QA used ports 3101/8101 and the dedicated test database. QA processes were stopped afterwards; the development pair is available for local review.

Database names are `laamach_marbre` and `laamach_marbre_test`, with different local accounts, passwords and application keys. Sessions/cache/remember cookies are environment-namespaced. The unused SPA CSRF cookie is disabled without disabling form-token validation. Private environments, dependency artifacts, generated assets and storage are not committed. No runtime source path, writable link, storage location or dependency points into the reference project.

The intentional identifier exception is confined to `apps/api/tests/Unit/TestDatabaseGuardTest.php`: forbidden database names are negative fixtures, never connection targets.

## Limitations and deliberate boundaries

- GitHub archive downloads returned authentication/timeouts. Composer installation therefore used independently copied package caches plus exact-version third-party archives reconstructed from the reference installation, after checking installed version and dist-reference metadata. They were installed into LAAMACH's own vendor directory. A clean network-only Composer installation is not claimed.
- Turbopack's CSS worker failed on this Windows host both inside and outside the sandbox. The supported Webpack development/build path passes and is explicitly configured.
- The host's default Node is 23 and PHP INI contains an invalid upload setting. Verification selected Node 24 and an ignored project-local PHP INI copy. Neither global configuration was changed.
- R2 disks are configuration only. No adapter/pipeline, buckets, CDN, credentials or media domain have been implemented or verified. No Cloudflare credentials are needed for M1 local operation.
- No Linux/VPS/TLS deployment is claimed. The service file is a future-host template, not an installed unit. M1 remains noindex pending the explicit launch policy milestone.
- No default administrator, final pages, client assets or M2+ business functionality was created.

## M1 acceptance checklist

- [x] Independently runnable frontend/backend and repository.
- [x] Independent environments, databases, accounts, keys, ports, storage, cache and sessions.
- [x] Configurable French/Arabic routes; RTL from locale metadata; no mandatory English locale.
- [x] Backend readiness and Filament authentication/policy foundation work.
- [x] Destructive test safeguards accept only the dedicated local test database.
- [x] Local private storage works with empty R2 credentials.
- [x] Private/published R2 configuration is prepared only.
- [x] Production build, backend cache compilation, typecheck, lint and relevant tests pass.
- [x] No runtime dependency on the reference project or copied business branding/content.
- [x] Reference files remain unchanged in the captured integrity check.
- [x] M2 has not started.
