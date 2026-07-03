# Simply Rest Lab Implementation Handoff

This packet is for implementing the Simply Rest Lab authority layer on WordPress.

## Goal

Publish Simply Rest as a credible first-hand mattress testing and recommendation site, with Ferdie/Firdous positioned as the author and lead hands-on tester. The launch needs visible proof of testing, scoring, product evidence, methodology, schema, disclosures, internal linking, and QA evidence.

## Priority Pages To Publish First

- `/mattress-lab/`
- `/how-we-test-mattresses/`
- `/ferdie-farhad/`
- `/mattress-reviews/`
- `/mattress-reviews/amerisleep-as3/`

## Main Files In This Packet

- `simplyrest-phase1-wp-cli-deploy-instructions-2026-06-25.md`: step-by-step WordPress deploy instructions.
- `simplyrest_phase1_wp_preflight.php`: read-only preflight checks before import.
- `simplyrest_phase1_wp_cli_import.php`: creates/updates the five priority pages with content, schema-ready structures, disclosures, and internal links.
- `simplyrest_phase1_as3_media_import.php`: imports AS3 review media and maps it to the AS3 page.
- `simplyrest_phase1_lab_media_import.php`: imports approved Ferdie/lab proof media; the candidate Ferdie action crop stays approval-gated unless explicitly included.
- `simplyrest_phase1_as3_redirect_cleanup.php`: fixes the AS3 review URL redirect/canonical issue.
- `simplyrest_phase1_launch_gate.php`: final read-only gate before launch approval.
- `simplyrest_phase1_wp_rollback.php`: dry-run rollback helper with explicit `--apply` requirement.
- `simplyrest_phase1_live_qa_checker.py`: checks the five priority URLs after deployment.
- `simplyrest_retrofit_live_qa_checker.py`: checks the 122 retrofit URLs after rollout.
- `simplyrest_phase1_package_qa.py` and `simplyrest_retrofit_package_qa.py`: local package QA checks.

## Content And Evidence Files

- `simplyrest-as3-optimized-media-2026-06-25.zip`: AS3 product photos, testing clips, edge support clips, response time clip, hand impression, and sleeper-position photos.
- `simplyrest-lab-proof-media-2026-06-25.zip`: approved lab proof images plus approved Ferdie/Firdous headshot/avatar. The action crop is a candidate only.
- `simplyrest-mattress-scoring-matrix-2026-06-25.tsv` and `.json`: scoring source supplied by Joel. AS3 is `10/10`.
- `simplyrest-official-source-content-pack-2026-06-25.zip`: official-source content pack for broader reviews, comparisons, best-of pages, and CMS QA.
- `simplyrest-phase1-static-preview-package-2026-06-25.zip`: static preview of the priority-page experience.
- `simplyrest-phase1-wordpress-draft-import-2026-06-25.xml`: fallback WXR draft import if WP-CLI route is not usable.

## Retrofit Files

- `simplyrest-122-url-retrofit-implementation-tracker-2026-06-25.tsv`: 122 live URL retrofit map.
- `simplyrest-retrofit-slug-manifest-2026-06-25.tsv`: slug-level retrofit manifest.
- `simplyrest-internal-linking-map-2026-06-25.tsv`: internal linking plan.
- `simplyrest-retrofit-gutenberg-block-library-2026-06-25.html`: reusable proof-layer blocks.
- `simplyrest_phase1_retrofit_batch.php`: batch helper for retrofit implementation.

## Current QA State

- Local package QA passes.
- Retrofit package QA passes.
- Zip integrity checks pass.
- Static preview passes desktop/mobile checks.
- Production is not complete yet.

Current live blockers:

- `/mattress-lab/` returns 404.
- `/how-we-test-mattresses/` returns 404.
- `/ferdie-farhad/` returns 404.
- `/mattress-reviews/` is still the legacy hub and does not carry the new proof layer.
- `/mattress-reviews/amerisleep-as3/` redirects to `/best-online-mattress/`.
- 122 existing URLs are live but have not yet received the proof-layer retrofit.

## Ahmed Action List

1. Read `simplyrest-phase1-wp-cli-deploy-instructions-2026-06-25.md`.
2. Run the preflight script in read-only mode.
3. Upload/import the AS3 media and lab proof media.
4. Run the priority-page importer.
5. Fix the AS3 redirect/canonical behavior.
6. Confirm the five priority pages are published at the exact target URLs.
7. Validate schema, breadcrumbs, FAQ, Review, Article, and Person markup.
8. Confirm affiliate disclosures, testing methodology disclosures, medical limitation language, and author/lead tester attribution are visible.
9. Run the launch gate and live QA scripts.
10. Roll out the 122 URL retrofits and rerun retrofit live QA.
11. Send back the final live QA outputs before production approval.

Do not expose private Drive links in public page content. Use uploaded WordPress media URLs instead.
