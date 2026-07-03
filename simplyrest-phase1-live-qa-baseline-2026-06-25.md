# Simply Rest Phase 1 Live QA Baseline

## TL;DR

The live proof layer is not ready. The QA checker found 0 of 5 phase-one pages passing on 2026-06-25.

Use this as the baseline before WordPress import/publish. After publishing, rerun:

```bash
python3 outputs/simplyrest_phase1_live_qa_checker.py --output outputs/simplyrest-phase1-live-qa-report-2026-06-25.tsv
```

The checker exits with status `1` while any page fails.

## Current Results

| Page | URL | Current Result | Main Blocker |
|---|---|---|---|
| Mattress Lab | `https://simplyrest.com/mattress-lab/` | 404 | Page missing |
| How We Test Mattresses | `https://simplyrest.com/how-we-test-mattresses/` | 404 | Page missing |
| Ferdie Farhad | `https://simplyrest.com/ferdie-farhad/` | 404 | Page missing |
| Mattress Reviews Hub | `https://simplyrest.com/mattress-reviews/` | 200 | Empty archive-style page; missing Lab/Ferdie/AS3 proof content and schema |
| Amerisleep AS3 Review | `https://simplyrest.com/mattress-reviews/amerisleep-as3/` | 200 after redirect | Redirects to `/best-online-mattress/`; AS3 proof page is not rendering |

## What The Checker Verifies

- HTTP status is 2xx.
- Final URL matches the intended canonical URL.
- Rendered canonical tag matches the intended canonical URL.
- Required JSON-LD schema types are present and parseable.
- Affiliate disclosure is present.
- Medical limitation language is present.
- Required page markers are present.
- Required links are present.
- No public Drive links are exposed.
- AS3 does not redirect to `/best-online-mattress/`.

## Pass Criteria

All five rows in `outputs/simplyrest-phase1-live-qa-report-2026-06-25.tsv` must show:

- `status_ok = yes`
- `final_url_ok = yes`
- `canonical_ok = yes`
- `missing_schema_types` blank
- `affiliate_disclosure_found = yes`
- `medical_limit_found = yes`
- `missing_content_markers` blank
- `missing_required_links` blank
- `forbidden_public_markers` blank
- `overall_pass = yes`

## Next Action

Import the five draft pages, attach media, add schema to the live SEO stack, remove the AS3 redirect, publish, then rerun the checker.
