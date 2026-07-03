# Simply Rest 122-URL Retrofit Batch Instructions

## Purpose

Use this only after the five phase-one proof pages are live and QA passes. The batch script turns the 122 retrofit briefs into a controlled WordPress staging workflow: dry run first, priority filters, no canonical changes, no redirect changes, and no published-page edits unless explicitly forced.

## Files

- `simplyrest-retrofit-slug-manifest-2026-06-25.tsv` - the 122 live URL slugs with page type, priority, and claim-sensitive flags.
- `simplyrest-122-url-retrofit-implementation-tracker-2026-06-25.tsv` - implementation tracker for owner/status/QA tracking across the 122 URLs.
- `simplyrest-internal-linking-map-2026-06-25.tsv` - source/target/anchor map for proof-page and commercial-route internal links.
- `simplyrest-retrofit-and-linking-operator-brief-2026-06-25.md` - operator brief explaining batch order, QA gates, and linking logic.
- `simplyrest-retrofit-gutenberg-block-library-2026-06-25.html` - reusable block patterns for manual or CMS-assisted retrofit work.
- `simplyrest_phase1_retrofit_batch.php` - WP-CLI retrofit staging script.
- `simplyrest_phase1_jsonld_renderer_mu_plugin.php` - updated renderer that supports both phase-one and retrofit JSON-LD meta.
- `simplyrest_retrofit_package_qa.py` - local validator for the 122-row manifest, internal-linking map, claim-sensitive alignment, and retrofit script gates.
- `simplyrest-retrofit-package-qa-instructions-2026-06-25.md` - run instructions for the local retrofit package QA.

## Deploy Sequence

1. Confirm the five proof pages are live and pass QA:
   - `/mattress-lab/`
   - `/how-we-test-mattresses/`
   - `/ferdie-farhad/`
   - `/mattress-reviews/`
   - `/mattress-reviews/amerisleep-as3/`

2. Run local retrofit package QA:

```bash
python3 -B outputs/simplyrest_retrofit_package_qa.py --output outputs/simplyrest-retrofit-package-qa-report-2026-06-25.tsv
```

3. Copy `simplyrest-retrofit-slug-manifest-2026-06-25.tsv` and `simplyrest_phase1_retrofit_batch.php` to the WordPress root.

4. Dry run the first 20 matching pages:

```bash
wp eval-file simplyrest_phase1_retrofit_batch.php -- --slug-file=simplyrest-retrofit-slug-manifest-2026-06-25.tsv --dry-run --limit=20
```

5. Dry run the highest-priority commercial pages:

```bash
wp eval-file simplyrest_phase1_retrofit_batch.php -- --slug-file=simplyrest-retrofit-slug-manifest-2026-06-25.tsv --priority=P0,P1 --dry-run
```

6. Apply P0 only after reviewing the dry run output:

```bash
wp eval-file simplyrest_phase1_retrofit_batch.php -- --slug-file=simplyrest-retrofit-slug-manifest-2026-06-25.tsv --priority=P0 --update-pages
```

7. If a target page is already published, require backup and approval before forcing the update:

```bash
wp eval-file simplyrest_phase1_retrofit_batch.php -- --slug-file=simplyrest-retrofit-slug-manifest-2026-06-25.tsv --priority=P0 --update-pages --force-update-published
```

## What The Script Adds

- Author and lead hands-on tester line for Firdous "Ferdie" Farhad.
- Quick answer summary tied to the Simply Rest Lab framework.
- Links to `/mattress-lab/` and `/how-we-test-mattresses/`.
- Affiliate disclosure.
- Medical limitation language on claim-sensitive pages.
- Testing and methodology FAQ.
- Retrofit JSON-LD stored in `_simplyrest_retrofit_json_ld`.

## What The Script Does Not Do

- It does not rewrite the full article.
- It does not change canonical URLs.
- It does not add redirects.
- It does not upload new media.
- It does not override existing theme, SEO-plugin, or analytics settings.

## QA Gates

- Confirm the retrofit block appears near the top of the rendered page.
- Confirm links to the proof pages resolve.
- Confirm schema renders from the updated mu-plugin.
- Confirm no private Drive links are exposed.
- Confirm claim-sensitive pages use support, comfort, fit, and shopping guidance instead of medical outcome promises.
- Confirm commercial CTAs have an affiliate disclosure before or near the CTA area.
- Run `simplyrest_retrofit_live_qa_checker.py` after each priority batch and review every `overall_pass=no` row before continuing.
