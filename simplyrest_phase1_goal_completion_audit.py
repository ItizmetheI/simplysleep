#!/usr/bin/env python3
"""
Requirement-level completion audit for the Simply Rest phase-one authority launch.

This script is read-only. It summarizes the original launch requirements against
the current local package evidence and live QA reports so the handoff does not
blur "package ready" with "production complete."
"""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import zipfile
from pathlib import Path
from typing import Iterable


ROOT = Path(__file__).resolve().parents[1]
OUTPUTS = ROOT / "outputs"

LIVE_QA_REPORT = OUTPUTS / "simplyrest-phase1-live-qa-report-2026-06-25.tsv"
RETROFIT_LIVE_QA_REPORT = OUTPUTS / "simplyrest-retrofit-live-qa-report-2026-06-25.tsv"
PACKAGE_QA_REPORT = OUTPUTS / "simplyrest-phase1-package-qa-report-2026-06-25.tsv"
RETROFIT_PACKAGE_QA_REPORT = OUTPUTS / "simplyrest-retrofit-package-qa-report-2026-06-25.tsv"
DEPLOY_BUNDLE = OUTPUTS / "simplyrest-phase1-wordpress-deploy-bundle-2026-06-25.zip"
AS3_MEDIA_MANIFEST = OUTPUTS / "simplyrest-as3-optimized-media-upload-manifest-2026-06-25.tsv"
LAB_MEDIA_MANIFEST = OUTPUTS / "simplyrest-lab-proof-media-upload-manifest-2026-06-25.tsv"
SCORING_TSV = OUTPUTS / "simplyrest-mattress-scoring-matrix-2026-06-25.tsv"
SCORING_JSON = OUTPUTS / "simplyrest-mattress-scoring-matrix-2026-06-25.json"
OFFICIAL_SOURCE_ZIP = OUTPUTS / "simplyrest-official-source-content-pack-2026-06-25.zip"
OFFICIAL_SOURCE_SUMMARY_TSV = OUTPUTS / "simplyrest-official-source-content-pack-summary-2026-06-25.tsv"
OFFICIAL_SOURCE_SUMMARY_MD = OUTPUTS / "simplyrest-official-source-content-pack-summary-2026-06-25.md"
RETROFIT_MANIFEST = OUTPUTS / "simplyrest-retrofit-slug-manifest-2026-06-25.tsv"
RETROFIT_TRACKER = OUTPUTS / "simplyrest-122-url-retrofit-implementation-tracker-2026-06-25.tsv"
INTERNAL_LINKING_MAP = OUTPUTS / "simplyrest-internal-linking-map-2026-06-25.tsv"

DEFAULT_TSV = OUTPUTS / "simplyrest-phase1-goal-completion-audit-2026-06-25.tsv"
DEFAULT_MARKDOWN = OUTPUTS / "simplyrest-phase1-goal-completion-audit-2026-06-25.md"

CORE_PAGE_URLS = (
    "https://simplyrest.com/mattress-lab/",
    "https://simplyrest.com/how-we-test-mattresses/",
    "https://simplyrest.com/ferdie-farhad/",
    "https://simplyrest.com/mattress-reviews/",
    "https://simplyrest.com/mattress-reviews/amerisleep-as3/",
)

REQUIRED_BUNDLE_FILES = (
    "simplyrest_phase1_wp_cli_import.php",
    "simplyrest_phase1_wp_rollback.php",
    "simplyrest_phase1_jsonld_renderer_mu_plugin.php",
    "simplyrest_phase1_live_qa_checker.py",
    "simplyrest_phase1_launch_gate.php",
    "simplyrest_phase1_as3_media_import.php",
    "simplyrest_phase1_lab_media_import.php",
    "simplyrest_phase1_retrofit_batch.php",
    "simplyrest_retrofit_live_qa_checker.py",
    "simplyrest_phase1_goal_completion_audit.py",
    "simplyrest_phase1_local_readiness.py",
    "simplyrest-phase1-goal-completion-audit-2026-06-25.tsv",
    "simplyrest-phase1-goal-completion-audit-2026-06-25.md",
    "simplyrest-phase1-local-readiness-report-2026-06-25.tsv",
    "simplyrest-phase1-local-readiness-report-2026-06-25.md",
    "simplyrest-mattress-scoring-matrix-2026-06-25.tsv",
    "simplyrest-mattress-scoring-matrix-2026-06-25.json",
    "simplyrest-official-source-content-pack-2026-06-25.zip",
    "simplyrest-official-source-content-pack-summary-2026-06-25.tsv",
    "simplyrest-official-source-content-pack-summary-2026-06-25.md",
)


def read_tsv(path: Path) -> list[dict[str, str]]:
    if not path.exists():
        return []
    with path.open(newline="") as f:
        return list(csv.DictReader(f, delimiter="\t"))


def count_status(rows: Iterable[dict[str, str]], field: str, expected: str) -> int:
    return sum(1 for row in rows if row.get(field, "").strip().lower() == expected)


def count_nonempty(rows: Iterable[dict[str, str]], field: str) -> int:
    return sum(1 for row in rows if row.get(field, "").strip())


def yes(value: str) -> bool:
    return value.strip().lower() == "yes"


def semijoin(values: Iterable[str]) -> str:
    cleaned = [value.strip() for value in values if value and value.strip()]
    return "; ".join(cleaned) if cleaned else "none"


def qa_counts(path: Path) -> tuple[int, int, int]:
    rows = read_tsv(path)
    fails = count_status(rows, "status", "fail")
    warnings = count_status(rows, "status", "warn")
    return len(rows), fails, warnings


def bundle_status() -> tuple[bool, str]:
    if not DEPLOY_BUNDLE.exists():
        return False, "deploy bundle missing"
    try:
        with zipfile.ZipFile(DEPLOY_BUNDLE) as archive:
            bad_file = archive.testzip()
            names = set(archive.namelist())
    except zipfile.BadZipFile as exc:
        return False, f"zip unreadable: {exc}"
    if bad_file:
        return False, f"zip contains unreadable member: {bad_file}"
    missing = [name for name in REQUIRED_BUNDLE_FILES if name not in names]
    if missing:
        return False, "missing from bundle: " + semijoin(missing)
    return True, f"bundle readable with {len(names)} files and required audit/deploy files present"


def add_row(
    rows: list[dict[str, str]],
    requirement_id: str,
    requirement: str,
    status: str,
    evidence: str,
    blocker: str,
    next_action: str,
    evidence_files: Iterable[Path | str],
) -> None:
    rows.append(
        {
            "requirement_id": requirement_id,
            "requirement": requirement,
            "status": status,
            "evidence": evidence,
            "blocker": blocker,
            "next_action": next_action,
            "evidence_files": semijoin(str(Path(file).name) for file in evidence_files),
        }
    )


def live_page_blocker(row: dict[str, str]) -> str:
    blockers: list[str] = []
    if not yes(row.get("status_ok", "")):
        blockers.append(f"status={row.get('status', '')}")
    if not yes(row.get("final_url_ok", "")):
        blockers.append(f"final_url={row.get('final_url', '')}")
    if not yes(row.get("canonical_ok", "")):
        blockers.append(f"canonical={row.get('canonical', '') or 'missing'}")
    if row.get("missing_content_markers", "").strip():
        blockers.append("missing markers: " + row["missing_content_markers"])
    if row.get("missing_required_links", "").strip():
        blockers.append("missing links: " + row["missing_required_links"])
    if row.get("schema_entity_issues", "").strip():
        blockers.append("schema: " + row["schema_entity_issues"])
    if row.get("media_evidence_issues", "").strip():
        blockers.append("media: " + row["media_evidence_issues"])
    if row.get("forbidden_public_markers", "").strip():
        blockers.append("forbidden markers: " + row["forbidden_public_markers"])
    if not yes(row.get("affiliate_disclosure_found", "")):
        blockers.append("affiliate disclosure missing")
    if not yes(row.get("medical_limit_found", "")):
        blockers.append("medical limitation missing")
    return semijoin(blockers)


def media_issue_summary(row: dict[str, str], label: str) -> str:
    if not row:
        return f"{label} live QA row missing"
    issue = row.get("media_evidence_issues", "").strip()
    if issue:
        return issue
    if not yes(row.get("overall_pass", "")):
        return f"{label} page not passing live QA"
    return "none"


def build_rows() -> list[dict[str, str]]:
    rows: list[dict[str, str]] = []
    live_rows = read_tsv(LIVE_QA_REPORT)
    live_by_url = {row.get("url", ""): row for row in live_rows}
    live_passes = count_status(live_rows, "overall_pass", "yes")

    package_rows, package_fails, package_warnings = qa_counts(PACKAGE_QA_REPORT)
    retrofit_package_rows, retrofit_package_fails, retrofit_package_warnings = qa_counts(RETROFIT_PACKAGE_QA_REPORT)
    bundle_ok, bundle_detail = bundle_status()

    local_package_ready = (
        package_rows > 0
        and package_fails == 0
        and package_warnings == 0
        and retrofit_package_rows > 0
        and retrofit_package_fails == 0
        and bundle_ok
    )

    add_row(
        rows,
        "0",
        "Local deployment package is ready for a WordPress operator",
        "package_ready" if local_package_ready else "fail",
        (
            f"phase-one package QA rows={package_rows}, failures={package_fails}, warnings={package_warnings}; "
            f"retrofit package QA rows={retrofit_package_rows}, failures={retrofit_package_fails}, warnings={retrofit_package_warnings}; "
            f"{bundle_detail}"
        ),
        "none" if local_package_ready else "local package evidence is incomplete or failing",
        "Use the deploy instructions only after package QA is green and the bundle contains the required files.",
        (PACKAGE_QA_REPORT, RETROFIT_PACKAGE_QA_REPORT, DEPLOY_BUNDLE, SCORING_TSV, OFFICIAL_SOURCE_SUMMARY_TSV),
    )

    all_core_live = len(live_rows) == 5 and live_passes == 5
    failed_live = [row.get("page", "") for row in live_rows if not yes(row.get("overall_pass", ""))]
    add_row(
        rows,
        "1",
        "Publish the five core proof pages first",
        "pass" if all_core_live else "production_blocked",
        f"{live_passes}/5 core URLs pass live QA; failing pages: {semijoin(failed_live)}",
        "none" if all_core_live else "core pages are not all published with the new proof layer",
        "Run the WP-CLI page importer, publish approved pages, fix AS3 redirect, then rerun live QA.",
        (LIVE_QA_REPORT,),
    )

    for index, url in enumerate(CORE_PAGE_URLS, start=1):
        row = live_by_url.get(url, {})
        page_name = row.get("page", url)
        passed = yes(row.get("overall_pass", ""))
        add_row(
            rows,
            f"1.{index}",
            f"Core proof page live QA: {page_name}",
            "pass" if passed else "production_blocked",
            (
                f"status={row.get('status', 'missing')}; final_url={row.get('final_url', 'missing')}; "
                f"canonical_ok={row.get('canonical_ok', 'missing')}; schema_scripts={row.get('schema_script_count', 'missing')}; "
                f"overall_pass={row.get('overall_pass', 'missing')}"
            ),
            "none" if passed else live_page_blocker(row) if row else "URL not present in live QA report",
            "Publish or replace the WordPress page and rerun live QA until this row passes.",
            (LIVE_QA_REPORT,),
        )

    as3_media_rows = read_tsv(AS3_MEDIA_MANIFEST)
    lab_media_rows = read_tsv(LAB_MEDIA_MANIFEST)
    as3_video_count = sum(1 for row in as3_media_rows if row.get("media_type") == "video")
    as3_image_count = sum(1 for row in as3_media_rows if row.get("media_type") == "image")
    candidate_count = count_status(lab_media_rows, "approval_status", "candidate_requires_approval")
    deployable_lab_count = count_status(lab_media_rows, "approval_status", "deployable_rebrand_crop")
    approved_headshot_count = count_status(lab_media_rows, "approval_status", "deployable_user_supplied_headshot")
    as3_live = live_by_url.get("https://simplyrest.com/mattress-reviews/amerisleep-as3/", {})
    ferdie_live = live_by_url.get("https://simplyrest.com/ferdie-farhad/", {})
    live_media_ok = (
        as3_live
        and not as3_live.get("media_evidence_issues", "").strip()
        and ferdie_live
        and not ferdie_live.get("media_evidence_issues", "").strip()
        and yes(as3_live.get("overall_pass", ""))
        and yes(ferdie_live.get("overall_pass", ""))
    )
    add_row(
        rows,
        "2",
        "Upload and attach real media evidence",
        "pass" if live_media_ok else "production_blocked",
        (
            f"local AS3 manifest rows={len(as3_media_rows)} with images={as3_image_count}, videos={as3_video_count}; "
            f"lab manifest rows={len(lab_media_rows)} with deployable_lab={deployable_lab_count}, approved_headshot={approved_headshot_count}, candidate={candidate_count}; "
            f"AS3 live media issues={media_issue_summary(as3_live, 'AS3')}; "
            f"Ferdie live media issues={media_issue_summary(ferdie_live, 'Ferdie')}"
        ),
        "none" if live_media_ok else "media package exists locally, but production pages do not yet prove attached WordPress-hosted media",
        "Run AS3 and lab media importers; use the approved Ferdie headshot as primary and keep the candidate action crop optional unless approved.",
        (AS3_MEDIA_MANIFEST, LAB_MEDIA_MANIFEST, LIVE_QA_REPORT),
    )

    add_row(
        rows,
        "2.1",
        "Keep optional Ferdie action candidate approval-gated",
        "needs_approval" if candidate_count else "pass",
        f"{approved_headshot_count} approved headshot rows and {candidate_count} candidate action row marked candidate_requires_approval",
        "optional action crop still requires approval; approved headshot is available for primary Ferdie media" if candidate_count else "none",
        "Use the supplied Ferdie headshot for launch; use the candidate action crop only with explicit approval.",
        (LAB_MEDIA_MANIFEST,),
    )

    schema_live_ok = (
        len(live_rows) == 5
        and all(int(row.get("schema_script_count") or 0) > 0 for row in live_rows)
        and all(not row.get("missing_schema_types", "").strip() for row in live_rows)
        and all(not row.get("schema_entity_issues", "").strip() for row in live_rows)
        and all(yes(row.get("overall_pass", "")) for row in live_rows)
    )
    schema_issue_pages = [
        row.get("page", "")
        for row in live_rows
        if int(row.get("schema_script_count") or 0) == 0
        or row.get("missing_schema_types", "").strip()
        or row.get("schema_entity_issues", "").strip()
    ]
    add_row(
        rows,
        "3",
        "Implement Person, Article, Review, FAQ, and Breadcrumb schema",
        "pass" if schema_live_ok else "production_blocked",
        f"schema issue pages: {semijoin(schema_issue_pages)}",
        "none" if schema_live_ok else "live pages have no parseable JSON-LD or unresolved entity checks",
        "Install the MU JSON-LD renderer and confirm each page has _simplyrest_json_ld before rerunning live QA.",
        (LIVE_QA_REPORT,),
    )

    disclosure_live_ok = (
        len(live_rows) == 5
        and all(yes(row.get("affiliate_disclosure_found", "")) for row in live_rows)
        and all(yes(row.get("medical_limit_found", "")) for row in live_rows)
    )
    disclosure_issue_pages = [
        row.get("page", "")
        for row in live_rows
        if not yes(row.get("affiliate_disclosure_found", "")) or not yes(row.get("medical_limit_found", ""))
    ]
    add_row(
        rows,
        "4",
        "Add affiliate, methodology, medical limitation, and author/lead tester disclosures",
        "pass" if disclosure_live_ok else "production_blocked",
        f"disclosure issue pages: {semijoin(disclosure_issue_pages)}",
        "none" if disclosure_live_ok else "required disclosures are packaged but not visible on production pages",
        "Publish imported content and verify disclosure modules appear before commercial CTAs where applicable.",
        (LIVE_QA_REPORT,),
    )

    retrofit_rows = read_tsv(RETROFIT_LIVE_QA_REPORT)
    retrofit_passes = count_status(retrofit_rows, "overall_pass", "yes")
    retrofit_reachable = sum(1 for row in retrofit_rows if yes(row.get("status_ok", "")) and yes(row.get("final_url_ok", "")))
    manifest_rows = read_tsv(RETROFIT_MANIFEST)
    tracker_rows = read_tsv(RETROFIT_TRACKER)
    claim_sensitive = count_status(manifest_rows, "claim_sensitive", "yes")
    risky_claim_rows = count_nonempty(retrofit_rows, "risky_claim_patterns_found")
    retrofit_live_ok = len(retrofit_rows) == len(manifest_rows) == 122 and retrofit_passes == 122
    add_row(
        rows,
        "5",
        "Retrofit the existing 122 Simply Rest URLs",
        "pass" if retrofit_live_ok else "production_blocked",
        (
            f"manifest rows={len(manifest_rows)}, tracker rows={len(tracker_rows)}, live QA rows={len(retrofit_rows)}, "
            f"reachable={retrofit_reachable}, passing={retrofit_passes}, claim_sensitive={claim_sensitive}, risky_claim_rows={risky_claim_rows}"
        ),
        "none" if retrofit_live_ok else "122 URLs are reachable, but proof-layer retrofits are not deployed live",
        "Run retrofit batches by priority after phase-one proof pages pass; rerun retrofit live QA after each batch.",
        (RETROFIT_MANIFEST, RETROFIT_TRACKER, RETROFIT_LIVE_QA_REPORT),
    )

    link_map_rows = read_tsv(INTERNAL_LINKING_MAP)
    live_links_ok = len(live_rows) == 5 and all(not row.get("missing_required_links", "").strip() for row in live_rows)
    link_issue_pages = [row.get("page", "") for row in live_rows if row.get("missing_required_links", "").strip()]
    add_row(
        rows,
        "6",
        "Build internal linking between old guides, Lab, reviews, best-of pages, and How We Test",
        "pass" if live_links_ok else "production_blocked",
        f"internal linking map rows={len(link_map_rows)}; live link issue pages: {semijoin(link_issue_pages)}",
        "none" if live_links_ok else "link map is packaged, but production pages do not yet expose all required links",
        "Deploy core pages first, then apply the internal linking map through the retrofit batch and manual hub edits.",
        (INTERNAL_LINKING_MAP, LIVE_QA_REPORT),
    )

    live_forbidden_pages = [row.get("page", "") for row in live_rows if row.get("forbidden_public_markers", "").strip()]
    qa_ready = (
        local_package_ready
        and all_core_live
        and retrofit_live_ok
        and not live_forbidden_pages
        and risky_claim_rows == 0
    )
    add_row(
        rows,
        "7",
        "QA before launch: desktop/mobile, schema, links, canonicals, private links, and commercial disclosures",
        "pass" if qa_ready else "production_blocked",
        (
            f"package_ready={local_package_ready}; core_live_pass={live_passes}/5; retrofit_live_pass={retrofit_passes}/{len(retrofit_rows)}; "
            f"forbidden_public_marker_pages={semijoin(live_forbidden_pages)}; risky_claim_rows={risky_claim_rows}"
        ),
        "none" if qa_ready else "production cannot pass final QA until phase-one pages and retrofits are deployed and rerun clean",
        "Use the launch gate and live QA reports as the final go/no-go after WordPress deployment.",
        (PACKAGE_QA_REPORT, LIVE_QA_REPORT, RETROFIT_LIVE_QA_REPORT),
    )

    return rows


def write_tsv(rows: list[dict[str, str]], output: Path) -> None:
    output.parent.mkdir(parents=True, exist_ok=True)
    with output.open("w", newline="") as f:
        writer = csv.DictWriter(
            f,
            fieldnames=(
                "requirement_id",
                "requirement",
                "status",
                "evidence",
                "blocker",
                "next_action",
                "evidence_files",
            ),
            delimiter="\t",
        )
        writer.writeheader()
        writer.writerows(rows)


def write_markdown(rows: list[dict[str, str]], output: Path) -> None:
    production_blocked = [row for row in rows if row["status"] == "production_blocked"]
    package_ready = [row for row in rows if row["status"] == "package_ready"]
    needs_approval = [row for row in rows if row["status"] == "needs_approval"]
    generated_at = dt.datetime.now(dt.timezone.utc).strftime("%Y-%m-%d %H:%M:%S UTC")

    lines = [
        "# Simply Rest Phase 1 Goal Completion Audit",
        "",
        f"Generated: {generated_at}",
        "",
        "## TL;DR",
        "",
        "- Production is not launch-ready yet.",
        f"- Local package-ready rows: {len(package_ready)}.",
        f"- Production-blocked rows: {len(production_blocked)}.",
        f"- Approval-gated rows: {len(needs_approval)}.",
        "",
        "## Current Go / No-Go",
        "",
    ]
    if production_blocked:
        lines.append("No-go. The WordPress deploy package is ready for an operator, but live Simply Rest still fails the proof-page and retrofit gates.")
    else:
        lines.append("Go, based on the current audit rows.")
    lines.extend(["", "## Blocking Requirements", ""])
    if production_blocked:
        for row in production_blocked:
            lines.append(f"- {row['requirement_id']}: {row['requirement']} - {row['blocker']}")
    else:
        lines.append("- None.")
    lines.extend(["", "## Approval-Gated Items", ""])
    if needs_approval:
        for row in needs_approval:
            lines.append(f"- {row['requirement_id']}: {row['requirement']} - {row['blocker']}")
    else:
        lines.append("- None.")
    lines.extend(["", "## Next Actions", ""])
    seen_actions: set[str] = set()
    for row in rows:
        action = row["next_action"]
        if action in seen_actions or row["status"] == "pass":
            continue
        seen_actions.add(action)
        lines.append(f"- {action}")
    lines.extend(["", "## Evidence Files", ""])
    evidence_files = sorted({file for row in rows for file in row["evidence_files"].split("; ") if file and file != "none"})
    for file in evidence_files:
        lines.append(f"- `{file}`")
    lines.append("")
    output.write_text("\n".join(lines))


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--output", default=str(DEFAULT_TSV), help="TSV audit output path")
    parser.add_argument("--markdown", default=str(DEFAULT_MARKDOWN), help="Markdown audit output path")
    args = parser.parse_args()

    rows = build_rows()
    write_tsv(rows, Path(args.output))
    write_markdown(rows, Path(args.markdown))

    production_blocked = sum(1 for row in rows if row["status"] == "production_blocked")
    package_ready = sum(1 for row in rows if row["status"] == "package_ready")
    needs_approval = sum(1 for row in rows if row["status"] == "needs_approval")
    print(
        f"Wrote audit: {args.output} and {args.markdown}. "
        f"package_ready={package_ready}, production_blocked={production_blocked}, needs_approval={needs_approval}"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
