# Simply Rest Phase 1 Goal Completion Audit

Generated: 2026-06-27 10:23:22 UTC

## TL;DR

- Production is not launch-ready yet.
- Local package-ready rows: 0.
- Production-blocked rows: 12.
- Approval-gated rows: 1.

## Current Go / No-Go

No-go. The WordPress deploy package is ready for an operator, but live Simply Rest still fails the proof-page and retrofit gates.

## Blocking Requirements

- 1: Publish the five core proof pages first - core pages are not all published with the new proof layer
- 1.1: Core proof page live QA: Mattress Lab - status=404; canonical=missing; missing markers: Simply Rest Lab; Firdous; Ferdie; lead hands-on tester; missing links: https://simplyrest.com/how-we-test-mattresses/; https://simplyrest.com/ferdie-farhad/; https://simplyrest.com/mattress-reviews/; schema: no parseable JSON-LD schema; affiliate disclosure missing; medical limitation missing
- 1.2: Core proof page live QA: How We Test Mattresses - status=404; canonical=missing; missing markers: How We Test Mattresses; pressure relief; spinal alignment; motion isolation; edge support; missing links: https://simplyrest.com/how-we-test-mattresses/; schema: no parseable JSON-LD schema; affiliate disclosure missing; medical limitation missing
- 1.3: Core proof page live QA: Ferdie Farhad - status=404; canonical=missing; missing markers: Firdous; lead hands-on tester; missing links: https://simplyrest.com/how-we-test-mattresses/; schema: no parseable JSON-LD schema; affiliate disclosure missing; medical limitation missing
- 1.4: Core proof page live QA: Mattress Reviews Hub - canonical=missing; missing markers: Simply Rest Lab; Amerisleep AS3; missing links: https://simplyrest.com/mattress-reviews/amerisleep-as3/; https://simplyrest.com/how-we-test-mattresses/; schema: no parseable JSON-LD schema; forbidden markers: It seems we can; affiliate disclosure missing; medical limitation missing
- 1.5: Core proof page live QA: Amerisleep AS3 Review - final_url=https://simplyrest.com/best-online-mattress/; canonical=https://simplyrest.com/best-online-mattress/; missing markers: Simply Rest Lab Score; Testing Evidence; Ferdie; missing links: https://simplyrest.com/how-we-test-mattresses/; schema: no parseable JSON-LD schema; media: AS3 has fewer than 3 video blocks/assets: 0; forbidden markers: /best-online-mattress/; affiliate disclosure missing; medical limitation missing
- 2: Upload and attach real media evidence - media package exists locally, but production pages do not yet prove attached WordPress-hosted media
- 3: Implement Person, Article, Review, FAQ, and Breadcrumb schema - live pages have no parseable JSON-LD or unresolved entity checks
- 4: Add affiliate, methodology, medical limitation, and author/lead tester disclosures - required disclosures are packaged but not visible on production pages
- 5: Retrofit the existing 122 Simply Rest URLs - 122 URLs are reachable, but proof-layer retrofits are not deployed live
- 6: Build internal linking between old guides, Lab, reviews, best-of pages, and How We Test - link map is packaged, but production pages do not yet expose all required links
- 7: QA before launch: desktop/mobile, schema, links, canonicals, private links, and commercial disclosures - production cannot pass final QA until phase-one pages and retrofits are deployed and rerun clean

## Approval-Gated Items

- 2.1: Keep optional Ferdie action candidate approval-gated - optional action crop still requires approval; approved headshot is available for primary Ferdie media

## Next Actions

- Use the deploy instructions only after package QA is green and the bundle contains the required files.
- Run the WP-CLI page importer, publish approved pages, fix AS3 redirect, then rerun live QA.
- Publish or replace the WordPress page and rerun live QA until this row passes.
- Run AS3 and lab media importers; use the approved Ferdie headshot as primary and keep the candidate action crop optional unless approved.
- Use the supplied Ferdie headshot for launch; use the candidate action crop only with explicit approval.
- Install the MU JSON-LD renderer and confirm each page has _simplyrest_json_ld before rerunning live QA.
- Publish imported content and verify disclosure modules appear before commercial CTAs where applicable.
- Run retrofit batches by priority after phase-one proof pages pass; rerun retrofit live QA after each batch.
- Deploy core pages first, then apply the internal linking map through the retrofit batch and manual hub edits.
- Use the launch gate and live QA reports as the final go/no-go after WordPress deployment.

## Evidence Files

- `simplyrest-122-url-retrofit-implementation-tracker-2026-06-25.tsv`
- `simplyrest-as3-optimized-media-upload-manifest-2026-06-25.tsv`
- `simplyrest-internal-linking-map-2026-06-25.tsv`
- `simplyrest-lab-proof-media-upload-manifest-2026-06-25.tsv`
- `simplyrest-mattress-scoring-matrix-2026-06-25.tsv`
- `simplyrest-official-source-content-pack-summary-2026-06-25.tsv`
- `simplyrest-phase1-live-qa-report-2026-06-25.tsv`
- `simplyrest-phase1-package-qa-report-2026-06-25.tsv`
- `simplyrest-phase1-wordpress-deploy-bundle-2026-06-25.zip`
- `simplyrest-retrofit-live-qa-report-2026-06-25.tsv`
- `simplyrest-retrofit-package-qa-report-2026-06-25.tsv`
- `simplyrest-retrofit-slug-manifest-2026-06-25.tsv`
