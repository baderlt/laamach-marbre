# LAAMACH MARBRE — M5.2 design system

Authority: approved client prototypes, then the M5.2 brief, then UI UX Pro Max recommendations. All 739 skill lines were read. CLI query `architecture interior luxury --design-system` returned Portfolio Grid, neutral photography-led composition and restrained gold. The first broad query returned industrial orange and was rejected. Typography search returned Classic Elegant and Luxury Serif. Next.js image guidance and UX focus-not-obscured guidance were reviewed. No framework or package replacement is required.

## Client-specific synthesis

Preserve the eight prototype section sequences and desktop density. Do not adopt the generator's exaggerated typography, massive whitespace, hover-only CTAs or unrelated fonts. Reuse self-hosted Cairo for Arabic and the existing French serif/sans pairing. No remote fonts or animation library.

| Token | Value / rule |
| --- | --- |
| Ivory / surface | #faf9f6 / #ffffff |
| Text / secondary | #23221f / #59574f |
| Primary bronze | #79551f, white text; hover #604117 |
| Charcoal | #20211e, text #f7f3eb |
| Control border | #8a8172; subtle decorative divider #e5dfd5 |
| Focus | 3px #265d83 with 4px offset; charcoal areas use #efcf93 |
| Spacing | 4, 8, 12, 16, 24, 32, 48, 64px |
| Body | 16px minimum mobile, 1.7 line height, measure ≤65ch |
| Titles | 30–38px mobile hero, 44–60px desktop; Cairo 600/700 RTL, French editorial serif |
| Controls | ≥44px high; 48px primary actions; visible label, no placeholder-only form |
| Radius / shadows | 2–4px, thin borders, no decorative card shadow |
| Motion | 160ms colour/opacity; reduced-motion disables optional motion |

## Mobile first

320–359: one product column when labels cannot remain readable. 360–767: two material columns, single editorial stories with image above copy, two trust columns. ≥768: three material columns and two editorial columns. ≥1024: prototype sidebar and four catalogue columns. ≥1280: full desktop navigation; content maximum 1360px. Gutters 16px phone, 24px tablet, 40px desktop.

Header: readable wordmark, 44px menu, accessible language links, visible consultation path. Mobile hero prioritizes headline and discovery CTAs; remove decorative study rail on phones. Catalogue order: banner, search, categories, filter action, results. Modals use native dialog, labelled heading, close, Escape, focus containment and return. Footer stays compact and readable.

Do not fill a phone viewport with repeated fake catalogue cards. Retain actual record counts. Neutral composition placeholders remain honest, bounded and noninteractive. Generated architecture remains explicitly labelled in isolated presentation mode only. No real product image becomes proof of a completed project.

## Three local request journeys

A. Material quotation: identity, authoritative standard/custom choice, bounds/unit where provided, finish, quantity, allowed unit, optional description. B. Similar project: project identity, optional city, dimensions text and description. C. Material for a new project: product identity, optional city/project description. Distinct headings explain intent. Review a local summary; never claim transmission. No requests to WhatsApp or backend mutation endpoints. Pending product specifications block unsupported selections without inventing data.

## Verification

Check all eight pages in FR/AR at 320, 360, 375, 390, 430, 768, 1024, 1280, 1440. Save before/after desktop/mobile screenshots, compare twice, test keyboard/dialog/error/focus behaviour and mobile action clearance. WCAG 2.2 AA is a target, not an automatic compliance claim. Preserve backend/media security and M1–M5 regressions. Report Lighthouse/a11y tooling limitations instead of inventing scores. No M6/M7 integration.
