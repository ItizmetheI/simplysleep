# Simply Rest Retrofit Package QA Instructions

## Purpose

Run this before applying any 122-URL retrofit batch to WordPress. It validates the retrofit rollout files themselves (manifest, internal linking map, retrofit batch script, retrofit live QA checker) and does not touch production.

## What It Checks

- Required retrofit files are present: the 122-row slug manifest, the internal linking map, the retrofit batch script, the retrofit live QA checker, the retrofit batch instructions, the retrofit live QA report, and the retrofit live QA baseline.
- The slug manifest has exactly 122 rows, unique root-level slugs, brief filenames that match their slugs, valid page types, valid priorities (`P0`, `P1`, `P1-claim-sensitive`, `P2`, `P3`), and claim-sensitive flags that agree with both the assigned priority and the slug's own risk pattern (pain, reflux, snoring, sleep-health, and similar topics).
- The internal linking map has the required columns (`priority`, `source_url`, `target_url`, `anchor_text`, `placement`, `reason`, `publish_dependency`), only uses `https://simplyrest.com` URLs, and every manifest slug is represented.
- The retrofit batch script and retrofit live QA checker exist and are non-empty.
- The retrofit live QA report, when present, is checked against the manifest for row-count and slug alignment.

## Run

From this workspace:

```bash
python3 simplyrest_retrofit_package_qa.py --output simplyrest-retrofit-package-qa-report-2026-06-25.tsv
```

The manifest and internal linking map paths are fixed to `simplyrest-retrofit-slug-manifest-2026-06-25.tsv` and `simplyrest-internal-linking-map-2026-06-25.tsv` in the same directory as the script; only the report output path is configurable.

## Pass Criteria

- The script exits with code `0`.
- The TSV report has no `fail` rows.
- Any `fail` row must be resolved before the retrofit batch script is run against WordPress with `--update-pages`.
