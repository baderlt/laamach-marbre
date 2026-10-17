# LAAMACH MARBRE — M3

Independent Next.js and Laravel/Filament foundation with configurable locales (initially French and Arabic), administrator authentication, private/immutable published media and a functional product catalogue. M3 supports two-level categories, measured standard sizes, size-specific images, custom-size constraints, quantity units and commercial finishes. No projects, 3D, WhatsApp, commerce or final page design exists.

See [local setup](docs/local-setup.md) for installation, database provisioning, runtime commands, locales and verification. [M1 verification](docs/m1-verification.md) records actual results and limitations.
See [M2 media foundation](docs/media-foundation.md) for imports, approval, profiles and R2 setup; [M2 verification](docs/m2-verification.md) records the M2 acceptance evidence.
See [M3 catalogue](docs/catalogue.md) for models, admin workflows, APIs and functional UI; [M3 verification](docs/m3-verification.md) records acceptance evidence. Public catalogue: `/fr/products` and `/ar/products`.

## Requirements

- Node.js 24 and npm; exact dependencies are locked.
- PHP 8.3+ with Composer, PDO MySQL, mbstring, intl, XML/DOM, fileinfo, ctype, curl, OpenSSL, ZIP and GD with working WebP encoding. Use a 512 MB media worker PHP limit; the image pipeline separately enforces its configured memory budget.
- MySQL 8 running locally for development and tests. SQLite is not a fallback.

## Start

After completing the environment/database setup, use separate terminals:

```powershell
cd apps/api
php artisan serve --host=127.0.0.1 --port=8100
```

```powershell
npm run dev
```

Public roots: http://127.0.0.1:3100/fr and http://127.0.0.1:3100/ar. Admin login: http://127.0.0.1:8100/admin/login. Readiness: http://127.0.0.1:8100/api/v1/health.

There is no default administrator or password. Provision one with the hidden prompt described in the setup guide.
