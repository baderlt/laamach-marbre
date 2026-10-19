# Premium Interactive Stone Background — verification report

**Overall: PARTIAL.** Functional, fallback, asset-deferral, and local visual checks pass. Current mobile LCP and some blocking/paint metrics remain above desirable levels; a sustained 60 fps result, physical devices, and production CDN behavior are not verified.

## Evidence and test conditions

- Saved baseline: `.tools/stone-background/baseline/lighthouse-{fr,ar}.json`, October 18, 2026, 16:30 UTC, localhost port 3104.
- Saved final: `.tools/stone-background/final/lighthouse-{fr,ar}.json`, October 18, 2026, 19:58 UTC, localhost port 3105. These reports predate the approved `bg new.jpeg` replacement and subsequent homepage changes.
- Current verification: `.tools/stone-background/current-2026-10-19/verified-final/run-{1,2,3}/lighthouse-{fr,ar}.json`, October 19, 2026, 20:32–20:34 UTC, isolated Next.js production build on localhost port 3106, using the local API on 8101.
- Lighthouse 13.5.0; mobile viewport 390 × 844, DPR 1; simulated 150 ms RTT, 1,638.4 Kbps throughput, 4× CPU slowdown. All three current runs per locale are retained; medians below are calculated independently per metric.
- Local headless Chromium, not an actual phone. The current runs reported CPU benchmark indices ranging from 610.5 to 2,449.5, so score/timing variation is material.
- An earlier pre-compositing production audit scored 83 FR / 62 AR (TBT 450 / 1,045.5 ms). Its raw reports remain in the parent `current-2026-10-19` directory; they are not silently replaced or mixed into the final-build medians.
- Historical and current pages differ in approved content, images, branding, and layout. These are actual page measurements, not a controlled experiment isolating the background’s contribution.

## 1. Saved baseline versus saved final Lighthouse results

Times are seconds except TBT, which is milliseconds. CLS is unitless. Values are extracted from the numeric audit results, rather than rounded Lighthouse display labels.

| Locale | Snapshot | Performance /100 | LCP s | CLS | TBT ms | FCP s | Speed Index s |
|---|---|---:|---:|---:|---:|---:|---:|
| FR | Baseline | 57 | 16.393 | 0 | 730 | 1.270 | 1.711 |
| FR | Saved final | 89 | 2.995 | 0 | 282.5 | 1.131 | 1.711 |
| AR | Baseline | 58 | 17.196 | 0 | 662 | 1.144 | 1.718 |
| AR | Saved final | 87 | 3.584 | 0 | 164 | 1.962 | 1.962 |

LCP improved by 81.7% FR and 79.2% AR. TBT improved by 61.3% FR and 75.2% AR. **Historical regressions:** Arabic FCP increased by 817.5 ms (+71.4%) and Speed Index by 244.2 ms (+14.2%). French Speed Index changed by only +0.103 ms, effectively unchanged at this precision. CLS remained zero.

## 2. Current production-build Lighthouse results

| Locale | Run | Performance /100 | LCP s | CLS | TBT ms | FCP s | Speed Index s |
|---|---|---:|---:|---:|---:|---:|---:|
| FR | 1 | 91 | 3.069 | 0 | 190.5 | 1.232 | 2.227 |
| FR | 2 | 81 | 3.387 | 0 | 417.022 | 1.249 | 2.663 |
| FR | 3 | 83 | 3.287 | 0 | 397.791 | 1.237 | 2.498 |
| **FR** | **Median** | **83** | **3.287** | **0** | **397.791** | **1.237** | **2.498** |
| AR | 1 | 84 | 3.930 | 0 | 133.5 | 2.130 | 3.220 |
| AR | 2 | 82 | 3.903 | 0 | 212.5 | 2.121 | 2.525 |
| AR | 3 | 84 | 3.808 | 0 | 174 | 2.115 | 2.115 |
| **AR** | **Median** | **84** | **3.903** | **0** | **174** | **2.121** | **2.525** |

All six current reports have accessibility score 100, no Lighthouse runtime error, and no run warnings. This is an automated audit result, not a complete accessibility certification.

Compared with the saved final, current median performance is lower by 6 points FR and 3 points AR. Current median LCP is higher by about 292 ms FR and 319 ms AR; Speed Index is higher by about 788 ms FR and 563 ms AR. French median TBT is also higher by about 115 ms. These regressions remain visible in the report. Main-thread diagnostics attribute prominent tasks to React/application hydration, document/style/layout work, and other scripts; the background is static in the mobile tests. Attribution does not prove that every timing difference has one cause. No speculative hydration/font redesign was introduced to improve the score.

## 3. Transfer and image requests

The following are Lighthouse network transfer bytes, including recorded response overhead, for the measured navigation. Image requests include CSS background images; counts are not restricted to HTML `img` tags. No full-page scroll or pointer activation occurs during these mobile audits.

| Locale | Snapshot | Total transferred bytes | All requests | Image requests | Image transferred bytes |
|---|---|---:|---:|---:|---:|
| FR | Baseline | 3,778,497 | 17 | 7 | 3,593,756 |
| FR | Saved final | 995,460 | 18 | 7 | 806,742 |
| FR | Current, each of 3 runs | 891,276 | 28 | 14 | 654,709 |
| AR | Baseline | 3,961,134 | 21 | 9 | 3,630,840 |
| AR | Saved final | 1,177,987 | 22 | 9 | 843,826 |
| AR | Current, each of 3 runs | 1,061,441 | 31 | 15 | 679,274 |

Saved-final total transfer fell by 73.7% FR and 70.3% AR versus baseline. The baseline included a 2,871,552-byte courtyard PNG; its replacement explains much of the reduction. Current pages request more, smaller image assets and thumbnails; fewer transferred bytes must not be described as fewer image requests.

**Deferred crops — PASS locally:**

- Both saved-final audits and all six current mobile Lighthouse runs contain **zero sharp-detail requests**.
- Current FR and AR desktop probes each request **one base image initially**, then **one desktop detail crop only after permitted pointer movement**. Subsequent probe movements do not request another copy.
- A separate cold-context check at 920 and 1440 px in both locales confirms one matching base request followed by exactly one tablet/desktop detail request after repeated pointer movement; evidence is `verified-final/detail-reuse.json`.
- Current mobile probes request one base image and no detail crop, including after emulated pointer input.
- The final static-mode tests pass for touch, tablet/reduced motion, reduced motion, Save-Data, low-memory, and JavaScript disabled. Save-Data and low-memory were simulated, not physical-device measurements.
- Current file payloads, distinct from network transfer totals: mobile base **96,272 bytes**, tablet base **75,106**, desktop base **86,944**, tablet sharp crop **429,006**, desktop sharp crop **449,602**. The mobile base transfers **96,718 bytes** in the current Lighthouse reports.
- Local immutable cache headers were checked. Production/CDN cache hits, compression, and delivery were not tested.

## 4. Scrolling, pointer movement, and fixes

Two confirmed issues were corrected without changing business logic or CMS content:

1. **Duplicate tablet background download.** At 920 px, the base used the tablet image while the header requested the desktop image. The header now uses the matching tablet asset at 768–1199 px. The final cold-context tablet test confirms one background request, avoiding the extra 86,944-byte desktop file payload.
2. **Expensive moving gradient masks.** A production control probe measured a 16.7 ms median frame interval with the background hidden versus 49.9 ms with it active (active p95 66.7 ms, maximum 116.7 ms). The spotlight now uses bounded static gradient tiles with composited translation and counter-translated sharp imagery, instead of repainting a moving full-viewport gradient. Fully transparent bounds were trimmed; photo, gradient stops, blur, glow, parallax, and texture alignment were retained. A matching 500 × 400 pixel spotlight crop before/after had **zero mean absolute channel difference across 600,000 channel samples**. This is evidence for that sampled region, not an exhaustive pixel comparison of every state.

Final production probe results during combined pointer movement and small alternating scrolls:

| Locale | Sampled frame intervals | Median ms | p95 ms | Maximum ms | Intervals >50 ms | Observed long tasks |
|---|---:|---:|---:|---:|---:|---:|
| FR | 90 | 16.7 | 33.4 | 50 | 0 | 0 |
| AR | 89 | 16.7 | 33.4 | 50 | 0 | 0 |

After easing settled, each locale scheduled **zero additional requestAnimationFrame callbacks during a 700 ms idle observation**. The temporary frame sampler was started after this idle measurement, so its own callbacks are not included in the zero-idle assertion. Offscreen stopping passed in both probes. Focused tests also passed simulated hidden-document cancellation and live reduced-motion changes. The hidden-document test dispatches a simulated visibility event; it is not a real operating-system background-tab power measurement.

The final measurements show a substantial improvement, but p95 intervals of 33.4 ms and a 50 ms maximum do **not** establish a sustained 60 fps experience. Long-task observations also exclude compositor/GPU work. Smoothness therefore remains **PARTIAL**, pending physical-device and real GPU/browser checks.

## 5. Appearance and readability

Reviewed existing baseline/final screenshots, the latest approved hero screenshots, and fresh final-production FR/AR captures at 1440 × 1000 and 390 × 844. Dark hero overlays keep light text legible; content uses light paper surfaces; the replacement stone photo remains recognizable. French wraps naturally, Arabic uses its RTL layout and Arabic font, and desktop side navigation clears the heading. No text clipping or horizontal overflow was found in the reviewed states.

The final homepage checks cover widths **320, 390, 430, 768, 920, 1024, 1242, 1280, 1440, and 1920 px**, equal banner-card dimensions, balanced gutters, actual image loading, preserved project links, reduced-motion presentation, and rendering without JavaScript. The fixed WhatsApp control remains visible and can overlap decorative card edges at some scroll positions; it was not redesigned. Screenshot inspection is browser-rendered visual evidence, not an instrumented contrast audit or physical-phone review.

Fresh screenshots are in `.tools/stone-background/current-2026-10-19/verified-final/{fr,ar}-home-{1440,390}-{viewport,full}.png`. Historical screenshots in `.tools/stone-background/before` and `after` depict earlier photos/content and must not be presented as the current design.

## 6. Performed versus unverified

**Performed:** production build and TypeScript check; targeted ESLint and diff checks; 10 final focused Chromium tests passed; six final mobile Lighthouse runs; FR/AR desktop/mobile network and frame probes; idle, offscreen, reduced-motion and simulated hidden-document checks; six static fallbacks; local cache-header checks; screenshot review and sampled pixel-equivalence comparison. Raw current Lighthouse extraction is `verified-final/lighthouse-summary.json`; interaction and network results are `verified-final/interaction-network.json`; pre-fix evidence and the pixel comparison are retained in the parent `current-2026-10-19` directory.

**Unverified:** physical Android/iOS devices, Safari/Firefox, real 60/120 Hz GPU behavior, battery/thermal impact, production hosting/CDN/cache delivery, real mobile networks, field Core Web Vitals, and production concurrency/load. The broad all-public-routes test in `stone-background.spec.ts` was not rerun in this final focused selection. No database changes, business-logic changes, or full backend test suite were required for these fixes.

## Final verdict

| Area | Result |
|---|---|
| Deferral, asset reuse, static fallbacks, idle/offscreen stopping | **PASS locally** |
| Reviewed FR/AR appearance and responsive behavior | **PASS in tested Chromium states** |
| Continuous pointer/scroll smoothness | **PARTIAL** — improved, but frame outliers and no physical-device evidence |
| Mobile performance | **PARTIAL** — current medians FR 83 / AR 84, LCP 3.287 / 3.903 s; regressions disclosed |
| Production/CDN and real-device readiness | **UNVERIFIED** |
| Overall | **PARTIAL** |
