# Simply Rest 122-URL Retrofit Live QA Baseline

## TL;DR

All 122 tracked live URLs currently return 200 and keep their expected final/canonical URLs, but all 122 fail the Simply Rest Lab proof-layer QA. The retrofit batch should not be considered launched.

## Scope

- Manifest: `simplyrest-retrofit-slug-manifest-2026-06-25.tsv`
- Report: `simplyrest-retrofit-live-qa-report-2026-06-25.tsv`
- Pages checked: 122
- Overall pass: 0
- Overall fail: 122

## Priority Breakdown

| Priority | Pages | Passing |
|---|---:|---:|
| P0 | 4 | 0 |
| P1 | 52 | 0 |
| P1-claim-sensitive | 17 | 0 |
| P2 | 23 | 0 |
| P3 | 26 | 0 |

## Current Strength

- 122/122 URLs returned HTTP 200.
- 122/122 final URLs matched the expected path.
- 122/122 canonical URLs matched the expected path.

## Current Failures

- 122/122 missing required retrofit schema types: Article, FAQPage, BreadcrumbList, Person, Organization.
- 122/122 have schema entity issues because no parseable retrofit JSON-LD is live yet.
- 122/122 missing Ferdie author attribution.
- 122/122 missing visible lead hands-on tester attribution.
- 122/122 missing a link to `/mattress-lab/`.
- 122/122 missing a link to `/how-we-test-mattresses/`.
- 104/122 missing visible FAQ or FAQ schema.
- 101/122 commercial pages missing required affiliate disclosure language.
- 17/17 claim-sensitive pages missing medical limitation language.
- 1 page includes a forbidden credential marker: `whats-the-best-mattress-for-plus-size-people` includes `physical therapist`.
- 4 pages triggered risky claim-pattern flags:
  - `best-mattress-for-acid-reflux`
  - `best-mattress-for-back-pain`
  - `chronic-kidney-disease-and-sleep`
  - `mattress-types`

## Operational Meaning

The existing URL layer is stable from a status/canonical perspective, which is good for rollout safety. The content layer has not yet been retrofitted with the Simply Rest Lab proof system. Run the retrofit batch in priority order after the five proof pages are live, then rerun this QA report after each batch.
