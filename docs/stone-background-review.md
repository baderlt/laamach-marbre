# Premium interactive stone background

## Scope and design

Implemented the supplied HTML demo's softened photograph, localized detail reveal, warm architectural light and restrained pointer parallax. The background now uses the subsequently supplied `Mur de pierres en lumière dorée.png` wall image, showing more individual stone pieces than the previous `bbcheehb.jpeg` close-up. Source files remain unchanged in Downloads. No stock replacements, fabricated facts, database changes or admin styling were introduced.

UI UX Pro Max was read and applied alongside the existing design system. Its generic glass recommendation was rejected in favour of the client's approved restrained direction. Existing French serif/sans typography, self-hosted Arabic Cairo, gold actions, navigation, server-rendered content, products, projects and quotation rules remain. The optional kinetic/voxel examples informed pointer easing; their continuous canvas loops, synthetic geometry, stock-image instructions and extra scaffolding were not needed. The existing Next.js/Tailwind/TypeScript structure already supports the effect in `apps/web/src/components`; shadcn and a new UI folder are not prerequisites.

## Architecture and files

- `apps/web/src/components/stone-background.tsx`: one decorative, non-interactive fixed layer mounted by the locale layout. Pointer positions update CSS variables through refs, not React state. Time-based interpolation stops within 0.15px of its target; movement is bounded to roughly 5px horizontally and 4px vertically. A 280px soft mask reveals the matching sharp crop, with a low-opacity warm light.
- `apps/web/src/app/stone-background.css`: responsive photo crops, context-specific contrast surfaces, subtle blueprint lines, readable desktop/mobile/RTL compositions. The photo is shared behind hero, paper surfaces and final contact sections. No animated blur, backdrop filter, WebGL or added animation library.
- `apps/web/src/app/[locale]/layout.tsx` and `apps/web/src/app/layout.tsx`: single shared mount and stylesheet.
- `apps/web/src/components/page-design.tsx`: authentic shared stone in brand/catalogue heroes and missing-media editorial heroes; existing real project cover media remain distinct.
- `apps/web/src/components/site-footer.tsx`: shared stone contact treatment when no approved editorial image is available; actual editorial media remain supported. Removes the former illustrative hero/banner image substitution from these surfaces.
- `apps/web/src/app/[locale]/products/page.tsx`: contact section moved below the catalogue's paper surface so it can share the global photo.
- `apps/web/next.config.ts`: year-long immutable caching for content-hashed background WebPs.
- `scripts/prepare-stone-background.mjs`: reproducible local Sharp processing; EXIF orientation, proportional centre crops, resize, fixed soft blur on base variants, WebP and metadata removal. No recolouring. Run with an explicitly supplied client source path.
- `apps/web/public/stone-background/`: five active hashed variants and provenance/checksum manifest; previous immutable variants retained. No private original or EXIF is shipped. Phone base 65,236 bytes, tablet 54,908, desktop 61,738; optional detail crops 272,298/414,330 bytes.
- `tests/browser/stone-background.spec.ts`: browser integration and lifecycle/fallback coverage.

Media-qualified preloads select only the current viewport's base. The detail image is requested only after eligible mouse movement. CSS backgrounds avoid image-element hydration/preload ambiguity. One layer survives client route navigation; effect listeners and observers are cleaned up/rebound on route changes. Cards and controls receive pointer input normally.

Touch/no-hover, reduced-motion, reduced-data/save-data, slow 2G and reported memory at or below 2GB receive the static treatment. Hidden tabs cancel animation. An IntersectionObserver stops it when expressive stone surfaces are offscreen. Form fields, dialogs, product viewers and project galleries are excluded from atmospheric pointer movement. Preferences can change while the page is open; JavaScript-disabled browsers retain the photograph and server-rendered content.

## Verification

- Next.js production build, TypeScript and ESLint pass.
- Existing frontend logic tests: 28 passed, covering catalogue, locales, media mappings, projects, viewer configuration and quotation rules.
- New Chromium tests: 8 passed. FR/AR eight routes at 360, 390, 430, 768, 1024 and 1440px; no horizontal overflow or page errors; catalogue search/filter and mobile menu/Escape/focus; product gallery; current quotation availability remains enforced. No private-original or automatic GLB requests, no autoplay video.
- Lifecycle checks confirm easing settles and RAF requests stop, offscreen/hidden cancellation, dynamic reduced-motion changes, touch, save-data, low-memory and no-JavaScript fallbacks. Static profiles request exactly one base image and no detail crop; immutable response header verified.
- Tests operate read-only on the populated preview. No database fixture reset, business-data import, migration or user-account changes. Backend tests were not rerun for this frontend-only task.
- Original file checksum checked; isolation and diff checks pass. Earlier uncommitted admin work is excluded from this feature's commit.

## Visual evidence

Local evidence is kept in `.tools/stone-background/`, outside the source commit. `before/` and `after/` contain the FR/AR homepage, catalogue and Taza Beige detail at 1440px and 390px, plus viewport captures. `before/approved-demo.png` captures the supplied demo. Final `after/spotlight-1440.png`, `after/contact-1440.png` and `after/ar-mobile-viewport.png` show the interaction and mobile composition. Full-page screenshots cannot reproduce a fixed background below the capture viewport; the scrolled contact viewport is the authoritative check for that section.

## Performance profile and limitations

The following Lighthouse measurements concern the previous `bbcheehb.jpeg` background; they were not rerun for the wall-image replacement. Lighthouse 13.5, local Windows Chromium production builds, same API/preview data, 390×844 mobile emulation, DPR 1, simulated 150ms RTT / 1,638.4 Kbps / 4× CPU slowdown. Baseline and final measurements run sequentially without a concurrent build. Reports are in `baseline/lighthouse-{fr,ar}.json` and `final/lighthouse-{fr,ar}.json`. These are individual laboratory runs, not field INP or physical-device certification.

| Homepage | Performance before → after | LCP before → after | TBT before → after | CLS before → after | Accessibility before → after |
| --- | --- | --- | --- | --- | --- |
| French | 57 → 89 | 16.39s → 2.99s | 730ms → 282.5ms | 0 → 0 | 96 → 100 |
| Arabic | 58 → 87 | 17.20s → 3.58s | 662ms → 164ms | 0 → 0 | 96 → 100 |

The 90+ mobile performance and sub-2.5s LCP targets are not fully met under this profile. The feature substantially improves the measured preview baseline, but the remaining whole-page loading/JS cost needs separate profiling; these single runs cannot establish field INP or universal hardware performance. Lighthouse accessibility 100 is an automated result, not a WCAG certification. Browser snapshots additionally show an existing Arabic desktop detail font/layout shift of approximately 0.093 before and after; mobile snapshots show 0.

Real mobile hardware, Safari and a deployed CDN have not been tested. Existing illustrative project cards and provisional business facts remain clearly labelled in the isolated local preview; this task changes the background rather than approving those records. The current preview has quotation configuration unavailable, so browser checks verify its honest contact fallback; existing quotation unit tests pass. No actual 3D product is enabled or invented by this visual change.
