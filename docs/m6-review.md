# M6 — Interactive product visualization

Local implementation, 18 October 2026. Scope: M6 only. No deployment or unrelated design revision. The commit containing this report is the M6 commit; its exact hash is supplied in the handoff.

## Administration and database

The additive migration `2026_10_18_000007_add_product_3d_configuration.php` adds `products.viewer_3d_enabled` (boolean, default false) and nullable JSON `products.viewer_3d_config`. There are no new business tables. Existing products retain their facts, photographs, sizes and publication state; their viewers remain disabled.

Product → **3D Visualization** supports enable/disable, mode, GLB upload/assignment/revision/replacement/removal, optional poster, block/slab geometry, verified fixed dimensions or complete assigned sizes, explicit units, representative texture, roughness, initial camera, orbit range and zoom limits. Saved configuration can be previewed before publication. Model options show filename, bytes and approval/publication state; revision options show validation version. Upload is also available in Media.

Disabling retains the saved configuration and media ownership references, removes the public activation control and returns `viewer_3d: null`. Removal releases the reference without deleting media. Replacement uses another immutable asset/revision. Existing M2 deletion protection remains authoritative. Draft configuration is retained when disabled; server validation applies regardless of field visibility.

`CatalogueWriter` remains the write boundary and uses the existing transaction and authorization rules. Viewer references use `media_references.owner_type = product_viewers`, keeping viewer eligibility independent of product photograph eligibility. Invalid/unpublished viewer assets suppress the viewer without hiding the product gallery.

## GLB validation and M2 lifecycle

An actual temporary FileUpload is required. Extension/browser MIME alone is insufficient. `ModelLibrary` validates before storage, keeps the original private, and produces a private validated derivative. Approval/provenance review and publication remain separate existing M2 operations. Content managers cannot grant themselves publication privileges.

The published derivative uses `models/{asset-id}/glb-v{validator-version}-{revision-prefix}/{sha256}.glb`, correct `model/gltf-binary` MIME, a stable CDN URL and immutable caching. A replacement never overwrites an existing object. Public local delivery checks the stored hash and includes CORS for cross-origin frontend rendering. Private originals are never exposed; authenticated preview responses are `private, no-store`.

Processing preserves binary geometry, normals, UVs and compatible embedded materials/textures. It strips nonessential JSON extras and bounds textual metadata. It does not run an external converter, decimate meshes, create models from client photos, or alter the original private file.

Default enforced limits in `apps/api/config/viewer.php`:

| Resource | Limit |
| --- | --- |
| GLB / JSON | 8 MiB / 1 MiB |
| Vertices / triangles | 100,000 / 100,000, including instance budgets |
| Primitive draws / nodes / accessors | 64 / 128 / 256 |
| Embedded textures | 8, PNG or JPEG only |
| Texture edge / aggregate decoded pixels | 2,048 px / 8,388,608 pixels |
| Decoded scalar components / scene depth | 1,500,000 / 16 |
| Validation time / additional PHP memory | 8 seconds / 128 MiB, checked during bounded parsing |
| Public procedural texture | Approved processed variant, at most 1,600 px and 4 MiB at runtime |

The parser checks GLB magic/version/exact length, aligned JSON and BIN chunks, JSON shape/depth, embedded buffer ranges, accessor offsets/strides/alignment/component values, finite positions, min/max bounds, indices, triangle topology, scene/node references, cycles, transforms, material/sampler/texture references, full image decoding and resource budgets. External URLs, data URIs and other external resources are rejected. Unsupported required/used extensions, compressed meshes, sparse accessors, animation, skinning and camera assets are rejected. Supported extensions are `KHR_materials_clearcoat` and `KHR_texture_transform`.

This is a deliberately bounded static subset of [glTF 2.0](https://registry.khronos.org/glTF/specs/2.0/glTF-2.0.html), not a claim of complete specification conformance or proof of GPU safety. Models outside this subset require preparation by their author; no silent destructive conversion occurs.

## Procedural geometry, sizing and materials

Supported shapes are a rectangular block and a slab/panel. Fixed dimensions require explicit administrator verification, positive width/length/depth and a supported unit. Alternatively, only active assigned M3 sizes with complete measurements are eligible. Block depth comes from size height; slab depth comes from thickness. The configured default must be an eligible assigned size.

Scene units are metres: mm × 0.001, cm × 0.01, m × 1. Axes must be between 0.000001 and 100 metres. No missing thickness, unit or measurement is invented. Labels such as “4” remain labels. Incomplete selected sizes return the customer to photographs and explain why 3D is unavailable for that selection. Size changes update supported procedural proportions and the existing M7 selected-size context.

Uploaded GLB geometry remains unchanged for all quotation size selections. Camera framing/centering does not rescale vertices. There is no implicit calibration, per-size model fabrication or custom-dimension simulation.

The shared renderer uses sRGB texture/output handling, ACES tone mapping, neutral lights, a small generated studio environment, bounded filtering/DPR, nonmetallic procedural stone and configurable roughness (default 0.8). Desktop shadows use a 512px map; mobile/coarse-pointer shadows are disabled. Procedural texture is optional and representative. No external environment/stock imagery is downloaded. Uploaded compatible PBR material parameters are retained. Commercial finishes remain quotation facts and do not automatically change rendered materials.

## Public API, preview and controls

Only product **detail** responses receive optional `viewer_3d`. Published product eligibility, enabled flag, validated mode/settings, approved client-supplied assets and active published model revision are required. Catalogue/list responses contain no viewer configuration. No private storage keys or arbitrary model URLs are returned.

`/admin/product-viewer/{product}` authorizes the existing product policy and renders saved settings, including draft/private models and disabled configurations. `/admin/viewer-model/{variant}` authenticates and authorizes asset access and delivers verified bytes without publishing them. The browser uses a same-origin ESM bundle built from the **same renderer core** as the public website. Invalid saved configuration yields a localized 422 fallback. `npm run build` generates this ignored bundle at `apps/api/public/js/viewer/preview.js`; local `npm run dev` also builds it.

Public photographs remain the initial experience. **Voir en 3D / عرض ثلاثي الأبعاد** explicitly imports the complete Three.js core and loads the model/texture. Controls include drag orbit, bounded zoom, four rotation buttons, reset, supported fullscreen/exit, return to photographs, retry, canvas keyboard arrows/+/-/Home and Escape to photos. Closing cancels work, removes the canvas and releases controls, listeners, observers, geometry, materials, textures, environment, render target and WebGL context. Closing before the dynamic import completes does not initialize a renderer.

Rendering is demand-driven: no auto-rotation, damping or permanent animation loop. Reduced-motion users receive the same nonanimated idle behavior. Model fetch is streamed and bounded, checked against SHA-256/byte count/MIME, and has a 15-second asynchronous deadline. Unsupported WebGL, device context loss, corrupt/unavailable files and network failure retain a photograph fallback with retry/return actions.

Viewer strings are shared by PHP/TypeScript in `config/viewer-messages.json`, with configured locale fallback. French and Arabic RTL include loading, errors, interaction help, control names and honest size/finish disclosures. Physical rotation direction is the same in both directions. Touch defaults to page scrolling; explicit activation enables orbit/pinch, and the control restores scrolling. Coarse tablets retain this control even at desktop breakpoints. Buttons use `touch-action: manipulation` to avoid suppressed taps after a gesture.

The viewer has named keyboard controls, visible focus, 48px minimum toolbar targets, focus return to activation, localized help and HTML dimensions. Product facts and images remain available without the canvas. This targets applicable WCAG 2.2 AA behavior; it is not a complete accessibility certification or a screen-reader/hardware audit.

## Verification evidence

Verification used the dedicated guarded `laamach_marbre_test` database and registered disposable fixtures. The full client preview was snapshotted before testing. No database refresh was performed against the real business database.

| Check | Result |
| --- | --- |
| Full backend | 183 passed, 819 assertions; final rerun 178.21 seconds |
| Frontend unit tests | 28 passed |
| Existing Chromium regressions | 33 passed, including all 9 M7 browser cases |
| New M6 Chromium cases | 9 passed across focused reruns: 6 GLB/procedural/recovery, 2 touch phone/tablet, 1 private admin preview + real FilePond upload |
| TypeScript / ESLint | Passed, zero lint warnings |
| Production build | Passed; separate authenticated admin bundle also built |
| Pint / Composer | Passed; Composer retains existing exact Laravel/Filament version warnings |
| Source isolation | Passed, 257 source files with the existing database-guard test exception; user-installed skills excluded from the source scan |
| Reference repository | 255 snapshot hashes checked, zero changes |
| Client originals | All 22 original asset hashes unchanged |
| Real business database | All 28 tables compared with the pre-migration snapshot: no unexpected row changes; 3 products, all viewers disabled |

The browser tests verify actual canvas geometry/triangles and changed rendered pixels after orbit, deterministic reset, bounded zoom, physical rotation direction in RTL, keyboard commands, fullscreen entry/exit, stable idle frame count, gallery/focus return and canvas disposal. They exercise failed GLB fetch → retry → actual model load, unavailable WebGL, procedural block/slab dimensions and M7 selected-size review text. Touch tests dispatch a multi-frame finger drag at 390/768px, verify changed camera state, restore scrolling and check overflow. All 360/390/430/768/1440 procedural viewports were exercised in both locales.

The authenticated browser test uploads a real synthetic `.glb` through initialized Filament FilePond, finds the persisted media in the searchable table, and checks its pending approval/private state in the guarded fixture helper. A test-environment-only, private, process-isolated `tmp-for-tests` disk was necessary because Livewire selects that disk during HTTP testing. It creates no production disk or public storage link.

Initial-page samples (one loopback/software-rendered run, not a comparative field benchmark):

| Locale / state | LCP sample | CLS sample | Script resources |
| --- | --- | --- | --- |
| FR disabled | 2,280 ms | 0 | 9 |
| FR enabled, unopened | 1,664 ms | 0 | 9 |
| AR disabled | No LCP entry observed within the measurement window | 0 | 9 |
| AR enabled, unopened | 2,184 ms | 0 | 9 |

Every sampled homepage, catalogue, disabled detail and enabled-but-unopened detail fetched **zero GLBs and zero Three.js renderer bundles**. After activation the GLB test fetched one model and detected the renderer bundle. These stronger network assertions establish lazy loading; the noisy timings do not establish a speed improvement. Raw local measurements are `.tools/m6/performance-fr.json` and `performance-ar.json`. The separate minified admin ESM bundle is approximately 659 KB, loaded only by the authorized preview page.

### Screenshots

Browser captures use clearly synthetic QA geometry and fixture textures, not production product representations. FR/AR procedural screenshots cover 360, 390, 430, 768 and 1440px. Additional GLB captures cover desktop, touch phone and touch tablet, with an Arabic private admin preview. The 17 PNG files are local ignored evidence under `.tools/m6/screenshots/`: `{fr,ar}-procedural-{360,390,430,768,1440}.png`, `{fr,ar}-glb-desktop.png`, `{fr,ar}-glb-touch-{390,768}.png`, and `admin-private-model-ar.png`. Inspected captures show the existing ivory palette, wrapped touch-sized controls, readable RTL copy/dimensions and usable model framing. No wholesale prototype redesign was performed. No synthetic QA product/model is retained in the restored client preview.

### Files changed

- API: additive migration and viewer config; `ProductViewer`, `ViewerForms`, `ViewerController`, `GlbValidator`, `ModelLibrary`, private preview Blade; extensions to catalogue writer/DTO/model, existing Filament resources, media library/path/storage/controller/policy and routes.
- Web: `product-viewer.tsx`, `viewer.ts`, shared `viewer-core.ts` and `viewer-admin.ts`, product gallery integration, optional detail type and scoped viewer CSS.
- Build/config: shared localized messages, Three.js/types, esbuild, lockfile, admin bundle build script, test-only private temporary disk and ignored generated output.
- Tests: synthetic model generator, validator/feature tests, guarded browser fixture helper, frontend viewer tests and actual-render Chromium cases. The obsolete “future 3D placeholder” assertion now verifies a disabled product remains photo-only; existing business assertions remain intact.

## Operational limits and acceptance

Production GLB/R2/CDN behavior and physical phones/GPUs were not exercised. Browser 3D evidence uses explicit Chromium SwiftShader software rendering and touch emulation. It cannot establish real-device frame rate, mobile Safari behavior or field Core Web Vitals. Asynchronous timeouts cannot interrupt a synchronous driver/compiler stall; backend parser budgets reduce input cost but do not certify arbitrary GPU workloads.

There are no supplied verified production GLBs or complete verified dimensions for the seven provisional client records. Existing products therefore remain 3D-disabled after testing. An administrator must configure verified dimensions or upload/review/approve/publish a supported model before activating a real product. The feature is functional without assigning fabricated geometry to those products.

Optional per-size uploaded models, model calibration, displacement, animation, arbitrary shader editing and commercial-finish material presets are intentionally absent. No deployment, new payment/account/stock feature, M8/M9 work or unrelated redesign was performed.

Acceptance checklist:

- [x] Additive per-product control; default off; disabling preserves configuration/assets.
- [x] Actual file upload, model assignment/replacement/removal and saved configuration preview.
- [x] Bounded validation, private staging, explicit approval and immutable M2 publication.
- [x] Authenticated private model delivery and no private public DTO exposure.
- [x] Block/slab geometry, explicit units, verified dimensions and complete assigned-size mapping.
- [x] Uploaded geometry remains unchanged; no invented product facts or scanned-geometry claim.
- [x] Click-only runtime/model loading, orbit/zoom/reset/fullscreen and gallery recovery.
- [x] French/Arabic controls, RTL, keyboard alternatives and deliberate mobile gestures.
- [x] M1–M5/M7 regression verification and read-only reference/client asset integrity checks.
- [x] Final browser/static verification complete; synthetic fixtures removed and populated preview restored.

After the final backend run, the post-migration client preview snapshot was restored and all **28 tables compared exactly**, with no differences: 7 products, 0 enabled viewers. This preserves the preview administrator account and all saved editorial/admin data. The separate real business database comparison likewise found no unexpected row changes across 28 tables; all 3 real database products remain disabled. The website preview runs at `http://127.0.0.1:3104` and the admin panel at `http://127.0.0.1:8101/admin`. Existing approved local provisional-preview behavior is retained; M6 adds no new AI imagery or fabricated production models.
