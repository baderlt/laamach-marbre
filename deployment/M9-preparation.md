# M9 controlled deployment preparation — not an execution record

The authoritative export is the restricted, Git-ignored file
`.tools/m9-prep/laamach-marbre-m9-20261022-143343-4ff3e954.sql` on the
preparation workstation. SHA-256:
`6d2af7993baeb70c0d7657f089abd95d1321eb8163479008e0f57b9bc7b2fbdc`.
It contains 28 tables, was made with `mysqldump --single-transaction --quick`
including triggers, routines and events, and restored successfully into a
separate MySQL 8.3 instance on port 3311. The restored 46 products, 6 projects,
154 assets, 422 variants, 208 media references and business-content fingerprint
matched the source. The isolated server was stopped; its files are retained.

## Release gate

Do not run the commands below until the owner approves M9 deployment. First
verify the VPS ED25519 host-key fingerprint out of band. The presented key
matched the owner's independent fingerprint and was saved only in the ignored
M9 workspace. SSH authentication then failed because no accepted key was
available to this task; no VPS command was executed. The owner can enter the
root password locally in an interactive SSH terminal; never send it in chat or
place it in a command/script. Inspect the VPS database,
existing application release, backup paths, service names, PHP-FPM
pool, Nginx vhosts, TLS and free disk space before choosing an import action.
Stop if `laamach_marbre` contains tables until its contents and backup have been
reviewed. Never import this dump over a populated destination by default.

1. Review and commit the complete intended source set, including the untracked
   R2 integration files, web catalogue components and
   `scripts/start-client-preview.ps1`. Keep tests in Git. Use a reviewed commit
   for release packaging. `git archive` will omit tests, audit docs and local
   preparation scripts via `.gitattributes`; ignored `.tools`, local media,
   secrets, dumps, dependencies, caches and build output are not in the archive.
   Verify the archive member list and SHA-256 before transferring it.
2. On the VPS, make a restricted, dated backup of the **existing** destination
   database, if it exists, with `mysqldump --single-transaction --quick
   --hex-blob --routines --events --triggers --no-tablespaces
   --result-file=<restricted-backup-path> laamach_marbre`. Hash and retain it
   off-host. Save the existing release, environment files, APP_KEY and service
   configuration under restricted access. Rehearse recovery separately.
3. Transfer the reviewed release archive and the exact SQL export over the
   verified SSH host key to restricted temporary paths. Compare both SHA-256
   values before use. Confirm MySQL major-version compatibility, `utf8mb4` and
   `utf8mb4_0900_ai_ci`. The SQL dump intentionally has no `CREATE DATABASE`
   or `DROP TABLE`; import only into a verified empty `laamach_marbre` database
   after explicit approval. Recheck 46/6/154 counts, relationships, admin rows,
   migrations, R2 disk columns and the content fingerprint after import.
4. Configure separate secret files outside releases. Backend: production
   `APP_ENV`, HTTPS `APP_URL=https://api.laamachmarbre.com`, independent MySQL
   user/database, a new independent and thereafter stable production `APP_KEY`, `APP_DEBUG=false`,
   `MEDIA_PRIVATE_DISK=r2_private`, `MEDIA_PUBLISHED_DISK=r2_published`, correct
   account S3 endpoint and bucket names, and **production runtime** R2 tokens.
   Do not supply temporary migration credentials to the runtime. Frontend:
   `SITE_URL=https://laamachmarbre.com`, server-only
   `LARAVEL_API_URL=https://api.laamachmarbre.com`,
   `MEDIA_ORIGINS=https://media.laamachmarbre.com`, `SITE_INDEXING_ENABLED=false`.
   Keep HSTS disabled until end-to-end HTTPS is verified. The source was not
   found to use encrypted database casts, but production-key choice and any
   encrypted external configuration still need explicit review; do not reuse
   the local preview key and never rotate a live APP_KEY during rollback.
5. In the reviewed release directory run `npm ci`, `npm run build`, then in
   `apps/api` run `composer install --no-dev --prefer-dist
   --optimize-autoloader`, `php artisan config:cache`,
   `php artisan route:cache`, `php artisan view:cache`,
   `php artisan laamach:media-capabilities`, `php artisan migrate:status` and
   `php artisan schedule:list`. Review pending migrations before any separately
   authorized `php artisan migrate --force`. Do not run `migrate:fresh` or seeders.
6. Validate the actual Nginx and PHP-FPM configuration with `nginx -t`, then
   start only the owned Next and PHP-FPM services. Serve Laravel from
   `apps/api/public` and Next on loopback behind TLS. Validate FR/AR and RTL,
   Filament login/roles, authorized private originals, public covers/galleries,
   MP4 range playback, GLB viewer on the production HTTPS origin, CDN CORS/cache,
   WhatsApp links, health, logs, robots/noindex and hostile-origin/private-media
   denial before public launch. The skipped local 3D CORS check remains open
   until this HTTPS test passes.

Rollback: keep the previous release and destination backup untouched. Before
switching back, decide whether the prior code is schema-compatible. If not,
restore the saved destination backup into a **separate** database first and
verify it, then schedule a controlled cutback. Preserve APP_KEY, runtime R2
credentials, immutable R2 objects and all local/recovery media. Never purge
objects or blindly reverse migrations after accepting writes. Keep indexing off
throughout staging and rollback.

Security evidence remains partial: unsigned private S3 GET returned
`400/InvalidArgument` and proved neither denial nor exposure; independent
cross-bucket credential denial was not tested.

## Commands for the separately approved window

These are gated examples for the verified workstation/VPS identities, not
commands run during preparation. Replace `<APPROVED_COMMIT>` and
`<RESTRICTED_VPS_PATH>` only after reviewing the commit and actual VPS layout.

```powershell
# Workstation: only after all intended source is committed and approved.
git diff --check
git archive --format=tar --output=.tools/m9-prep/release.tar <APPROVED_COMMIT>
Get-FileHash -Algorithm SHA256 .tools/m9-prep/release.tar
Get-FileHash -Algorithm SHA256 .tools/m9-prep/laamach-marbre-m9-20261022-143343-4ff3e954.sql
# In an interactive terminal only; enter the root password at SSH's prompt.
ssh -o StrictHostKeyChecking=yes -o UserKnownHostsFile=.tools/m9-prep/known_hosts root@147.93.95.57
```

```sh
# VPS, read-only inventory after authenticated SSH access is established.
mysql --batch --skip-column-names -e 'SELECT @@version, @@port, @@datadir, @@character_set_server, @@collation_server'
mysql --batch --skip-column-names -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='laamach_marbre'"
readlink -f /var/www/laamach-marbre/current
systemctl is-active nginx php8.3-fpm
nginx -t
df -h /var/www /var/lib/mysql
```

Only after a separate import authorization, the destination database is
confirmed empty, its pre-import state is backed up, and the transferred dump
hash equals the checksum above:

```sh
test "$(mysql --batch --skip-column-names -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='laamach_marbre'")" = 0 || exit 1
sha256sum <RESTRICTED_VPS_PATH>/laamach-marbre-m9-20261022-143343-4ff3e954.sql
mysql --database=laamach_marbre < <RESTRICTED_VPS_PATH>/laamach-marbre-m9-20261022-143343-4ff3e954.sql
```

Do not execute this import block if the destination has any tables, the source
fingerprint changed, the backup is missing, or the dump checksum differs. The
release symlink/service-switch commands must use the VPS's verified service
names and directories; those facts are not available yet.
