# Simply Rest Phase 1 WP-CLI Deploy Instructions

## Purpose

Use these files to stage the five Simply Rest Lab proof pages in WordPress, attach the schema payloads, and render JSON-LD without editing the active theme.

## Files

- `simplyrest_phase1_wp_cli_import.php` - creates or updates the five phase-one pages and stores page schema in `_simplyrest_json_ld`.
- `simplyrest_phase1_wp_rollback.php` - dry-run-first rollback helper that restores updated pages from importer snapshots or moves importer-created pages back to draft without deleting pages.
- `simplyrest_phase1_package_qa.py` - local package validator for required files, embedded page payloads, schema coverage, disclosures, internal links, AS3 media consistency, and static preview link integrity.
- `simplyrest_phase1_local_readiness.py` - one-command local readiness runner that executes package QA, live QA, completion audit, and zip integrity checks in operator order.
- `simplyrest-phase1-package-qa-instructions-2026-06-25.md` - operator sequence for local package QA before WordPress work.
- `simplyrest_phase1_wp_preflight.php` - read-only WordPress readiness and AS3 redirect audit before launch edits.
- `simplyrest_phase1_jsonld_renderer_mu_plugin.php` - must-use plugin that renders `_simplyrest_json_ld` on singular pages.
- `simplyrest_phase1_live_qa_checker.py` - post-launch checker for URL status, redirects, page evidence, disclosures, schema type coverage, schema entity relationships, and media proof placement.
- `simplyrest_phase1_launch_gate.php` - read-only WordPress launch gate that fails until pages, schema meta, media, renderer, redirects, and live URLs are launch-ready.
- `simplyrest_phase1_goal_completion_audit.py` - read-only requirement-level audit that maps the original launch goal to package evidence, live QA, retrofit QA, blockers, and next actions.
- `simplyrest-phase1-goal-completion-audit-2026-06-25.tsv` - current requirement-level status matrix.
- `simplyrest-phase1-goal-completion-audit-2026-06-25.md` - human-readable no-go / next-action brief from the audit.
- `simplyrest-phase1-local-readiness-report-2026-06-25.tsv` - single-run local readiness summary.
- `simplyrest-phase1-local-readiness-report-2026-06-25.md` - human-readable readiness no-go / blocker summary.
- `simplyrest_phase1_as3_redirect_cleanup.php` - dry-run-first helper for disabling common WordPress redirect records that send AS3 to `/best-online-mattress/`.
- `simplyrest_phase1_as3_media_import.php` - uploads optimized AS3 proof media, sets alt/caption metadata, and can patch the AS3 review with media-rich Gutenberg blocks.
- `simplyrest-as3-optimized-media-2026-06-25.zip` - optimized AS3 images, clips, and poster frames.
- `simplyrest-as3-optimized-media-upload-manifest-2026-06-25.tsv` - exact AS3 media titles, alt text, captions, and placement tokens.
- `simplyrest-as3-media-section-replacement-2026-06-25.html` - Gutenberg replacement section for the AS3 text-only evidence block.
- `simplyrest_phase1_lab_media_import.php` - uploads and places cropped Lab/Methodology proof media plus the approved Ferdie headshot; skips the optional Ferdie action candidate unless explicitly approved with `--include-candidate-ferdie`.
- `simplyrest-lab-proof-media-2026-06-25.zip` - cropped Simply Rest-ready proof images for Lab/Methodology, the approved Ferdie headshot/avatar, plus one optional Ferdie action-photo candidate.
- `simplyrest-lab-proof-media-upload-manifest-2026-06-25.tsv` - exact proof media titles, alt text, captions, target pages, and approval status.
- `simplyrest-mattress-scoring-matrix-2026-06-25.tsv` and `.json` - source-of-truth scoring matrix supplied for current mattress score tables.
- `simplyrest-official-source-content-pack-2026-06-25.zip` - official-source content/evidence pack for reviews, comparisons, best-of pages, and foundation pages.
- `simplyrest-official-source-content-pack-summary-2026-06-25.md` / `.tsv` - publish gates and counts for the official-source content pack.
- `simplyrest-retrofit-slug-manifest-2026-06-25.tsv` - 122 live URL retrofit slugs with page type, priority, and claim-sensitive flags.
- `simplyrest-122-url-retrofit-implementation-tracker-2026-06-25.tsv` - implementation tracker for the 122 URL retrofit layer.
- `simplyrest_phase1_retrofit_batch.php` - dry-run-first WP-CLI script for staging author/tester/methodology/disclosure/FAQ retrofits on existing pages.
- `simplyrest-phase1-retrofit-batch-instructions-2026-06-25.md` - operator sequence for phased 122-URL retrofits.
- `simplyrest-internal-linking-map-2026-06-25.tsv` - internal-linking map for proof pages, review pages, commercial pages, and comparison hubs.
- `simplyrest-retrofit-and-linking-operator-brief-2026-06-25.md` - human-readable retrofit and internal-linking operating brief.
- `simplyrest-retrofit-gutenberg-block-library-2026-06-25.html` - reusable Gutenberg block patterns for retrofit modules.
- `simplyrest_retrofit_package_qa.py` - local validator for the retrofit manifest, internal-linking map, claim-sensitive alignment, and retrofit script gates.
- `simplyrest-retrofit-package-qa-instructions-2026-06-25.md` - operator sequence for local retrofit package QA.
- `simplyrest-retrofit-package-qa-report-2026-06-25.tsv` - latest local retrofit package QA report.
- `simplyrest_retrofit_live_qa_checker.py` - post-retrofit live QA checker for the 122 URL rollout.
- `simplyrest-retrofit-live-qa-instructions-2026-06-25.md` - operator sequence for stricter post-retrofit live QA.
- `simplyrest-retrofit-live-qa-report-2026-06-25.tsv` - current production baseline report for the 122 URL retrofit layer.
- `simplyrest-retrofit-live-qa-baseline-2026-06-25.md` - human-readable baseline summary for the 122 URL retrofit layer.

## Deploy Sequence

1. Run local package QA before handing off or copying files:

```bash
python3 -B outputs/simplyrest_phase1_package_qa.py --output outputs/simplyrest-phase1-package-qa-report-2026-06-25.tsv
```

Or run the local readiness sequence in one command:

```bash
python3 -B outputs/simplyrest_phase1_local_readiness.py --output outputs/simplyrest-phase1-local-readiness-report-2026-06-25.tsv --markdown outputs/simplyrest-phase1-local-readiness-report-2026-06-25.md
```

Generate or refresh the requirement-level completion audit whenever a live QA or package QA report changes:

```bash
python3 -B outputs/simplyrest_phase1_goal_completion_audit.py --output outputs/simplyrest-phase1-goal-completion-audit-2026-06-25.tsv --markdown outputs/simplyrest-phase1-goal-completion-audit-2026-06-25.md
```

2. Back up WordPress database and confirm the Simply Rest staging or production target.
3. Copy `simplyrest_phase1_wp_preflight.php` and `simplyrest_phase1_wp_cli_import.php` to the WordPress root.
4. Run read-only preflight:

```bash
wp eval-file simplyrest_phase1_wp_preflight.php
```

5. Dry run first:

```bash
wp eval-file simplyrest_phase1_wp_cli_import.php -- --dry-run
```

6. Stage missing pages as drafts. When updating existing pages, the importer saves the first pre-import snapshot in `_simplyrest_phase1_pre_import_snapshot` unless `--skip-snapshot` is passed:

```bash
wp eval-file simplyrest_phase1_wp_cli_import.php
```

7. If the existing `/mattress-reviews/` page is already published and should be replaced, rerun only after approval:

```bash
wp eval-file simplyrest_phase1_wp_cli_import.php -- --force-update-published
```

8. Publish during the approved launch window:

```bash
wp eval-file simplyrest_phase1_wp_cli_import.php -- --publish --force-update-published
```

9. Keep the rollback helper available during launch. It defaults to dry-run and does not delete pages:

```bash
wp eval-file simplyrest_phase1_wp_rollback.php
```

Only after backup confirmation and approval, apply rollback if launch must be unwound:

```bash
wp eval-file simplyrest_phase1_wp_rollback.php -- --apply
```

10. Install the JSON-LD renderer by copying `simplyrest_phase1_jsonld_renderer_mu_plugin.php` to:

```text
wp-content/mu-plugins/simplyrest-phase1-jsonld.php
```

11. Upload and attach AS3 testing media with the AS3 media importer:

```bash
unzip simplyrest-as3-optimized-media-2026-06-25.zip -d simplyrest-as3-media
wp eval-file simplyrest_phase1_as3_media_import.php -- --media-dir=simplyrest-as3-media --manifest=simplyrest-as3-optimized-media-upload-manifest-2026-06-25.tsv --section-template=simplyrest-as3-media-section-replacement-2026-06-25.html --dry-run
wp eval-file simplyrest_phase1_as3_media_import.php -- --media-dir=simplyrest-as3-media --manifest=simplyrest-as3-optimized-media-upload-manifest-2026-06-25.tsv --section-template=simplyrest-as3-media-section-replacement-2026-06-25.html --update-page
```

12. Upload and attach Lab/Methodology proof media with the lab media importer:

```bash
unzip simplyrest-lab-proof-media-2026-06-25.zip -d simplyrest-lab-proof-media
wp eval-file simplyrest_phase1_lab_media_import.php -- --media-dir=simplyrest-lab-proof-media --manifest=simplyrest-lab-proof-media-upload-manifest-2026-06-25.tsv --dry-run
wp eval-file simplyrest_phase1_lab_media_import.php -- --media-dir=simplyrest-lab-proof-media --manifest=simplyrest-lab-proof-media-upload-manifest-2026-06-25.tsv --update-pages
```

The supplied Ferdie headshot and avatar are approved deployable rows in the manifest and should be used as the primary `/ferdie-farhad/` media. The Ferdie action-photo crop is candidate-only. Use it only after approval:

```bash
wp eval-file simplyrest_phase1_lab_media_import.php -- --media-dir=simplyrest-lab-proof-media --manifest=simplyrest-lab-proof-media-upload-manifest-2026-06-25.tsv --update-pages --include-candidate-ferdie
```

13. Confirm the approved Ferdie headshot appears on `/ferdie-farhad/` and that Lab/Methodology proof images appear on `/mattress-lab/` and `/how-we-test-mattresses/`. Do not use Healthy Americans-branded images unless they are cropped, rebranded, or explicitly approved.
14. Remove or update any redirect that sends `/mattress-reviews/amerisleep-as3/` to `/best-online-mattress/`. Use the preflight redirect audit to identify common plugin records before checking server/CDN rules.
15. If the bad AS3 redirect appears in common WordPress redirect records, run the redirect cleanup helper in dry-run mode first:

```bash
wp eval-file simplyrest_phase1_as3_redirect_cleanup.php
```

After database backup and approval, apply only if the dry run shows the AS3 to `/best-online-mattress/` record:

```bash
wp eval-file simplyrest_phase1_as3_redirect_cleanup.php -- --apply
```

The helper does not edit server, CDN, or htaccess rules. If live QA still shows the redirect after WordPress records are clean, escalate to server/CDN redirect owners.

16. Confirm SEO title, meta description, and canonical fields in the active SEO plugin. The importer writes Yoast-compatible fields; a different SEO stack may need manual field mapping.
17. Run the read-only launch gate. It should fail until the five proof pages are published, AS3 media is attached, Ferdie media is attached, the JSON-LD renderer is installed, rollback helper is present, page rollback markers exist, and the AS3 redirect is fixed:

```bash
wp eval-file simplyrest_phase1_launch_gate.php
```

For staging review before publish, use:

```bash
wp eval-file simplyrest_phase1_launch_gate.php -- --allow-drafts --skip-http
```

18. Run the live QA checker after pages are published:

```bash
python3 outputs/simplyrest_phase1_live_qa_checker.py --output outputs/simplyrest-phase1-live-qa-report-2026-06-25.tsv
```

19. After phase-one QA passes, start 122-URL retrofits with dry runs only:

```bash
wp eval-file simplyrest_phase1_retrofit_batch.php -- --slug-file=simplyrest-retrofit-slug-manifest-2026-06-25.tsv --priority=P0,P1 --dry-run
```

20. After each retrofit batch, run the live retrofit QA checker:

```bash
python3 simplyrest_retrofit_live_qa_checker.py --manifest simplyrest-retrofit-slug-manifest-2026-06-25.tsv --priority P0 --output simplyrest-retrofit-live-qa-report-2026-06-25.tsv
```

## Launch Pass Criteria

- All five phase-one URLs return 200 without unintended redirects.
- Ferdie appears as author and lead hands-on tester.
- Affiliate disclosure appears before or near commercial CTAs.
- Medical limitation language appears on mattress and wellness-adjacent pages.
- JSON-LD renders on each page and includes Person, FAQ, Breadcrumb, Article or CollectionPage, and Review/Product where applicable.
- JSON-LD entity relationships are internally consistent: page node IDs match canonicals, Article points to Ferdie and Simply Rest, Breadcrumb ends on the canonical URL, FAQ schema matches visible questions, and AS3 Review points to the AS3 Product/Amerisleep brand.
- AS3 page uses WordPress-hosted media, not private Drive links.
- Every review links back to `/how-we-test-mattresses/`.
- Each phase-one page has an importer provenance marker plus either a pre-import snapshot or importer-created rollback marker.
- `simplyrest_phase1_wp_rollback.php` is present in the WordPress root during launch.

## Known Current Blockers

- Live baseline QA found `/mattress-lab/`, `/how-we-test-mattresses/`, and `/ferdie-farhad/` returning 404.
- `/mattress-reviews/` currently resolves but does not show the new hub content.
- `/mattress-reviews/amerisleep-as3/` redirects to `/best-online-mattress/`.
- Real media still needs to be uploaded and placed before public launch.
