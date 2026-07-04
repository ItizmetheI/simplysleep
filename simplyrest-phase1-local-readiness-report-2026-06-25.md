# Simply Rest Local Readiness Report

Generated: 2026-07-04 06:09:08 UTC

## TL;DR

- Local validators ran in the operator sequence.
- Status counts: blocker=4, fail=2, pass=2.
- Go/no-go: no-go

## Blocking Rows

- `phase_one_package_qa`: FAIL: 3 hard package QA failure(s). Report: C:\Users\ahmed\OneDrive\Desktop\FACC\simplyrest_work\outputs\simplyrest-phase1-package-qa-report-2026-06-25.tsv
- `phase_one_live_qa`: Wrote C:\Users\ahmed\OneDrive\Desktop\FACC\simplyrest_work\outputs\simplyrest-phase1-live-qa-report-2026-06-25.tsv Pages checked: 5 Failures: 5 - Mattress Lab: status=404 final=https://simplyrest.com/mattress-lab/ pass=no - How We Test Mattresses: status=404 final=https://simplyrest.com/how-we-test-mattresses/ pass=no - Ferdie Farhad: status=404 final=https://simplyrest.com/ferdie-farhad/ pass=no - Mattress Reviews Hub: status=200 final=https://simplyrest.com/mattress-reviews/ pass=no - Amerisleep AS3 Review: status=200 final=https://simplyrest.com/best-online-mattress/ pass=no
- `deploy_bundle_integrity`: command not found: [WinError 2] The system cannot find the file specified
- `phase_one_live_summary`: 0/5 pages pass; failing=Mattress Lab; How We Test Mattresses; Ferdie Farhad; Mattress Reviews Hub; Amerisleep AS3 Review
- `retrofit_live_summary`: 0/122 pages pass; schema_entity_issue_rows=122
- `completion_audit_summary`: fail=1, needs_approval=1, production_blocked=12

## Steps

- `fail` `phase_one_package_qa` exit=1: FAIL: 3 hard package QA failure(s). Report: C:\Users\ahmed\OneDrive\Desktop\FACC\simplyrest_work\outputs\simplyrest-phase1-package-qa-report-2026-06-25.tsv
- `pass` `retrofit_package_qa` exit=0: PASS: retrofit package QA passed. Report: C:\Users\ahmed\OneDrive\Desktop\FACC\simplyrest_work\outputs\simplyrest-retrofit-package-qa-report-2026-06-25.tsv
- `blocker` `phase_one_live_qa` exit=1: Wrote C:\Users\ahmed\OneDrive\Desktop\FACC\simplyrest_work\outputs\simplyrest-phase1-live-qa-report-2026-06-25.tsv Pages checked: 5 Failures: 5 - Mattress Lab: status=404 final=https://simplyrest.com/mattress-lab/ pass=no - How We Test Mattresses: status=404 final=https://simplyrest.com/how-we-test-mattresses/ pass=no - Ferdie Farhad: status=404 final=https://simplyrest.com/ferdie-farhad/ pass=no - Mattress Reviews Hub: status=200 final=https://simplyrest.com/mattress-reviews/ pass=no - Amerisleep AS3 Review: status=200 final=https://simplyrest.com/best-online-mattress/ pass=no
- `pass` `completion_audit` exit=0: Wrote audit: C:\Users\ahmed\OneDrive\Desktop\FACC\simplyrest_work\outputs\simplyrest-phase1-goal-completion-audit-2026-06-25.tsv and C:\Users\ahmed\OneDrive\Desktop\FACC\simplyrest_work\outputs\simplyrest-phase1-goal-completion-audit-2026-06-25.md. package_ready=0, production_blocked=12, needs_approval=1
- `fail` `deploy_bundle_integrity` exit=127: command not found: [WinError 2] The system cannot find the file specified
- `blocker` `phase_one_live_summary` exit=1: 0/5 pages pass; failing=Mattress Lab; How We Test Mattresses; Ferdie Farhad; Mattress Reviews Hub; Amerisleep AS3 Review
- `blocker` `retrofit_live_summary` exit=1: 0/122 pages pass; schema_entity_issue_rows=122
- `blocker` `completion_audit_summary` exit=1: fail=1, needs_approval=1, production_blocked=12
