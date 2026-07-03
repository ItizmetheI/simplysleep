# Simply Rest Local Readiness Report

Generated: 2026-06-27 10:22:43 UTC

## TL;DR

- Local validators ran in the operator sequence.
- Status counts: blocker=3, fail=4.
- Go/no-go: no-go

## Blocking Rows

- `phase_one_package_qa`: C:\Users\ahmed\AppData\Local\Python\pythoncore-3.14-64\python.exe: can't open file 'C:\\Users\\ahmed\\OneDrive\\Desktop\\FACC\\simplyrest_work\\outputs\\simplyrest_phase1_package_qa.py': [Errno 2] No such file or directory
- `retrofit_package_qa`: C:\Users\ahmed\AppData\Local\Python\pythoncore-3.14-64\python.exe: can't open file 'C:\\Users\\ahmed\\OneDrive\\Desktop\\FACC\\simplyrest_work\\outputs\\simplyrest_retrofit_package_qa.py': [Errno 2] No such file or directory
- `completion_audit`: C:\Users\ahmed\AppData\Local\Python\pythoncore-3.14-64\python.exe: can't open file 'C:\\Users\\ahmed\\OneDrive\\Desktop\\FACC\\simplyrest_work\\outputs\\simplyrest_phase1_goal_completion_audit.py': [Errno 2] No such file or directory
- `deploy_bundle_integrity`: unzip: cannot find or open C:\Users\ahmed\OneDrive\Desktop\FACC\simplyrest_work\outputs\simplyrest-phase1-wordpress-deploy-bundle-2026-06-25.zip, C:\Users\ahmed\OneDrive\Desktop\FACC\simplyrest_work\outputs\simplyrest-phase1-wordpress-deploy-bundle-2026-06-25.zip.zip or C:\Users\ahmed\OneDrive\Desktop\FACC\simplyrest_work\outputs\simplyrest-phase1-wordpress-deploy-bundle-2026-06-25.zip.ZIP.
- `phase_one_live_summary`: 0/5 pages pass; failing=Mattress Lab; How We Test Mattresses; Ferdie Farhad; Mattress Reviews Hub; Amerisleep AS3 Review
- `retrofit_live_summary`: 0/122 pages pass; schema_entity_issue_rows=122
- `completion_audit_summary`: needs_approval=1, package_ready=1, production_blocked=12

## Steps

- `fail` `phase_one_package_qa` exit=2: C:\Users\ahmed\AppData\Local\Python\pythoncore-3.14-64\python.exe: can't open file 'C:\\Users\\ahmed\\OneDrive\\Desktop\\FACC\\simplyrest_work\\outputs\\simplyrest_phase1_package_qa.py': [Errno 2] No such file or directory
- `fail` `retrofit_package_qa` exit=2: C:\Users\ahmed\AppData\Local\Python\pythoncore-3.14-64\python.exe: can't open file 'C:\\Users\\ahmed\\OneDrive\\Desktop\\FACC\\simplyrest_work\\outputs\\simplyrest_retrofit_package_qa.py': [Errno 2] No such file or directory
- `fail` `completion_audit` exit=2: C:\Users\ahmed\AppData\Local\Python\pythoncore-3.14-64\python.exe: can't open file 'C:\\Users\\ahmed\\OneDrive\\Desktop\\FACC\\simplyrest_work\\outputs\\simplyrest_phase1_goal_completion_audit.py': [Errno 2] No such file or directory
- `fail` `deploy_bundle_integrity` exit=9: unzip: cannot find or open C:\Users\ahmed\OneDrive\Desktop\FACC\simplyrest_work\outputs\simplyrest-phase1-wordpress-deploy-bundle-2026-06-25.zip, C:\Users\ahmed\OneDrive\Desktop\FACC\simplyrest_work\outputs\simplyrest-phase1-wordpress-deploy-bundle-2026-06-25.zip.zip or C:\Users\ahmed\OneDrive\Desktop\FACC\simplyrest_work\outputs\simplyrest-phase1-wordpress-deploy-bundle-2026-06-25.zip.ZIP.
- `blocker` `phase_one_live_summary` exit=1: 0/5 pages pass; failing=Mattress Lab; How We Test Mattresses; Ferdie Farhad; Mattress Reviews Hub; Amerisleep AS3 Review
- `blocker` `retrofit_live_summary` exit=1: 0/122 pages pass; schema_entity_issue_rows=122
- `blocker` `completion_audit_summary` exit=1: needs_approval=1, package_ready=1, production_blocked=12
