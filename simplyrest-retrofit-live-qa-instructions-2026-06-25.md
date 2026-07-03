# Simply Rest Retrofit Live QA Instructions

## Purpose

Use this after the phase-one proof pages are live and after any 122-URL retrofit batch is applied. The checker verifies whether existing pages now show the Simply Rest Lab proof layer instead of thin affiliate-only content.

## Run Examples

Check P0 pages first:

```bash
python3 outputs/simplyrest_retrofit_live_qa_checker.py \
  --manifest outputs/simplyrest-retrofit-slug-manifest-2026-06-25.tsv \
  --priority P0 \
  --output outputs/simplyrest-retrofit-live-qa-report-2026-06-25.tsv
```

Check P0 and P1 after the first commercial batch:

```bash
python3 outputs/simplyrest_retrofit_live_qa_checker.py \
  --manifest outputs/simplyrest-retrofit-slug-manifest-2026-06-25.tsv \
  --priority P0,P1 \
  --output outputs/simplyrest-retrofit-live-qa-report-2026-06-25.tsv
```

Check all 122 pages:

```bash
python3 outputs/simplyrest_retrofit_live_qa_checker.py \
  --manifest outputs/simplyrest-retrofit-slug-manifest-2026-06-25.tsv \
  --output outputs/simplyrest-retrofit-live-qa-report-2026-06-25.tsv
```

## Pass Criteria

- URL returns 200.
- Final URL matches the expected canonical path.
- Canonical matches the expected path.
- Ferdie author or lead-tester attribution is visible.
- Page links to `/mattress-lab/` and `/how-we-test-mattresses/`.
- FAQ or FAQ schema is present.
- Required Article, FAQPage, BreadcrumbList, Person, and Organization schema types render.
- Schema entity relationships are valid: Article author/publisher/mainEntityOfPage, Ferdie Person, Simply Rest Organization, Breadcrumb terminal URL, and FAQ question/answer alignment.
- Commercial pages have affiliate disclosure language.
- Claim-sensitive pages have medical limitation language.
- No private Drive links or risky medical outcome claims appear.

## Baseline Result

The pre-retrofit production baseline on June 25, 2026 showed 122/122 URLs returning 200 with expected final/canonical URLs, but 0/122 passing the proof-layer QA. The report now includes a `schema_entity_issues` column so schema type presence cannot hide broken entity relationships. Use `simplyrest-retrofit-live-qa-baseline-2026-06-25.md` for the human-readable summary and `simplyrest-retrofit-live-qa-report-2026-06-25.tsv` for row-level remediation.
