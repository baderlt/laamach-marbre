# M8.5 verification — PASS locally

Scope: local admin experience and reliability only, on `codex/m85-admin-experience`, based on M8 `b26a332d3fd5dddb1bd01fdc426558aaddc6fea0`. No M9, production deployment/publication, R2, DNS or VPS operation. The final handoff supplies this milestone's commit hash.

## Cover failure and corrected workflow

The initial audit and real failing workflow are recorded in [m85-audit.md](m85-audit.md). Existing cover selectors already supported inline FilePond uploads. A newly imported private/pending asset was immediately selected, but the page writer correctly rejected it because section images must be approved, published client-supplied M2 media. Its domain error used a key outside the visible nested field, leaving an unexplained open editor. Validation ran before reference replacement, so the previous database cover remained intact.

The corrected workflow is:

1. Select existing eligible media, or use **Importer / رفع ملف** to upload a genuine allowed image.
2. Confirm client provenance. Temporary-transfer completion is explicitly distinguished from publication.
3. A normal upload remains private. Slots requiring published media retain their previous selection and show a persistent pending notice with the next action and a Media Library link in a new tab.
4. An authorized super-admin approves public use and publishes the processed revision through M2. Alternatively, the inline form offers an explicit, unchecked-by-default approval/publication checkbox to that role.
5. Assign the eligible asset, save, reload and inspect the current preview. Draft products retain their existing ability to attach ready private client images.

The callback uses Filament's injected mounted Select, rather than a captured form definition. Processing generates missing requested profiles and publishes the selected new revision even if an older revision was already active; originals and old published variants remain retained. Domain validation failures are surfaced in a localized notification without relaxing writer/policy checks.

## Admin interface and content

Navigation groups website content, product catalogue, projects, media and settings. Forms retain their existing storage paths while grouping details, FR/AR content tabs, images, optional dimensions, SEO and advanced viewer/settings fields. Current cover/gallery/poster/size images have authenticated previews. Repeater order has button alternatives to dragging. Media Library adds a standalone image entry point, processing/approval/publication labels, filters, usage details and a labeled action menu. Less frequently used media metadata is available through column controls and inspection.

Dashboard metrics query actual products, projects, categories, media and pages. Published/draft counts, pending/failed media and recent edits are grounded in stored records; no sales, revenue, quotation or stock metrics were invented. Dashboard widgets do not poll continuously.

Admin language is a bounded `fr`/`ar` user preference; a nullable additive user column does not change public defaults or another account. Official Filament localization is used, with owned custom/validation strings and targeted missing accessibility-label overrides. Arabic uses RTL navigation, forms, dialogs and tables. Content language remains independent of interface language.

## Profile, contacts and authorization

My Profile reuses Filament's authenticated edit-profile page. Current password is required for email/password changes; email uniqueness, confirmation and a minimum 12-character mixed-case/numeric/symbol password are validated. Only own name/email/password can be updated. Hashing stays with Laravel; remember tokens rotate, other database sessions for that user are revoked and the current session identifier regenerates. Role, active state and foreign-user input cannot be changed through this page. Existing login/profile rate limits remain.

Contact & Social Settings is super-admin-only, enforced again in the writer. Main phone/email/map/address/footer, approved logo and supported HTTPS social profiles are optional. Facebook, Instagram, TikTok, YouTube and LinkedIn hosts are allowlisted; unsafe schemes/credentials and invalid phone values fail validation. Existing settings/translation keys are merged rather than discarded.

The primary M7 quotation number and its per-flow flags stay separate from additional displayed contacts. Additional numbers normalize to fixed `https://wa.me/<digits>` links and never participate in quotation routing. Public DTOs expose only published eligible settings; missing/disabled primary numbers hide their actions. Footer/contact additions use the existing public design and remain absent when no additional contact is configured.

## Browser evidence

All destructive fixtures run only on a dedicated loopback MySQL 3308 instance and separate private/published storage. The reset helper checks actual port, database and resolved data directory before any reset. Browser fixture helpers require synthetic catalogue/project records and a test manifest; there is no public fixture endpoint.

The real FilePond matrix covers ten slots: homepage hero, secondary image, website section, product cover/gallery, category cover, project cover/gallery, video poster and global logo. Each slot uploads file A, explicitly publishes/assigns/saves/reloads, then replaces with file B and verifies the stored reference and retained original checksum. A fresh pending upload separately verifies the previous cover remains selected, private status is honest, and explicit Media Library approval/publication works. FR/AR public DTOs and actual derivative HTTP responses verify eligible delivery. Existing M2 tests retain anonymous/private rejection and invalid-format/forged-publication checks.

The profile/contact browser workflow edits Arabic content while preserving French, saves and reloads, publishes distinct primary/additional contacts and a social link, checks their FR/AR public links, changes a synthetic administrator's email/password, logs out and logs in with the changed credentials, verifies persisted Arabic preference and rejects the content-manager settings route.

Browser harness corrections wait for the visible Filament modal window, Livewire nested controls, FilePond's initialized input and `_finishUpload`, actual profile-save confirmation, and real menu roles. Refreshing before a sensitive save completed discarded a rotated session cookie in an early test; final verification waits for completion. These are not claims of additional baseline application defects. An early public run used an unused API environment variable and mixed real-content specs with synthetic fixtures; those results are excluded from the final ledger.

## Final check ledger

| Check performed | Measured result |
|---|---|
| Full Laravel suite, actual isolated MySQL 3308 | **217 passed, 1,087 assertions** |
| Frontend unit suite | **43 passed; no failures/skips** |
| Existing Chromium regressions | **79 passed** in two clean final groups: 42 synthetic and 37 frozen real-content cases |
| Dedicated admin Chromium suite | **5 case definitions passed cumulatively**: responsive/axe, ten-slot upload replacement, content/contact/profile/role workflow, actual public delivery, keyboard/dark mode. The main run passed four; the corrected keyboard selector passed its targeted rerun. The final strict matrix and contact-preservation/profile flow were rerun after fixes; no skipped cases |
| Responsive admin | **84 screen checks**: seven scenarios × six required widths × FR/AR; no page-level horizontal overflow. Wide tables scroll internally |
| Admin axe 4.14.0 | **32 checks, zero automated violations**: 28 light-mode checks at 390/1440 plus four dark-mode checks. WCAG 2 A/AA, 2.1 AA, 2.2 AA and applicable best-practice rules; incomplete/manual results remain available in private evidence |
| Actual image-slot workflow | **10 slots, 20 save/reload assignments**, plus a fresh pending-upload/approval/publication test; old originals/checksums retained |
| Production build / TypeScript / ESLint | **Passed**, Node 24, Next 16.4.0; generated Next environment/type configuration restored rather than committed |
| Complete Pint | **Passed**. M8's old ProjectForms formatting finding is now resolved in a file legitimately changed for this milestone |
| Composer validation | Schema/lock valid. Strict exit **1** only for the two existing exact-pin recommendations: Filament 5.10.1 and Laravel 13.35.0. Dependencies/pins were not changed |
| Source isolation / diff check | Passed: **669 source files**, one intentional forbidden-database fixture; no external source links or tracked private environment. `git diff --check` passed |
| Reference/client input integrity | **255 reference files + 145 client inputs**, no hash differences |
| Local additive migration integrity | **28 tables compared; no existing data-row differences**, except the expected migration ledger entry and the new nullable user field. **80 current stored originals unchanged** |

The backend review found and fixed a JSON object-key ordering issue in the new contact guard. Comparisons preserve strict scalar values and contact-list order while ignoring database normalization of object-key order. Tests prove omitted values survive unrelated editorial edits, unchanged social entries remain editable, and a content manager cannot reorder protected contacts. Explicit empty lists/nulls still support authorized clearing.

The existing M7 omission test now edits ordinary footer content rather than attempting to change email: email is newly protected by the explicitly requested M8.5 super-admin settings boundary. Its original quotation-number preservation and forged-change rejection assertions remain.

## Integrity and limits

The M8 integrity manifest confirms all 255 reference-checkout files and 145 client input files are byte-identical. A read-only check of the original populated preview confirms all 79 stored client originals retain their recorded hashes. Six tables differ from the older M8 SQL snapshot (`cache`, media assets/references/variants, sessions and site content); that old snapshot is not an M8.5 start-of-turn database baseline. After all destructive QA completed, the additive preference migration was applied to the verified local preview on 3306. A fresh before/after comparison found zero changes to existing rows across 28 tables (existing user fields included), one expected migration ledger row, and identical hashes for all 80 currently stored originals. No synthetic M8.5 account/image or locale column existed in that preview during destructive QA. The separate QA copy was restored after the destructive suites. The local launcher then confirmed 7 published products and 5 published project collections; it does not seed, migrate, copy or reset data.

Local Chromium emulation and automated scans do not establish physical-device, screen-reader, formal WCAG certification, production FPM/proxy/TLS, live R2/CDN, distributed throttling or production delivery success. Existing M8 production readiness/performance limitations remain; no new Lighthouse claim is made for M8.5. Private credentials, SQL, logs, browser traces and genuine originals remain under ignored local QA storage and are not release artifacts.

## Screenshots and visual review

These captures show synthetic QA content, not claimed client performance or business metrics. The seven primary admin scenarios were checked visually in representative FR/AR desktop/mobile captures. The action overlap, low-contrast descriptions/badges/private placeholders, small remove controls and modal-footer overlap found during review were corrected. Keyboard Enter/Escape, focus restoration, visible save controls and RTL/dark layout were exercised in Chromium.

- [French dashboard, 1440px](assets/m85/dashboard-fr-1440.png)
- [Arabic dashboard, 1440px](assets/m85/dashboard-ar-1440.png)
- [Arabic dashboard, 390px](assets/m85/dashboard-ar-390.png)
- [Arabic website editor, 390px](assets/m85/website-ar-390.png)
- [French product editor, 390px](assets/m85/product-fr-390.png)
- [Arabic project editor, 1440px](assets/m85/project-ar-1440.png)
- [French Media Library, 1440px](assets/m85/media-fr-1440.png)
- [Arabic Contact Settings, 390px](assets/m85/contacts-ar-390.png)
- [French My Profile, 1440px](assets/m85/profile-fr-1440.png)
- [Arabic My Profile, dark mode](assets/m85/profile-dark-ar.png)

## Handoff

Local admin: http://127.0.0.1:8101/admin. Website: http://127.0.0.1:3105/fr and /ar. Existing real administrator credentials, roles and catalogue/project content were preserved. The only real schema change is the nullable admin-language preference field. No real account password/email was edited by the browser tests.

**M8.5 local acceptance: PASS. Production deployment: not performed; existing M8 production gates remain. Stop after M8.5.**
