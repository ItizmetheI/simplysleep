#!/usr/bin/env python3
"""
Local readiness runner for the Simply Rest phase-one launch package.

This performs no WordPress writes. It runs the local package validators,
refreshes the five-page live QA report, regenerates the requirement audit, and
checks deploy-bundle zip integrity so an operator can get one go/no-go summary.
"""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import subprocess
import sys
from dataclasses import dataclass
from pathlib import Path


ROOT = Path(__file__).resolve().parent
OUTPUTS = ROOT

PHASE_PACKAGE_QA = OUTPUTS / "simplyrest_phase1_package_qa.py"
RETROFIT_PACKAGE_QA = OUTPUTS / "simplyrest_retrofit_package_qa.py"
PHASE_LIVE_QA = OUTPUTS / "simplyrest_phase1_live_qa_checker.py"
RETROFIT_LIVE_QA = OUTPUTS / "simplyrest_retrofit_live_qa_checker.py"
GOAL_AUDIT = OUTPUTS / "simplyrest_phase1_goal_completion_audit.py"
DEPLOY_BUNDLE = OUTPUTS / "simplyrest-phase1-wordpress-deploy-bundle-2026-06-25.zip"

PHASE_PACKAGE_REPORT = OUTPUTS / "simplyrest-phase1-package-qa-report-2026-06-25.tsv"
RETROFIT_PACKAGE_REPORT = OUTPUTS / "simplyrest-retrofit-package-qa-report-2026-06-25.tsv"
PHASE_LIVE_REPORT = OUTPUTS / "simplyrest-phase1-live-qa-report-2026-06-25.tsv"
RETROFIT_LIVE_REPORT = OUTPUTS / "simplyrest-retrofit-live-qa-report-2026-06-25.tsv"
GOAL_AUDIT_TSV = OUTPUTS / "simplyrest-phase1-goal-completion-audit-2026-06-25.tsv"
GOAL_AUDIT_MD = OUTPUTS / "simplyrest-phase1-goal-completion-audit-2026-06-25.md"

DEFAULT_TSV = OUTPUTS / "simplyrest-phase1-local-readiness-report-2026-06-25.tsv"
DEFAULT_MD = OUTPUTS / "simplyrest-phase1-local-readiness-report-2026-06-25.md"


@dataclass(frozen=True)
class StepResult:
    step: str
    status: str
    exit_code: int
    command: str
    detail: str


def run_command(step: str, cmd: list[str], allow_nonzero: bool = False) -> StepResult:
    try:
        proc = subprocess.run(
            cmd, cwd=ROOT, text=True, capture_output=True, check=False, encoding="utf-8", errors="replace"
        )
    except FileNotFoundError as exc:
        return StepResult(
            step=step,
            status="fail",
            exit_code=127,
            command=" ".join(cmd),
            detail=one_line(f"command not found: {exc}"),
        )
    output = (proc.stdout + "\n" + proc.stderr).strip()
    if proc.returncode == 0:
        status = "pass"
    elif allow_nonzero:
        status = "blocker"
    else:
        status = "fail"
    return StepResult(
        step=step,
        status=status,
        exit_code=proc.returncode,
        command=" ".join(cmd),
        detail=one_line(output),
    )


def one_line(text: str, limit: int = 600) -> str:
    cleaned = " ".join((text or "").split())
    if len(cleaned) <= limit:
        return cleaned
    return cleaned[: limit - 3] + "..."


def read_tsv(path: Path) -> list[dict[str, str]]:
    if not path.exists():
        return []
    with path.open(newline="", encoding="utf-8") as f:
        return list(csv.DictReader(f, delimiter="\t"))


def yes(value: str) -> bool:
    return value.strip().lower() == "yes"


def append_report_summaries(results: list[StepResult]) -> list[StepResult]:
    rows = list(results)

    phase_rows = read_tsv(PHASE_LIVE_REPORT)
    if phase_rows:
        passing = sum(1 for row in phase_rows if yes(row.get("overall_pass", "")))
        failing_pages = [row.get("page", "") for row in phase_rows if not yes(row.get("overall_pass", ""))]
        rows.append(
            StepResult(
                step="phase_one_live_summary",
                status="pass" if passing == len(phase_rows) else "blocker",
                exit_code=0 if passing == len(phase_rows) else 1,
                command=f"read {PHASE_LIVE_REPORT.name}",
                detail=f"{passing}/{len(phase_rows)} pages pass; failing={'; '.join(failing_pages) if failing_pages else 'none'}",
            )
        )

    retrofit_rows = read_tsv(RETROFIT_LIVE_REPORT)
    if retrofit_rows:
        passing = sum(1 for row in retrofit_rows if yes(row.get("overall_pass", "")))
        schema_entity_issues = sum(1 for row in retrofit_rows if row.get("schema_entity_issues", "").strip())
        rows.append(
            StepResult(
                step="retrofit_live_summary",
                status="pass" if passing == len(retrofit_rows) else "blocker",
                exit_code=0 if passing == len(retrofit_rows) else 1,
                command=f"read {RETROFIT_LIVE_REPORT.name}",
                detail=f"{passing}/{len(retrofit_rows)} pages pass; schema_entity_issue_rows={schema_entity_issues}",
            )
        )

    audit_rows = read_tsv(GOAL_AUDIT_TSV)
    if audit_rows:
        status_counts: dict[str, int] = {}
        for row in audit_rows:
            status_counts[row.get("status", "")] = status_counts.get(row.get("status", ""), 0) + 1
        has_blockers = status_counts.get("production_blocked", 0) > 0 or status_counts.get("fail", 0) > 0
        rows.append(
            StepResult(
                step="completion_audit_summary",
                status="blocker" if has_blockers else "pass",
                exit_code=1 if has_blockers else 0,
                command=f"read {GOAL_AUDIT_TSV.name}",
                detail=", ".join(f"{status}={count}" for status, count in sorted(status_counts.items())),
            )
        )

    return rows


def write_tsv(results: list[StepResult], output: Path) -> None:
    output.parent.mkdir(parents=True, exist_ok=True)
    with output.open("w", newline="", encoding="utf-8") as f:
        writer = csv.DictWriter(f, fieldnames=("step", "status", "exit_code", "command", "detail"), delimiter="\t")
        writer.writeheader()
        for result in results:
            writer.writerow(
                {
                    "step": result.step,
                    "status": result.status,
                    "exit_code": str(result.exit_code),
                    "command": result.command,
                    "detail": result.detail,
                }
            )


def write_markdown(results: list[StepResult], output: Path) -> None:
    status_counts: dict[str, int] = {}
    for result in results:
        status_counts[result.status] = status_counts.get(result.status, 0) + 1
    blockers = [result for result in results if result.status in {"blocker", "fail"}]
    generated_at = dt.datetime.now(dt.timezone.utc).strftime("%Y-%m-%d %H:%M:%S UTC")

    lines = [
        "# Simply Rest Local Readiness Report",
        "",
        f"Generated: {generated_at}",
        "",
        "## TL;DR",
        "",
        "- Local validators ran in the operator sequence.",
        f"- Status counts: {', '.join(f'{status}={count}' for status, count in sorted(status_counts.items()))}.",
        "- Go/no-go: " + ("no-go" if blockers else "go"),
        "",
        "## Blocking Rows",
        "",
    ]
    if blockers:
        for result in blockers:
            lines.append(f"- `{result.step}`: {result.detail}")
    else:
        lines.append("- None.")
    lines.extend(["", "## Steps", ""])
    for result in results:
        lines.append(f"- `{result.status}` `{result.step}` exit={result.exit_code}: {result.detail}")
    lines.append("")
    output.write_text("\n".join(lines), encoding="utf-8")


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--output", default=str(DEFAULT_TSV), help="TSV readiness output path")
    parser.add_argument("--markdown", default=str(DEFAULT_MD), help="Markdown readiness output path")
    parser.add_argument("--skip-live", action="store_true", help="Skip the five-page production live QA fetch")
    parser.add_argument(
        "--refresh-retrofit-live",
        action="store_true",
        help="Refresh the full 122-page retrofit live QA report. This is slower and should usually run after retrofit batches.",
    )
    args = parser.parse_args()

    results: list[StepResult] = []
    py = sys.executable
    results.append(
        run_command(
            "phase_one_package_qa",
            [py, "-B", str(PHASE_PACKAGE_QA), "--output", str(PHASE_PACKAGE_REPORT), "--skip-local-readiness-report"],
        )
    )
    results.append(
        run_command(
            "retrofit_package_qa",
            [py, "-B", str(RETROFIT_PACKAGE_QA), "--output", str(RETROFIT_PACKAGE_REPORT)],
        )
    )
    if not args.skip_live:
        results.append(
            run_command(
                "phase_one_live_qa",
                [py, str(PHASE_LIVE_QA), "--output", str(PHASE_LIVE_REPORT)],
                allow_nonzero=True,
            )
        )
    if args.refresh_retrofit_live:
        results.append(
            run_command(
                "retrofit_live_qa",
                [py, str(RETROFIT_LIVE_QA), "--output", str(RETROFIT_LIVE_REPORT)],
                allow_nonzero=True,
            )
        )
    results.append(
        run_command(
            "completion_audit",
            [py, "-B", str(GOAL_AUDIT), "--output", str(GOAL_AUDIT_TSV), "--markdown", str(GOAL_AUDIT_MD)],
        )
    )
    results.append(run_command("deploy_bundle_integrity", ["unzip", "-t", str(DEPLOY_BUNDLE)]))

    results = append_report_summaries(results)
    write_tsv(results, Path(args.output))
    write_markdown(results, Path(args.markdown))

    hard_failures = [result for result in results if result.status == "fail"]
    blockers = [result for result in results if result.status == "blocker"]
    print(f"Wrote {args.output} and {args.markdown}")
    print(f"Hard failures: {len(hard_failures)}")
    print(f"Production blockers: {len(blockers)}")
    return 2 if hard_failures else 1 if blockers else 0


if __name__ == "__main__":
    raise SystemExit(main())
