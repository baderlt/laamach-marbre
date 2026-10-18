# Populated local content preview

Open `http://127.0.0.1:3102/fr` or `/ar`. This demonstration uses the separate local test database and API port 8101. The actual application on ports 3100/8100 retains three private draft products and no published editorial/project records.

## Content and media boundaries

The client authorized all seven supplied product groups for a provisional preview. Seventeen checksum-verified client photographs provide seven covers and ten gallery images. All 22 originals, including five review files, remain unchanged. The normal M2 workflow dry-run accepted 17 with zero skipped, duplicate, failed or ambiguous mappings; those 17 were applied only to the preview. Originals remain private and published variants remain immutable. This is not approval of commercial names, categories or product specifications.

The latest review supersedes the earlier illustrative product details. No example dimensions, thicknesses, finishes, colour, origin, technical values or availability are displayed. `config/product-editorial-review.json` holds seven editorial draft proposals, with each unknown explicitly pending client confirmation. Preview-only publication retains the existing M3-required internal `piece` unit; the provisional UI shows the unit as awaiting confirmation. This does not change M3 validation. No size images have been inferred.

`config/website-editorial-revision.json` now supplies the reviewed-default FR/AR copy, SEO, activities, general selection guidance, process, About and contact sections. `docs/m5-copy-review.md` records old/new wording and confirmations before the preview was updated. Three inspiration projects explicitly disclaim completed client work; no installation links, locations, years, areas or actually installed materials are asserted. Services describe preparation and questions to confirm, rather than unverified installation/fabrication promises.

No phone, email, address, social account, approved logo, history or certification was invented. Contact submission remains disabled until an email is configured. The geometric brand symbol, neutral architectural placeholders and location diagram are temporary visual treatments. Placeholder cards are not product/project records and do not increase result counts.

## Guarded preparation

From the repository root, with the project's PHP runtime/configuration:

```powershell
php scripts/prepare-content-preview.php --env=testing
php scripts/prepare-content-preview.php --env=testing --apply
php scripts/apply-editorial-preview-revision.php --env=testing
```

The first command is a dry-run. Initial preparation refuses populated datasets and never overwrites existing records. The final command requires the expected seven provisional products, a disabled preview audit actor and the prepared copy inventory. It applies reviewed copy via existing editorial/catalogue/project writers, removes obsolete illustrative values and disables the old demo finishes. All commands refuse the application database. The actor is activated only in memory for authorization and remains disabled as a login.

Local state, private staged assets and AI imagery are ignored under `.tools/content-preview`. Backend tests refresh the disposable test database: stop preview servers and preserve/restore its data before regression testing. These scripts are not production seeders. Promote confirmed content through the existing administration workflow.

Current servers: `artisan serve --env=testing --host=127.0.0.1 --port=8101 --no-reload`; separate Next build `NEXT_DIST_DIR=.next-visual-revision`, `SITE_URL=http://127.0.0.1:3102`, `LARAVEL_API_URL=http://127.0.0.1:8101`, `LAAMACH_PROVISIONAL_PREVIEW=1`, serving port 3102.

## Explicitly provisional AI illustration

The user's later authorization permits one temporary AI image in this local demonstration. `.tools/content-preview/ai/courtyard-v1.png` is an imaginary Moroccan courtyard, not a client building or product photo. Its exact prompt/tool provenance is in `.tools/content-preview/ai/provenance.json`; mode: built-in image_gen. The source image is not committed, ingested into M2 or falsely marked client-supplied. Heroes and inspiration cards disclose AI usage in both languages.

A bounded route serves only that exact filename, with an explicit preview flag, loopback site/backend origins and API port 8101. Disabled mode and other filenames return 404. Normal pages show neither generated imagery nor the demonstration notice. The three attached quarry PNGs remain unused; the catalogue uses a neutral architectural banner.

## Visual revision

All eight page types follow the supplied prototype compositions; desktop and mobile captures cover both locales. Cairo is self-hosted with its included SIL Open Font License, from the [official font source](https://github.com/google/fonts/tree/main/ofl/cairo). No external font request or package dependency was introduced. See the visual revision verification report for final screenshots and current test results.
