#!/usr/bin/env python3
"""
Local QA for the Simply Rest 122-URL retrofit and internal-linking package.

This validates the rollout files before WordPress retrofit batches are run. It
does not touch production.
"""

from __future__ import annotations

import argparse
import csv
import re
import sys
from collections import Counter
from dataclasses import dataclass
from pathlib import Path
from urllib.parse import urlparse


ROOT = Path(__file__).resolve().parent
OUTPUTS = ROOT

MANIFEST = OUTPUTS / "simplyrest-retrofit-slug-manifest-2026-06-25.tsv"
INTERNAL_LINKING_MAP = OUTPUTS / "simplyrest-internal-linking-map-2026-06-25.tsv"
RETROFIT_BATCH = OUTPUTS / "simplyrest_phase1_retrofit_batch.php"
RETROFIT_LIVE_QA = OUTPUTS / "simplyrest_retrofit_live_qa_checker.py"
RETROFIT_INSTRUCTIONS = OUTPUTS / "simplyrest-phase1-retrofit-batch-instructions-2026-06-25.md"
RETROFIT_LIVE_QA_REPORT = OUTPUTS / "simplyrest-retrofit-live-qa-report-2026-06-25.tsv"
RETROFIT_BASELINE = OUTPUTS / "simplyrest-retrofit-live-qa-baseline-2026-06-25.md"

EXPECTED_MANIFEST_ROWS = 122
EXPECTED_LINK_TARGETS = {
    "https://simplyrest.com/mattress-lab/",
    "https://simplyrest.com/how-we-test-mattresses/",
    "https://simplyrest.com/ferdie-farhad/",
    "https://simplyrest.com/mattress-reviews/",
    "https://simplyrest.com/mattress-reviews/amerisleep-as3/",
    "https://simplyrest.com/mattress-comparison/",
    "https://simplyrest.com/mattress-stores/",
    "https://simplyrest.com/best-mattress-2026/",
}
VALID_PAGE_TYPES = {"best-of", "comparison", "guide", "local", "sales"}
VALID_PRIORITIES = {"P0", "P1", "P1-claim-sensitive", "P2", "P3"}
FORBIDDEN_MARKERS = (
    "drive.google.com",
    "docs.google.com",
    "It seems we can",
    "physical therapist",
    "licensed massage therapist",
    "sports performance specialist",
)
CLAIM_SENSITIVE_SLUG_PATTERNS = (
    r"pain",
    r"reflux",
    r"snor",
    r"kidney",
    r"oxygen",
    r"sleep-quality",
    r"sleep-health",
    r"jet-lag",
    r"natural-sleep-aids",
    r"reset-sleep-clock",
    r"stages-of-sleep",
    r"technology-and-sleep",
    r"trouble-sleeping",
    r"sleep-habits",
    r"sleep-schedule",
)


@dataclass(frozen=True)
class RetrofitRow:
    slug: str
    brief_file: str
    page_type: str
    priority: str
    claim_sensitive: bool


class Reporter:
    def __init__(self) -> None:
        self.rows: list[dict[str, str]] = []

    def add(self, status: str, artifact: str, check: str, detail: str) -> None:
        self.rows.append({"status": status, "artifact": artifact, "check": check, "detail": detail})

    def pass_if(self, condition: bool, artifact: str, check: str, pass_detail: str, fail_detail: str) -> None:
        self.add("pass" if condition else "fail", artifact, check, pass_detail if condition else fail_detail)

    @property
    def failures(self) -> list[dict[str, str]]:
        return [row for row in self.rows if row["status"] == "fail"]

    def write(self, path: Path) -> None:
        path.parent.mkdir(parents=True, exist_ok=True)
        with path.open("w", newline="") as f:
            writer = csv.DictWriter(f, fieldnames=("status", "artifact", "check", "detail"), delimiter="\t")
            writer.writeheader()
            writer.writerows(self.rows)


def read_tsv(path: Path) -> list[dict[str, str]]:
    with path.open(newline="") as f:
        return list(csv.DictReader(f, delimiter="\t"))


def parse_manifest(path: Path) -> list[RetrofitRow]:
    rows: list[RetrofitRow] = []
    for row in read_tsv(path):
        rows.append(
            RetrofitRow(
                slug=row.get("slug", "").strip(),
                brief_file=row.get("brief_file", "").strip(),
                page_type=row.get("page_type", "").strip(),
                priority=row.get("priority", "").strip(),
                claim_sensitive=row.get("claim_sensitive", "").strip().lower() == "yes",
            )
        )
    return rows


def slug_from_url(url: str) -> str:
    parsed = urlparse(url)
    return parsed.path.strip("/") or "home"


def is_simplyrest_url(url: str) -> bool:
    parsed = urlparse(url)
    return parsed.scheme == "https" and parsed.netloc == "simplyrest.com"


def should_be_claim_sensitive(slug: str) -> bool:
    return any(re.search(pattern, slug, flags=re.I) for pattern in CLAIM_SENSITIVE_SLUG_PATTERNS)


def check_required_files(reporter: Reporter) -> None:
    for path in (
        MANIFEST,
        INTERNAL_LINKING_MAP,
        RETROFIT_BATCH,
        RETROFIT_LIVE_QA,
        RETROFIT_INSTRUCTIONS,
        RETROFIT_LIVE_QA_REPORT,
        RETROFIT_BASELINE,
    ):
        reporter.pass_if(path.exists(), path.name, "file exists", "found", "missing")


def check_manifest(reporter: Reporter, rows: list[RetrofitRow]) -> None:
    slugs = [row.slug for row in rows]
    duplicate_slugs = sorted(slug for slug, count in Counter(slugs).items() if count > 1)
    invalid_slugs = sorted(row.slug for row in rows if not re.fullmatch(r"[a-z0-9][a-z0-9\-]*", row.slug))
    invalid_briefs = sorted(row.slug for row in rows if row.brief_file != f"{row.slug}.md")
    invalid_types = sorted(row.slug for row in rows if row.page_type not in VALID_PAGE_TYPES)
    invalid_priorities = sorted(row.slug for row in rows if row.priority not in VALID_PRIORITIES)
    claim_priority_mismatch = sorted(
        row.slug
        for row in rows
        if (row.claim_sensitive and row.priority != "P1-claim-sensitive")
        or (row.priority == "P1-claim-sensitive" and not row.claim_sensitive)
    )
    missing_claim_flags = sorted(row.slug for row in rows if should_be_claim_sensitive(row.slug) and not row.claim_sensitive)

    reporter.pass_if(len(rows) == EXPECTED_MANIFEST_ROWS, MANIFEST.name, "row count", f"{EXPECTED_MANIFEST_ROWS} rows", f"{len(rows)} rows")
    reporter.pass_if(not duplicate_slugs, MANIFEST.name, "unique slugs", "all slugs unique", "duplicates: " + "; ".join(duplicate_slugs))
    reporter.pass_if(not invalid_slugs, MANIFEST.name, "slug format", "all slugs are root-level lowercase slugs", "invalid: " + "; ".join(invalid_slugs))
    reporter.pass_if(not invalid_briefs, MANIFEST.name, "brief filename alignment", "brief filenames match slugs", "mismatch: " + "; ".join(invalid_briefs))
    reporter.pass_if(not invalid_types, MANIFEST.name, "page types", "all page types valid", "invalid: " + "; ".join(invalid_types))
    reporter.pass_if(not invalid_priorities, MANIFEST.name, "priorities", "all priorities valid", "invalid: " + "; ".join(invalid_priorities))
    reporter.pass_if(not claim_priority_mismatch, MANIFEST.name, "claim-sensitive priority alignment", "claim-sensitive rows use P1-claim-sensitive", "mismatch: " + "; ".join(claim_priority_mismatch))
    reporter.pass_if(not missing_claim_flags, MANIFEST.name, "claim-sensitive slug coverage", "risk slugs are claim-sensitive", "missing yes: " + "; ".join(missing_claim_flags))


def check_internal_linking_map(reporter: Reporter, manifest_rows: list[RetrofitRow], link_rows: list[dict[str, str]]) -> None:
    manifest_by_slug = {row.slug: row for row in manifest_rows}
    required = {"priority", "source_url", "target_url", "anchor_text", "placement", "reason", "publish_dependency"}
    headers = set(link_rows[0]) if link_rows else set()
    missing_headers = sorted(required - headers)
    invalid_source_urls = sorted({row.get("source_url", "") for row in link_rows if not is_simplyrest_url(row.get("source_url", ""))})
    invalid_target_urls = sorted({row.get("target_url", "") for row in link_rows if row.get("target_url", "") not in EXPECTED_LINK_TARGETS})
    private_markers = sorted(
        {
            marker
            for row in link_rows
            for marker in FORBIDDEN_MARKERS
            if marker.lower() in "\t".join(row.values()).lower()
        }
    )
    empty_required = [
        str(index)
        for index, row in enumerate(link_rows, start=2)
        if any(not row.get(column, "").strip() for column in required)
    ]
    claim_mismatches = sorted(
        {
            slug_from_url(row.get("source_url", ""))
            for row in link_rows
            if row.get("priority") == "P1-claim-sensitive"
            and manifest_by_slug.get(slug_from_url(row.get("source_url", "")))
            and not manifest_by_slug[slug_from_url(row.get("source_url", ""))].claim_sensitive
        }
    )
    unique_sources = {slug_from_url(row.get("source_url", "")) for row in link_rows}
    sources_with_methodology = {
        slug_from_url(row.get("source_url", ""))
        for row in link_rows
        if row.get("target_url") == "https://simplyrest.com/how-we-test-mattresses/"
    }
    sources_missing_methodology = sorted(unique_sources - sources_with_methodology)
    link_priorities = set(row.get("priority", "") for row in link_rows)

    reporter.pass_if(not missing_headers, INTERNAL_LINKING_MAP.name, "columns", "required columns present", "missing: " + "; ".join(missing_headers))
    reporter.pass_if(len(link_rows) >= EXPECTED_MANIFEST_ROWS, INTERNAL_LINKING_MAP.name, "link row coverage", f"{len(link_rows)} rows", f"only {len(link_rows)} rows")
    reporter.pass_if(not invalid_source_urls, INTERNAL_LINKING_MAP.name, "source URLs", "all source URLs are simplyrest.com https URLs", "invalid: " + "; ".join(invalid_source_urls[:20]))
    reporter.pass_if(not invalid_target_urls, INTERNAL_LINKING_MAP.name, "target URLs", "all target URLs are approved rollout targets", "invalid: " + "; ".join(invalid_target_urls))
    reporter.pass_if(not private_markers, INTERNAL_LINKING_MAP.name, "private/risky markers", "none found", "found: " + "; ".join(private_markers))
    reporter.pass_if(not empty_required, INTERNAL_LINKING_MAP.name, "required cell values", "all required cells filled", "empty rows: " + "; ".join(empty_required[:30]))
    reporter.pass_if(not claim_mismatches, INTERNAL_LINKING_MAP.name, "claim-sensitive source alignment", "claim-sensitive link sources align with manifest", "mismatch: " + "; ".join(claim_mismatches))
    reporter.pass_if(not sources_missing_methodology, INTERNAL_LINKING_MAP.name, "methodology link per source", "every source has a How We Test route", "missing: " + "; ".join(sources_missing_methodology[:30]))
    reporter.pass_if("P0" in link_priorities, INTERNAL_LINKING_MAP.name, "P0 link priorities", "P0 rows present", "P0 rows missing")


def check_retrofit_scripts(reporter: Reporter) -> None:
    batch = RETROFIT_BATCH.read_text() if RETROFIT_BATCH.exists() else ""
    live_qa = RETROFIT_LIVE_QA.read_text() if RETROFIT_LIVE_QA.exists() else ""
    reporter.pass_if("SR-RETROFIT-MODULE-START" in batch, RETROFIT_BATCH.name, "retrofit module marker", "present", "missing")
    reporter.pass_if("Affiliate disclosure" in batch, RETROFIT_BATCH.name, "affiliate disclosure module", "present", "missing")
    reporter.pass_if("Health note" in batch and "not substitutes for medical care" in batch, RETROFIT_BATCH.name, "medical limitation module", "present", "missing")
    reporter.pass_if("https://simplyrest.com/mattress-lab/" in batch, RETROFIT_BATCH.name, "lab link", "present", "missing")
    reporter.pass_if("https://simplyrest.com/how-we-test-mattresses/" in batch, RETROFIT_BATCH.name, "methodology link", "present", "missing")
    reporter.pass_if("_simplyrest_retrofit_json_ld" in batch, RETROFIT_BATCH.name, "retrofit JSON-LD meta", "present", "missing")
    reporter.pass_if("_yoast_wpseo_canonical" not in batch and "wp_redirect" not in batch, RETROFIT_BATCH.name, "no canonical or redirect writes", "no canonical/redirect writes found", "canonical/redirect write marker found")
    reporter.pass_if("RISKY_CLAIM_PATTERNS" in live_qa, RETROFIT_LIVE_QA.name, "risky claim checks", "present", "missing")
    reporter.pass_if("REQUIRED_SCHEMA_TYPES" in live_qa and "FAQPage" in live_qa, RETROFIT_LIVE_QA.name, "schema checks", "present", "missing")
    reporter.pass_if("schema_entity_issues" in live_qa, RETROFIT_LIVE_QA.name, "schema entity issue output", "present", "missing")
    reporter.pass_if("schema_entity_issues(page, schemas, body)" in live_qa, RETROFIT_LIVE_QA.name, "schema entity validation", "present", "missing")
    reporter.pass_if("Article mainEntityOfPage mismatch" in live_qa, RETROFIT_LIVE_QA.name, "Article relationship validation", "present", "missing")
    reporter.pass_if("Breadcrumb terminal URL mismatch" in live_qa, RETROFIT_LIVE_QA.name, "Breadcrumb relationship validation", "present", "missing")
    reporter.pass_if("FAQ question missing from visible content" in live_qa, RETROFIT_LIVE_QA.name, "FAQ content alignment validation", "present", "missing")
    reporter.pass_if("Person jobTitle missing lead hands-on tester" in live_qa, RETROFIT_LIVE_QA.name, "Person entity validation", "present", "missing")
    reporter.pass_if("Organization url mismatch" in live_qa, RETROFIT_LIVE_QA.name, "Organization entity validation", "present", "missing")
    reporter.pass_if("not substitutes for medical care" in live_qa, RETROFIT_LIVE_QA.name, "medical limitation detection", "present", "missing")


def check_live_qa_report(reporter: Reporter, manifest_rows: list[RetrofitRow]) -> None:
    if not RETROFIT_LIVE_QA_REPORT.exists():
        reporter.add("fail", RETROFIT_LIVE_QA_REPORT.name, "report exists", "missing")
        return
    rows = read_tsv(RETROFIT_LIVE_QA_REPORT)
    headers = set(rows[0]) if rows else set()
    report_slugs = {row.get("slug", "") for row in rows}
    manifest_slugs = {row.slug for row in manifest_rows}
    reporter.pass_if(len(rows) == len(manifest_rows), RETROFIT_LIVE_QA_REPORT.name, "row count matches manifest", f"{len(rows)} rows", f"{len(rows)} rows vs {len(manifest_rows)} manifest rows")
    reporter.pass_if(report_slugs == manifest_slugs, RETROFIT_LIVE_QA_REPORT.name, "slug set matches manifest", "slug sets match", f"missing={sorted(manifest_slugs - report_slugs)}; extra={sorted(report_slugs - manifest_slugs)}")
    reporter.pass_if("schema_entity_issues" in headers, RETROFIT_LIVE_QA_REPORT.name, "schema entity issue column", "present", "missing")


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument(
        "--output",
        default=str(OUTPUTS / "simplyrest-retrofit-package-qa-report-2026-06-25.tsv"),
        help="TSV report output path",
    )
    args = parser.parse_args()

    reporter = Reporter()
    check_required_files(reporter)

    manifest_rows: list[RetrofitRow] = []
    if MANIFEST.exists():
        manifest_rows = parse_manifest(MANIFEST)
        check_manifest(reporter, manifest_rows)

    if INTERNAL_LINKING_MAP.exists() and manifest_rows:
        check_internal_linking_map(reporter, manifest_rows, read_tsv(INTERNAL_LINKING_MAP))

    check_retrofit_scripts(reporter)
    if manifest_rows:
        check_live_qa_report(reporter, manifest_rows)

    output = Path(args.output)
    reporter.write(output)
    if reporter.failures:
        print(f"FAIL: {len(reporter.failures)} retrofit package QA failure(s). Report: {output}", file=sys.stderr)
        return 1
    print(f"PASS: retrofit package QA passed. Report: {output}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
