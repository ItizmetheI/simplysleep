# Simply Rest Local Readiness Report

Generated: 2026-07-12 10:57:49 UTC

## TL;DR

- Local validators ran in the operator sequence.
- Status counts: blocker=3, pass=4.
- Go/no-go: no-go

## Blocking Rows

- `phase_one_live_summary`: 0/5 pages pass; failing=Mattress Lab; How We Test Mattresses; Ferdie Farhad; Mattress Reviews Hub; Amerisleep AS3 Review
- `retrofit_live_summary`: 0/122 pages pass; schema_entity_issue_rows=122
- `completion_audit_summary`: needs_approval=1, package_ready=1, production_blocked=12

## Steps

- `pass` `phase_one_package_qa` exit=0: PASS: package QA passed. Report: C:\Users\ahmed\SIMPLYREST\simplyrest-phase1-package-qa-report-2026-06-25.tsv
- `pass` `retrofit_package_qa` exit=0: PASS: retrofit package QA passed. Report: C:\Users\ahmed\SIMPLYREST\simplyrest-retrofit-package-qa-report-2026-06-25.tsv
- `pass` `completion_audit` exit=0: Wrote audit: C:\Users\ahmed\SIMPLYREST\simplyrest-phase1-goal-completion-audit-2026-06-25.tsv and C:\Users\ahmed\SIMPLYREST\simplyrest-phase1-goal-completion-audit-2026-06-25.md. package_ready=1, production_blocked=12, needs_approval=1
- `pass` `deploy_bundle_integrity` exit=0: all files readable
- `blocker` `phase_one_live_summary` exit=1: 0/5 pages pass; failing=Mattress Lab; How We Test Mattresses; Ferdie Farhad; Mattress Reviews Hub; Amerisleep AS3 Review
- `blocker` `retrofit_live_summary` exit=1: 0/122 pages pass; schema_entity_issue_rows=122
- `blocker` `completion_audit_summary` exit=1: needs_approval=1, package_ready=1, production_blocked=12
