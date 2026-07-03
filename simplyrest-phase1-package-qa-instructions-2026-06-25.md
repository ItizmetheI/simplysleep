# Simply Rest Phase 1 Package QA Instructions

## Purpose

Run this before handing the WordPress deploy bundle to an operator or before running any WP-CLI import command. It checks the local package only and does not touch production.

## What It Checks

- Required deploy files are present.
- The WordPress deploy bundle is readable and includes the importer, rollback helper, preflight, launch gate, live QA, AS3 media importer, lab media importer, retrofit files, completion audit, and package QA script.
- The embedded five-page importer payload has the expected URLs, schema types, disclosures, Ferdie attribution, methodology links, and no private-link or unsupported credential markers.
- The WordPress launch gate checks schema entity relationships, not just schema type presence.
- The importer saves pre-import snapshots before updating existing pages, and the rollback helper is dry-run-first with no page deletion path.
- The AS3 media manifest matches the optimized media zip and Gutenberg replacement section.
- The Lab/Methodology/Ferdie proof media manifest matches the optimized proof media zip, includes the approved Ferdie headshot/avatar, and keeps the optional Ferdie action candidate approval-gated.
- The supplied mattress scoring matrix exists as TSV and JSON, and the AS3 review copy/schema use the supplied `10/10` score.
- The official-source content pack is bundled with its source evidence, remaining CMS QA gates, and publish-gate summary.
- The completion audit covers the original launch requirements and preserves production blockers while live QA is failing.
- The local readiness runner exists and produces a single go/no-go report for package QA, live QA, completion audit, and zip integrity.
- The static preview does not contain the malformed AS3 local-link pattern.

## Run

From this workspace:

```bash
python3 -B outputs/simplyrest_phase1_local_readiness.py --output outputs/simplyrest-phase1-local-readiness-report-2026-06-25.tsv --markdown outputs/simplyrest-phase1-local-readiness-report-2026-06-25.md
```

```bash
python3 -B outputs/simplyrest_phase1_goal_completion_audit.py --output outputs/simplyrest-phase1-goal-completion-audit-2026-06-25.tsv --markdown outputs/simplyrest-phase1-goal-completion-audit-2026-06-25.md
```

```bash
python3 -B outputs/simplyrest_phase1_package_qa.py --output outputs/simplyrest-phase1-package-qa-report-2026-06-25.tsv
```

## Pass Criteria

- The script exits with code `0`.
- The TSV report has no `fail` rows.
- Warnings should be reviewed before launch, but hard failures must be fixed before WordPress work starts.
