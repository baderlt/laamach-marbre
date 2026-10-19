# Client project media and confirmed company content

Integrated into the existing local preview (`laamach_marbre_test`, API 8101, website 3105). No deployment or changes to the business database. Product records, sizes, finishes, categories, contact details and WhatsApp configuration were compared with the pre-import snapshot and remain unchanged.

## Reviewed assets

113 supplied files: 107 photos and 6 videos. All were inspected, including video probes and extracted preview frames. 55 supplied sources selected: 5 covers, 45 gallery photos and 5 videos. Five additional poster frames were extracted from those exact videos. 60 media assets imported and approved through M2.

All 113 organized originals retain their original SHA-256 checksums. No image generation, replacement downloads, upscaling, recolouring or aggressive recompression. Eight large images needed separate 2560px maximum, quality-95 WebP intake copies to satisfy the existing worker memory limit; originals remain unchanged. Normal responsive public variants use M2.

| Original folder | Corrected folder | Selected original cover | Gallery photos | Videos | Review/reserve |
| --- | --- | --- | ---: | ---: | ---: |
| ardiya blhajar tabi3i | sols-en-pierre-naturelle | IMG_1209.PNG | 7 | 0 | 5 |
| hajar bni khiran btari9a t9lidiya | pierre-bni-khiran-pose-traditionnelle | IMG_4037.JPG.jpeg | 8 | 0 | 3 |
| hajar btari9a ta9lidiya | pierre-pose-traditionnelle | IMG_2810.JPG.jpeg | 12 | 0 | 17 |
| الشلالات والنافورات-Cascades et fontaines | cascades-et-fontaines | IMG_4054.JPG.jpeg | 6 | 5 | 2 |
| جدران و الواجهات- Murs et façades | murs-et-facades | IMG_3936.JPG.jpeg | 12 | 0 | 31 |

The complete original → organized filenames, gallery order, checksums, French/Arabic titles, captions, video posters and individual review reasons are in `config/client-projects-map.json`. Originals use `cover.<ext>`, `gallery/NN.<ext>`, `videos/NN.mp4` and `_review/` inside the normalized `projects/` folders.

## Review and uncertainties

- 58 files preserved in `_review`: screenshots, soft/obstructed views, redundant views, reserve selections and the items below. Reserve does not mean corrupt or unusable. No corrupted source was detected. Several supplied views are low-resolution; no detail was fabricated.
- Exact duplicate pairs: `IMG_3985.JPG.jpeg` / `IMG_4017.JPG.jpeg`; `IMG_3994.JPG.jpeg` / `IMG_4026.JPG.jpeg`.
- `IMG_1639.PNG` is an unrelated car screenshot.
- `IMG_3327.JPG.jpeg` has another watermark; `IMG_4061.MP4` has a TikTok watermark. Ownership/usage confirmation is required before public use. No watermark was removed.
- Folders contain multiple different sites. Public records explicitly describe themed collections, not a claim that every view depicts one building. Location, year, area, named clients, installed product assignments and material specifications remain unset.
- Bni Khiran is retained from the supplied folder name; no new commercial product identity or product relationship was invented.

## Import and editorial

- Initial dry-run: 60 accepted, 0 skipped, 0 failed.
- First private apply: 52 accepted, 8 blocked by the decoding memory budget; nothing published at that stage.
- Reviewed preparation/resume: 8 accepted, 52 already-imported sources safely skipped, 0 failed. Final outcome: 60 approved assets and 5 published collections.
- Existing three illustrative project records retained as drafts; no deletion.
- Confirmed copy: more than 150 satisfied clients, 100% Moroccan stone, more than 10 years of experience, and the supplied company description. French and Arabic copy added to Home, About, Services and footer; existing unrelated editorial and contact settings retained.
- SEO titles/descriptions updated for all six public page types. Project detail descriptions, canonical links, enabled-locale alternates and Open Graph/Twitter image metadata use approved published media. This does not claim indexing, ranking or deployment.

## Gallery and verification

- Photo/video thumbnails, selected state, counts, previous/next controls and fullscreen native dialog. Escape closes and returns focus; dialog traps focus; directional keys respect RTL; native video seeking keys remain available.
- Videos use controls, playsInline and preload=none, never autoplay. Changing selection, closing the viewer or hiding the tab pauses playback.
- Build and TypeScript passed; targeted ESLint passed; Pint passed.
- 7 relevant frontend tests passed, including the two new source/provenance and mapping tests.
- 21 database-free backend locale/guard tests passed (21 assertions). No RefreshDatabase fixture tests were run against the populated preview.
- 2 new Chromium tests passed for French/Arabic, gallery focus/keyboard, no autoplay/private or illustrative media requests, SEO and widths 390/430/768/1024/1440.
- Read-only backend verification: 60 approved published assets with private originals; 5 public collections per locale; 10 catalogue tables and contact/WhatsApp settings unchanged.
- HTTP checks: video range request returned 206 and 1024 bytes; published images use immutable caching; private-original public route returned 404.
- Browser screenshots saved under `.tools/client-projects/` for projects, galleries, homepage facts and About in French/Arabic at 390px and 1440px. Mobile/desktop gallery arrow/layout issues identified in comparison were corrected.

## Operational notes

`scripts/import-client-projects.php` is a guarded local integration runner. It refuses a repeat after success, preserves the pre-apply snapshot, validates a clean importer dry-run and uses existing approval/publication/editorial writers. It will not apply against a production database. Its staging files and database snapshot live in ignored `.tools/client-projects/`; the source originals remain in the organized `projects/` folder.

No M6 viewer, M7 quotation logic, stock, price, sizes or unrelated admin changes were made in this integration.
