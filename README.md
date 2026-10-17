# LAAMACH MARBRE — M1

Independent Next.js and Laravel/Filament foundation. Only French and Arabic shell routes, backend readiness, administrator authentication and infrastructure configuration are implemented. No catalogue, projects, media pipeline, 3D, WhatsApp or final page design exists.

See [local setup](docs/local-setup.md) for installation, database provisioning, runtime commands, locales and verification. [M1 verification](docs/m1-verification.md) records actual results and limitations.

## Requirements

- Node.js 24 and npm; exact dependencies are locked.
- PHP 8.3+ with Composer, PDO MySQL, mbstring, intl, XML/DOM, fileinfo, ctype, curl, OpenSSL and ZIP.
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
