# Simply Rest Retrofit and Internal Linking Operator Brief

## TL;DR

Use the phase-one proof pages as the hub, then retrofit the existing 122 live URLs in priority order. Do not update broad internal links to `/mattress-lab/`, `/how-we-test-mattresses/`, or `/mattress-reviews/amerisleep-as3/` until those pages are live and the AS3 redirect is fixed.

## New Implementation Files

- `outputs/simplyrest-122-url-retrofit-implementation-tracker-2026-06-25.tsv`
- `outputs/simplyrest-internal-linking-map-2026-06-25.tsv`

## Retrofit Priority Logic

- `P0`: core commercial/hub pages that should be updated immediately after the proof layer is live.
- `P1`: high-value commercial mattress pages and claim-sensitive sleep/health pages.
- `P1-claim-sensitive`: pages with pain, sleep-health, snoring, reflux, kidney, oxygen, or similar high-risk language.
- `P2`: local-store, sales, and seasonal commercial pages.
- `P3`: lower-risk evergreen guides and support pages.

## Implementation Order

1. Publish and QA the five phase-one proof pages.
2. Fix `/mattress-reviews/amerisleep-as3/` so it no longer redirects to `/best-online-mattress/`.
3. Retrofit `P0` and `P1` mattress/commercial pages first.
4. Retrofit `P1-claim-sensitive` pages with extra medical-claim review.
5. Add internal links using the internal-linking map.
6. Work through `P2` local/sales pages.
7. Finish `P3` evergreen and lower-risk pages.

## Required Retrofit Blocks

Every retrofitted page should add or verify:

- Byline: Firdous “Ferdie” Farhad.
- Lead tester line where product, ranking, comparison, or shopping-evaluation context appears.
- Citeable summary box.
- Methodology link to `/how-we-test-mattresses/`.
- Relevant first-hand testing note or safe testing guidance.
- FAQ section.
- Schema JSON-LD.
- Disclosure block where commercial, affiliate, health-adjacent, support, pain, cooling, or wellness claims appear.

## Internal Linking Rules

- Old mattress guide and commercial pages should link into `/mattress-lab/`.
- Every review and comparison should link to `/how-we-test-mattresses/`.
- Best-of pages should link to the relevant product review pages only after those review pages are live.
- Review pages should link back to the reviews hub and relevant best-of pages.
- Sleep-health pages should use cautious comfort/support language and link to methodology only where mattress testing is relevant.

## QA Rules

Before publishing each retrofit:

- Confirm links resolve.
- Confirm canonical URL is unchanged unless intentionally redirected.
- Validate rendered schema.
- Check mobile tables and CTA spacing.
- Confirm no private Drive URLs are exposed.
- Confirm affiliate disclosure appears before or near commercial CTAs.
- Confirm medical limitation language appears where support, alignment, pain, sleep-health, recovery, cooling, or wellness claims appear.

