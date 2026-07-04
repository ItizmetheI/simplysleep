#!/usr/bin/env python3
"""
Package QA for the Simply Rest phase-one launch bundle.

This checks local launch artifacts before a WordPress operator runs the
preflight/import/media steps. It does not touch production.
"""

from __future__ import annotations

import argparse
import csv
import json
import re
import sys
import zipfile
from dataclasses import dataclass
from pathlib import Path
from typing import Any


ROOT = Path(__file__).resolve().parent
OUTPUTS = ROOT

IMPORTER = OUTPUTS / "simplyrest_phase1_wp_cli_import.php"
LIVE_QA = OUTPUTS / "simplyrest_phase1_live_qa_checker.py"
LAUNCH_GATE = OUTPUTS / "simplyrest_phase1_launch_gate.php"
ROLLBACK = OUTPUTS / "simplyrest_phase1_wp_rollback.php"
AS3_REDIRECT_CLEANUP = OUTPUTS / "simplyrest_phase1_as3_redirect_cleanup.php"
DEPLOY_BUNDLE = OUTPUTS / "simplyrest-phase1-wordpress-deploy-bundle-2026-06-25.zip"
MEDIA_ZIP = OUTPUTS / "simplyrest-as3-optimized-media-2026-06-25.zip"
MEDIA_MANIFEST = OUTPUTS / "simplyrest-as3-optimized-media-upload-manifest-2026-06-25.tsv"
AS3_MEDIA_SECTION = OUTPUTS / "simplyrest-as3-media-section-replacement-2026-06-25.html"
LAB_MEDIA_ZIP = OUTPUTS / "simplyrest-lab-proof-media-2026-06-25.zip"
LAB_MEDIA_MANIFEST = OUTPUTS / "simplyrest-lab-proof-media-upload-manifest-2026-06-25.tsv"
SCORING_TSV = OUTPUTS / "simplyrest-mattress-scoring-matrix-2026-06-25.tsv"
SCORING_JSON = OUTPUTS / "simplyrest-mattress-scoring-matrix-2026-06-25.json"
OFFICIAL_SOURCE_ZIP = OUTPUTS / "simplyrest-official-source-content-pack-2026-06-25.zip"
OFFICIAL_SOURCE_SUMMARY_TSV = OUTPUTS / "simplyrest-official-source-content-pack-summary-2026-06-25.tsv"
OFFICIAL_SOURCE_SUMMARY_MD = OUTPUTS / "simplyrest-official-source-content-pack-summary-2026-06-25.md"
STATIC_PREVIEW_DIR = OUTPUTS / "simplyrest-phase1-static-preview-2026-06-25"
GOAL_AUDIT = OUTPUTS / "simplyrest_phase1_goal_completion_audit.py"
GOAL_AUDIT_REPORT = OUTPUTS / "simplyrest-phase1-goal-completion-audit-2026-06-25.tsv"
GOAL_AUDIT_MARKDOWN = OUTPUTS / "simplyrest-phase1-goal-completion-audit-2026-06-25.md"
LIVE_QA_REPORT = OUTPUTS / "simplyrest-phase1-live-qa-report-2026-06-25.tsv"
LOCAL_READINESS = OUTPUTS / "simplyrest_phase1_local_readiness.py"
LOCAL_READINESS_REPORT = OUTPUTS / "simplyrest-phase1-local-readiness-report-2026-06-25.tsv"
LOCAL_READINESS_MARKDOWN = OUTPUTS / "simplyrest-phase1-local-readiness-report-2026-06-25.md"


FORBIDDEN_PAGE_MARKERS = (
    "drive.google.com",
    "docs.google.com",
    "It seems we can",
    "/best-online-mattress/",
    "physical therapist",
    "licensed massage therapist",
    "sports performance specialist",
)

MEDICAL_LIMIT_MARKERS = (
    "not medical advice",
    "do not diagnose",
    "diagnosis",
    "treatment guidance",
    "medical review",
)

REQUIRED_DEPLOY_FILES = (
    "simplyrest_phase1_wp_cli_import.php",
    "simplyrest_phase1_wp_preflight.php",
    "simplyrest_phase1_wp_rollback.php",
    "simplyrest_phase1_jsonld_renderer_mu_plugin.php",
    "simplyrest_phase1_live_qa_checker.py",
    "simplyrest_phase1_launch_gate.php",
    "simplyrest_phase1_as3_redirect_cleanup.php",
    "simplyrest_phase1_as3_media_import.php",
    "simplyrest_phase1_lab_media_import.php",
    "simplyrest_phase1_retrofit_batch.php",
    "simplyrest_retrofit_live_qa_checker.py",
    "simplyrest_retrofit_package_qa.py",
    "simplyrest_phase1_package_qa.py",
    "simplyrest_phase1_goal_completion_audit.py",
    "simplyrest_phase1_local_readiness.py",
    "simplyrest-phase1-package-qa-instructions-2026-06-25.md",
    "simplyrest-phase1-wp-cli-deploy-instructions-2026-06-25.md",
    "simplyrest-phase1-wp-preflight-instructions-2026-06-25.md",
    "simplyrest-phase1-goal-completion-audit-2026-06-25.tsv",
    "simplyrest-phase1-goal-completion-audit-2026-06-25.md",
    "simplyrest-phase1-local-readiness-report-2026-06-25.tsv",
    "simplyrest-phase1-local-readiness-report-2026-06-25.md",
    "simplyrest-as3-optimized-media-2026-06-25.zip",
    "simplyrest-as3-optimized-media-upload-manifest-2026-06-25.tsv",
    "simplyrest-as3-media-section-replacement-2026-06-25.html",
    "simplyrest-lab-proof-media-2026-06-25.zip",
    "simplyrest-lab-proof-media-upload-manifest-2026-06-25.tsv",
    "simplyrest-mattress-scoring-matrix-2026-06-25.tsv",
    "simplyrest-mattress-scoring-matrix-2026-06-25.json",
    "simplyrest-official-source-content-pack-2026-06-25.zip",
    "simplyrest-official-source-content-pack-summary-2026-06-25.tsv",
    "simplyrest-official-source-content-pack-summary-2026-06-25.md",
    "simplyrest-retrofit-slug-manifest-2026-06-25.tsv",
    "simplyrest-122-url-retrofit-implementation-tracker-2026-06-25.tsv",
    "simplyrest-internal-linking-map-2026-06-25.tsv",
    "simplyrest-retrofit-and-linking-operator-brief-2026-06-25.md",
    "simplyrest-retrofit-gutenberg-block-library-2026-06-25.html",
    "simplyrest-retrofit-live-qa-instructions-2026-06-25.md",
    "simplyrest-retrofit-live-qa-report-2026-06-25.tsv",
    "simplyrest-retrofit-live-qa-baseline-2026-06-25.md",
    "simplyrest-retrofit-package-qa-instructions-2026-06-25.md",
    "simplyrest-retrofit-package-qa-report-2026-06-25.tsv",
    "simplyrest-phase1-static-preview-package-2026-06-25.zip",
)

EXPECTED_MEDIA_FILES = {
    "as3-photo-1.jpg",
    "as3-testing-clip-poster.jpg",
    "as3-testing-clip.mp4",
    "as3-edge-support-test-poster.jpg",
    "as3-edge-support-test.mp4",
    "as3-response-time-poster.jpg",
    "as3-response-time.mp4",
    "as3-hand-impression.jpg",
    "as3-side-sleeper.jpg",
    "as3-back-sleeper.jpg",
    "as3-stomach-sleeper.jpg",
    "as3-edge-support.jpg",
    "as3-photo-2.jpg",
}

EXPECTED_MEDIA_TOKENS = {
    "{{AS3_PHOTO_1_URL}}",
    "{{AS3_TESTING_CLIP_POSTER_URL}}",
    "{{AS3_TESTING_CLIP_URL}}",
    "{{AS3_EDGE_SUPPORT_TEST_POSTER_URL}}",
    "{{AS3_EDGE_SUPPORT_TEST_URL}}",
    "{{AS3_RESPONSE_TIME_POSTER_URL}}",
    "{{AS3_RESPONSE_TIME_URL}}",
    "{{AS3_HAND_IMPRESSION_URL}}",
    "{{AS3_SIDE_SLEEPER_URL}}",
    "{{AS3_BACK_SLEEPER_URL}}",
    "{{AS3_STOMACH_SLEEPER_URL}}",
    "{{AS3_EDGE_SUPPORT_PHOTO_URL}}",
    "{{AS3_PHOTO_2_URL}}",
}

EXPECTED_LAB_MEDIA_FILES = {
    "simplyrest-lab-testing-setup.jpg",
    "simplyrest-methodology-testing-collage.jpg",
    "ferdie-farhad-headshot.jpg",
    "ferdie-farhad-avatar.jpg",
    "ferdie-action-testing-candidate.jpg",
}

LAB_MEDIA_REQUIRED_STATUSES = {
    "simplyrest-lab-testing-setup.jpg": "deployable_rebrand_crop",
    "simplyrest-methodology-testing-collage.jpg": "deployable_rebrand_crop",
    "ferdie-farhad-headshot.jpg": "deployable_user_supplied_headshot",
    "ferdie-farhad-avatar.jpg": "deployable_user_supplied_headshot",
    "ferdie-action-testing-candidate.jpg": "candidate_requires_approval",
}

SCORING_FIELDNAMES = (
    "Model",
    "Overall",
    "Value",
    "Edge Support",
    "Trial Period",
    "Response Time",
    "Motion Transfer",
    "Cooling & Breathability",
)

SCORING_REQUIRED_MODELS = {
    "Amerisleep AS3": {
        "Overall": "10",
        "Value": "9",
        "Edge Support": "10",
        "Trial Period": "9",
        "Response Time": "9",
        "Motion Transfer": "10",
        "Cooling & Breathability": "10",
    },
    "Amerisleep AS3 Hybrid": {"Overall": "10"},
    "Zoma Boost": {"Overall": "10"},
    "Nolah Evolution 15\"": {"Overall": "10"},
    "Saatva Classic": {"Overall": "10"},
    "Amerisleep AS6 Black Series": {"Overall": "10"},
}

LAB_MEDIA_IMPORTER_REQUIRED_MARKERS = (
    "--include-candidate-ferdie",
    "candidate_requires_approval",
    "SKIP candidate media without --include-candidate-ferdie",
    "SR-LAB-PROOF-MEDIA-START",
    "_simplyrest_lab_proof_file",
    "_simplyrest_lab_proof_approval_status",
    "Disclosure and Review Limits",
    "Testing Disclosure and Limits",
    "Role Disclosure",
)

LAUNCH_GATE_REQUIRED_MARKERS = (
    "This script is read-only.",
    "--allow-drafts",
    "--skip-http",
    "--format=json",
    "https://simplyrest.com/mattress-lab/",
    "https://simplyrest.com/how-we-test-mattresses/",
    "https://simplyrest.com/ferdie-farhad/",
    "https://simplyrest.com/mattress-reviews/",
    "https://simplyrest.com/mattress-reviews/amerisleep-as3/",
    "simplyrest_gate_check_schema_renderer",
    "simplyrest_gate_check_rollback_helper_file",
    "simplyrest_gate_check_redirect_records",
    "simplyrest_gate_check_live_url",
    "_simplyrest_phase1_last_imported_at",
    "_simplyrest_phase1_pre_import_snapshot",
    "_simplyrest_phase1_created_by_import",
    "Page has a pre-import snapshot or importer-created marker.",
    "simplyrest_gate_check_schema_entity_relationships",
    "schema page node id",
    "schema Article author",
    "schema Breadcrumb terminal URL",
    "schema FAQ answer completeness",
    "schema ItemList AS3 URL",
    "schema Review itemReviewed",
    "schema Product brand",
    "exit(empty($failures) ? 0 : 1);",
)

LAUNCH_GATE_FORBIDDEN_WRITE_MARKERS = (
    "wp_insert_post",
    "wp_update_post",
    "update_post_meta",
    "delete_post_meta",
    "wp_delete_post",
    "media_handle_sideload",
    "set_post_thumbnail",
    "file_put_contents",
    "copy(",
)

IMPORTER_SNAPSHOT_REQUIRED_MARKERS = (
    "--skip-snapshot",
    "_simplyrest_phase1_pre_import_snapshot",
    "_simplyrest_phase1_pre_import_snapshot_created_at",
    "_simplyrest_phase1_created_by_import",
    "metadata_exists('post'",
    "Snapshot saved for /",
    "sr_phase1_mark_created_by_import",
)

ROLLBACK_REQUIRED_MARKERS = (
    "Defaults to dry-run.",
    "This helper does not delete pages",
    "--apply",
    "_simplyrest_phase1_pre_import_snapshot",
    "_simplyrest_phase1_created_by_import",
    "wp_update_post",
    "delete_post_meta",
    "DRY RUN restore",
    "DRY RUN move importer-created",
    "path mismatch",
)

ROLLBACK_FORBIDDEN_MARKERS = (
    "wp_delete_post",
    "DELETE FROM",
    "TRUNCATE",
    "DROP TABLE",
    "wp_trash_post",
    "delete_post(",
)

AS3_REDIRECT_CLEANUP_REQUIRED_MARKERS = (
    "Defaults to dry-run.",
    "--apply",
    "--format=json",
    "mattress-reviews/amerisleep-as3",
    "best-online-mattress",
    "simplyrest_as3_cleanup_redirection_plugin",
    "simplyrest_as3_cleanup_rankmath",
    "simplyrest_as3_cleanup_safe_redirect_manager",
    "simplyrest_as3_cleanup_options_audit",
    "simplyrest_as3_cleanup_text_targets_bad_redirect",
)

AS3_REDIRECT_CLEANUP_FORBIDDEN_MARKERS = (
    "wp_delete_post",
    "DELETE FROM",
    "TRUNCATE",
    "DROP TABLE",
    "UPDATE " + "$wpdb->posts",
)

LIVE_QA_REQUIRED_MARKERS = (
    "schema_entity_issues",
    "media_evidence_issues",
    "schema_entity_issues(req, schemas, body)",
    "media_evidence_issues(req, body)",
    "Review itemReviewed does not reference AS3 product",
    "Product brand is not Amerisleep",
    "FAQ question missing from visible content",
    "Breadcrumb terminal URL mismatch",
    "missing visible Ferdie image/media",
    "AS3 has fewer than 3 video blocks/assets",
)

GOAL_AUDIT_REQUIRED_MARKERS = (
    "This script is read-only.",
    "production_blocked",
    "package_ready",
    "needs_approval",
    "candidate_requires_approval",
    "CORE_PAGE_URLS",
    "RETROFIT_LIVE_QA_REPORT",
    "REQUIRED_BUNDLE_FILES",
    "local package evidence is incomplete or failing",
)

GOAL_AUDIT_REQUIRED_REQUIREMENT_IDS = {
    "0",
    "1",
    "1.1",
    "1.2",
    "1.3",
    "1.4",
    "1.5",
    "2",
    "2.1",
    "3",
    "4",
    "5",
    "6",
    "7",
}

GOAL_AUDIT_FIELDNAMES = (
    "requirement_id",
    "requirement",
    "status",
    "evidence",
    "blocker",
    "next_action",
    "evidence_files",
)

LOCAL_READINESS_REQUIRED_MARKERS = (
    "Local readiness runner",
    "phase_one_package_qa",
    "retrofit_package_qa",
    "phase_one_live_qa",
    "completion_audit",
    "deploy_bundle_integrity",
    "--skip-live",
    "--refresh-retrofit-live",
    "Production blockers",
    "status == \"blocker\"",
)

LOCAL_READINESS_FIELDNAMES = (
    "step",
    "status",
    "exit_code",
    "command",
    "detail",
)


@dataclass(frozen=True)
class PageRequirement:
    title: str
    path: str
    canonical: str
    required_schema_types: tuple[str, ...]
    required_markers: tuple[str, ...]
    required_links: tuple[str, ...]


PAGE_REQUIREMENTS = {
    "mattress-lab": PageRequirement(
        title="Simply Rest Lab",
        path="mattress-lab",
        canonical="https://simplyrest.com/mattress-lab/",
        required_schema_types=("WebPage", "Article", "FAQPage", "BreadcrumbList", "Person", "Organization"),
        required_markers=("Simply Rest Lab", "Firdous", "Ferdie", "lead hands-on tester", "first-hand"),
        required_links=(
            "https://simplyrest.com/how-we-test-mattresses/",
            "https://simplyrest.com/ferdie-farhad/",
            "https://simplyrest.com/mattress-reviews/",
        ),
    ),
    "how-we-test-mattresses": PageRequirement(
        title="How We Test Mattresses",
        path="how-we-test-mattresses",
        canonical="https://simplyrest.com/how-we-test-mattresses/",
        required_schema_types=("WebPage", "Article", "FAQPage", "BreadcrumbList", "Person", "Organization"),
        required_markers=("How We Test Mattresses", "pressure relief", "spinal alignment", "motion isolation", "edge support"),
        required_links=("https://simplyrest.com/how-we-test-mattresses/",),
    ),
    "ferdie-farhad": PageRequirement(
        title="Firdous Ferdie Farhad",
        path="ferdie-farhad",
        canonical="https://simplyrest.com/ferdie-farhad/",
        required_schema_types=("ProfilePage", "Person", "FAQPage", "BreadcrumbList", "Organization"),
        required_markers=("Firdous", "Ferdie", "lead hands-on tester", "author"),
        required_links=("https://simplyrest.com/how-we-test-mattresses/",),
    ),
    "mattress-reviews": PageRequirement(
        title="Mattress Reviews",
        path="mattress-reviews",
        canonical="https://simplyrest.com/mattress-reviews/",
        required_schema_types=("CollectionPage", "ItemList", "FAQPage", "BreadcrumbList", "Person", "Organization"),
        required_markers=("Mattress Reviews", "Simply Rest Lab", "Amerisleep AS3", "lead hands-on tester"),
        required_links=(
            "https://simplyrest.com/mattress-reviews/amerisleep-as3/",
            "https://simplyrest.com/how-we-test-mattresses/",
        ),
    ),
    "mattress-reviews/amerisleep-as3": PageRequirement(
        title="Amerisleep AS3 Mattress Review",
        path="mattress-reviews/amerisleep-as3",
        canonical="https://simplyrest.com/mattress-reviews/amerisleep-as3/",
        required_schema_types=("WebPage", "Review", "Product", "Article", "FAQPage", "BreadcrumbList", "Person", "Organization"),
        required_markers=("Amerisleep AS3", "Simply Rest Lab Score", "Testing Evidence", "Ferdie", "first-hand"),
        required_links=("https://simplyrest.com/how-we-test-mattresses/",),
    ),
}


class Reporter:
    def __init__(self) -> None:
        self.rows: list[dict[str, str]] = []

    def add(self, status: str, artifact: str, check: str, detail: str) -> None:
        self.rows.append(
            {
                "status": status,
                "artifact": artifact,
                "check": check,
                "detail": detail,
            }
        )

    def pass_if(self, condition: bool, artifact: str, check: str, pass_detail: str, fail_detail: str) -> None:
        self.add("pass" if condition else "fail", artifact, check, pass_detail if condition else fail_detail)

    def warn_if(self, condition: bool, artifact: str, check: str, pass_detail: str, warn_detail: str) -> None:
        self.add("pass" if condition else "warn", artifact, check, pass_detail if condition else warn_detail)

    @property
    def failures(self) -> list[dict[str, str]]:
        return [row for row in self.rows if row["status"] == "fail"]

    @property
    def warnings(self) -> list[dict[str, str]]:
        return [row for row in self.rows if row["status"] == "warn"]

    def write(self, output: Path) -> None:
        output.parent.mkdir(parents=True, exist_ok=True)
        with output.open("w", newline="") as f:
            writer = csv.DictWriter(f, fieldnames=("status", "artifact", "check", "detail"), delimiter="\t")
            writer.writeheader()
            writer.writerows(self.rows)


def present(text: str, marker: str) -> bool:
    return marker.lower() in text.lower()


def extract_pages(importer_text: str) -> list[dict[str, Any]]:
    match = re.search(r"json_decode\(<<<'JSON'\s*(.*?)\s*JSON,\s*true\);", importer_text, re.S)
    if not match:
        raise ValueError("embedded JSON payload not found")
    return json.loads(match.group(1))


def walk_schema_types(value: Any, found: set[str]) -> None:
    if isinstance(value, dict):
        schema_type = value.get("@type")
        if isinstance(schema_type, str):
            found.add(schema_type)
        elif isinstance(schema_type, list):
            found.update(str(item) for item in schema_type)
        for child in value.values():
            walk_schema_types(child, found)
    elif isinstance(value, list):
        for item in value:
            walk_schema_types(item, found)


def collect_schema_types(schema: Any) -> set[str]:
    found: set[str] = set()
    walk_schema_types(schema, found)
    return found


def schema_graph(schema: Any) -> list[dict[str, Any]]:
    if not isinstance(schema, dict):
        return []
    graph = schema.get("@graph")
    if not isinstance(graph, list):
        return []
    return [node for node in graph if isinstance(node, dict)]


def schema_type_list(node: dict[str, Any]) -> list[str]:
    schema_type = node.get("@type")
    if isinstance(schema_type, list):
        return [str(item) for item in schema_type]
    if isinstance(schema_type, str):
        return [schema_type]
    return []


def schema_nodes_by_type(graph: list[dict[str, Any]], schema_type: str) -> list[dict[str, Any]]:
    return [node for node in graph if schema_type in schema_type_list(node)]


def schema_ref_id(value: Any) -> str:
    if isinstance(value, dict):
        return str(value.get("@id", ""))
    return str(value or "")


def check_schema_entity_consistency(
    reporter: Reporter,
    artifact: str,
    page: dict[str, Any],
    requirement: PageRequirement,
    content: str,
) -> None:
    schema = page.get("schema")
    graph = schema_graph(schema)
    canonical = requirement.canonical
    webpage_id = canonical + "#webpage"
    person_id = "https://simplyrest.com/ferdie-farhad/#person"
    organization_id = "https://simplyrest.com/#organization"
    content_lower = content.lower()

    reporter.pass_if(isinstance(schema, dict), artifact, "schema object", "schema is an object", "schema is missing or not an object")
    if not isinstance(schema, dict):
        return
    reporter.pass_if(schema.get("@context") == "https://schema.org", artifact, "schema context", "schema.org context", f"got {schema.get('@context')}")
    reporter.pass_if(bool(graph), artifact, "schema graph", f"{len(graph)} graph nodes", "missing @graph nodes")
    if not graph:
        return

    duplicate_ids = sorted({node_id for node_id in [str(node.get("@id", "")) for node in graph if node.get("@id")] if [str(n.get("@id", "")) for n in graph if n.get("@id")].count(node_id) > 1})
    reporter.pass_if(not duplicate_ids, artifact, "schema duplicate ids", "none found", "duplicate @id values: " + "; ".join(duplicate_ids))

    page_node_type = "ProfilePage" if requirement.path == "ferdie-farhad" else "CollectionPage" if requirement.path == "mattress-reviews" else "WebPage"
    page_nodes = schema_nodes_by_type(graph, page_node_type)
    reporter.pass_if(bool(page_nodes), artifact, f"{page_node_type} node", "present", "missing")
    if page_nodes:
        page_node = page_nodes[0]
        reporter.pass_if(str(page_node.get("@id", "")) == webpage_id, artifact, "page node id", "matches canonical #webpage", f"got {page_node.get('@id')}")
        reporter.pass_if(str(page_node.get("url", "")) == canonical, artifact, "page node url", "matches canonical", f"got {page_node.get('url')}")

    person_nodes = schema_nodes_by_type(graph, "Person")
    reporter.pass_if(bool(person_nodes), artifact, "Person node", "present", "missing")
    if person_nodes:
        person = person_nodes[0]
        reporter.pass_if(str(person.get("@id", "")) == person_id, artifact, "Person id", "Ferdie person id", f"got {person.get('@id')}")
        reporter.pass_if("firdous" in str(person.get("name", "")).lower(), artifact, "Person name", "Firdous present", f"got {person.get('name')}")
        reporter.pass_if("ferdie" in str(person.get("alternateName", "")).lower(), artifact, "Person alternate name", "Ferdie present", f"got {person.get('alternateName')}")
        reporter.pass_if("lead hands-on tester" in str(person.get("jobTitle", "")).lower(), artifact, "Person job title", "lead hands-on tester present", f"got {person.get('jobTitle')}")

    organization_nodes = schema_nodes_by_type(graph, "Organization")
    reporter.pass_if(bool(organization_nodes), artifact, "Organization node", "present", "missing")
    if organization_nodes:
        organization = organization_nodes[0]
        reporter.pass_if(str(organization.get("@id", "")) == organization_id, artifact, "Organization id", "Simply Rest organization id", f"got {organization.get('@id')}")
        reporter.pass_if(str(organization.get("url", "")) == "https://simplyrest.com/", artifact, "Organization url", "Simply Rest home URL", f"got {organization.get('url')}")

    for article in schema_nodes_by_type(graph, "Article"):
        reporter.pass_if(schema_ref_id(article.get("author")) == person_id, artifact, "Article author", "references Ferdie", f"got {article.get('author')}")
        reporter.pass_if(schema_ref_id(article.get("publisher")) == organization_id, artifact, "Article publisher", "references Simply Rest", f"got {article.get('publisher')}")
        if "mainEntityOfPage" in article:
            reporter.pass_if(schema_ref_id(article.get("mainEntityOfPage")) == webpage_id, artifact, "Article mainEntityOfPage", "references page node", f"got {article.get('mainEntityOfPage')}")

    breadcrumb_nodes = schema_nodes_by_type(graph, "BreadcrumbList")
    reporter.pass_if(bool(breadcrumb_nodes), artifact, "BreadcrumbList node", "present", "missing")
    if breadcrumb_nodes:
        crumbs = breadcrumb_nodes[0].get("itemListElement")
        crumb_items = crumbs if isinstance(crumbs, list) else []
        reporter.pass_if(bool(crumb_items), artifact, "Breadcrumb items", f"{len(crumb_items)} items", "missing")
        if crumb_items:
            reporter.pass_if(str(crumb_items[-1].get("item", "")) == canonical, artifact, "Breadcrumb terminal URL", "matches canonical", f"got {crumb_items[-1].get('item')}")

    faq_nodes = schema_nodes_by_type(graph, "FAQPage")
    reporter.pass_if(bool(faq_nodes), artifact, "FAQPage node", "present", "missing")
    if faq_nodes:
        questions = faq_nodes[0].get("mainEntity")
        question_items = questions if isinstance(questions, list) else []
        reporter.pass_if(len(question_items) >= 3, artifact, "FAQ question count", f"{len(question_items)} questions", f"{len(question_items)} questions")
        bad_questions: list[str] = []
        missing_content_questions: list[str] = []
        for question in question_items:
            if not isinstance(question, dict):
                bad_questions.append("<non-object>")
                continue
            name = str(question.get("name", "")).strip()
            answer = question.get("acceptedAnswer")
            answer_text = str(answer.get("text", "")).strip() if isinstance(answer, dict) else ""
            if not name or not answer_text:
                bad_questions.append(name or "<missing name>")
            if name and name.lower() not in content_lower:
                missing_content_questions.append(name)
        reporter.pass_if(not bad_questions, artifact, "FAQ answer completeness", "all questions have answers", "incomplete: " + "; ".join(bad_questions))
        reporter.pass_if(not missing_content_questions, artifact, "FAQ content alignment", "FAQ questions appear in page content", "missing in content: " + "; ".join(missing_content_questions))

    if requirement.path == "mattress-reviews":
        item_lists = schema_nodes_by_type(graph, "ItemList")
        reporter.pass_if(bool(item_lists), artifact, "ItemList node", "present", "missing")
        if item_lists:
            item_text = json.dumps(item_lists[0])
            reporter.pass_if(
                "https://simplyrest.com/mattress-reviews/amerisleep-as3/" in item_text,
                artifact,
                "ItemList AS3 URL",
                "links AS3 review",
                "AS3 review URL missing",
            )

    if requirement.path == "mattress-reviews/amerisleep-as3":
        product_id = canonical + "#product"
        review_id = canonical + "#review"
        review_nodes = schema_nodes_by_type(graph, "Review")
        product_nodes = schema_nodes_by_type(graph, "Product")
        reporter.pass_if(bool(review_nodes), artifact, "Review node", "present", "missing")
        reporter.pass_if(bool(product_nodes), artifact, "Product node", "present", "missing")
        if review_nodes:
            review = review_nodes[0]
            rating = review.get("reviewRating")
            rating_value = None
            best_rating = None
            worst_rating = None
            if isinstance(rating, dict):
                try:
                    rating_value = float(str(rating.get("ratingValue", "")))
                    best_rating = float(str(rating.get("bestRating", "")))
                    worst_rating = float(str(rating.get("worstRating", "")))
                except ValueError:
                    pass
            reporter.pass_if(str(review.get("@id", "")) == review_id, artifact, "Review id", "matches canonical #review", f"got {review.get('@id')}")
            reporter.pass_if(schema_ref_id(review.get("itemReviewed")) == product_id, artifact, "Review itemReviewed", "references AS3 product", f"got {review.get('itemReviewed')}")
            reporter.pass_if(rating_value is not None and 0 <= rating_value <= 10, artifact, "Review rating value", f"{rating_value:g} within 0-10", f"got {rating}")
            reporter.pass_if(best_rating == 10 and (worst_rating is None or worst_rating <= best_rating), artifact, "Review rating bounds", "bestRating is 10", f"got {rating}")
        if product_nodes:
            product = product_nodes[0]
            brand = product.get("brand")
            brand_name = str(brand.get("name", "")) if isinstance(brand, dict) else str(brand or "")
            reporter.pass_if(str(product.get("@id", "")) == product_id, artifact, "Product id", "matches canonical #product", f"got {product.get('@id')}")
            reporter.pass_if(brand_name == "Amerisleep", artifact, "Product brand", "Amerisleep", f"got {brand}")
        for article in schema_nodes_by_type(graph, "Article"):
            if "about" in article:
                reporter.pass_if(schema_ref_id(article.get("about")) == product_id, artifact, "Article about", "references AS3 product", f"got {article.get('about')}")


def check_required_files(reporter: Reporter) -> None:
    required_paths = (
        IMPORTER,
        OUTPUTS / "simplyrest_phase1_wp_preflight.php",
        ROLLBACK,
        OUTPUTS / "simplyrest_phase1_jsonld_renderer_mu_plugin.php",
        OUTPUTS / "simplyrest_phase1_live_qa_checker.py",
        OUTPUTS / "simplyrest_phase1_launch_gate.php",
        OUTPUTS / "simplyrest_phase1_as3_redirect_cleanup.php",
        OUTPUTS / "simplyrest_phase1_as3_media_import.php",
        OUTPUTS / "simplyrest_phase1_lab_media_import.php",
        OUTPUTS / "simplyrest_phase1_retrofit_batch.php",
        OUTPUTS / "simplyrest_retrofit_live_qa_checker.py",
        OUTPUTS / "simplyrest_retrofit_package_qa.py",
        Path(__file__).resolve(),
        GOAL_AUDIT,
        LOCAL_READINESS,
        GOAL_AUDIT_REPORT,
        GOAL_AUDIT_MARKDOWN,
        LOCAL_READINESS_REPORT,
        LOCAL_READINESS_MARKDOWN,
        OUTPUTS / "simplyrest-phase1-package-qa-instructions-2026-06-25.md",
        OUTPUTS / "simplyrest-retrofit-package-qa-instructions-2026-06-25.md",
        DEPLOY_BUNDLE,
        MEDIA_ZIP,
        MEDIA_MANIFEST,
        AS3_MEDIA_SECTION,
        LAB_MEDIA_ZIP,
        LAB_MEDIA_MANIFEST,
        SCORING_TSV,
        SCORING_JSON,
        OFFICIAL_SOURCE_ZIP,
        OFFICIAL_SOURCE_SUMMARY_TSV,
        OFFICIAL_SOURCE_SUMMARY_MD,
        OUTPUTS / "simplyrest-retrofit-slug-manifest-2026-06-25.tsv",
        OUTPUTS / "simplyrest-122-url-retrofit-implementation-tracker-2026-06-25.tsv",
        OUTPUTS / "simplyrest-internal-linking-map-2026-06-25.tsv",
        OUTPUTS / "simplyrest-retrofit-and-linking-operator-brief-2026-06-25.md",
        OUTPUTS / "simplyrest-retrofit-gutenberg-block-library-2026-06-25.html",
        OUTPUTS / "simplyrest-retrofit-live-qa-instructions-2026-06-25.md",
        OUTPUTS / "simplyrest-retrofit-live-qa-report-2026-06-25.tsv",
        OUTPUTS / "simplyrest-retrofit-live-qa-baseline-2026-06-25.md",
        OUTPUTS / "simplyrest-retrofit-package-qa-report-2026-06-25.tsv",
        OUTPUTS / "simplyrest-phase1-static-preview-package-2026-06-25.zip",
    )
    for path in required_paths:
        reporter.pass_if(path.exists(), path.name, "file exists", "found", "missing")


def check_deploy_bundle(reporter: Reporter) -> None:
    if not DEPLOY_BUNDLE.exists():
        reporter.add("fail", DEPLOY_BUNDLE.name, "zip readable", "deploy bundle is missing")
        return
    try:
        with zipfile.ZipFile(DEPLOY_BUNDLE) as archive:
            bad_file = archive.testzip()
            names = set(archive.namelist())
    except zipfile.BadZipFile as exc:
        reporter.add("fail", DEPLOY_BUNDLE.name, "zip readable", str(exc))
        return

    reporter.pass_if(bad_file is None, DEPLOY_BUNDLE.name, "zip integrity", "all files readable", f"bad member: {bad_file}")
    missing = sorted(set(REQUIRED_DEPLOY_FILES) - names)
    reporter.pass_if(not missing, DEPLOY_BUNDLE.name, "required bundle files", "all required files present", "missing: " + "; ".join(missing))


def check_importer_pages(reporter: Reporter) -> None:
    if not IMPORTER.exists():
        reporter.add("fail", IMPORTER.name, "embedded page payload", "importer is missing")
        return

    importer_text = IMPORTER.read_text()
    missing_snapshot_markers = [marker for marker in IMPORTER_SNAPSHOT_REQUIRED_MARKERS if marker not in importer_text]
    reporter.pass_if(not missing_snapshot_markers, IMPORTER.name, "snapshot controls", "pre-import snapshot controls present", "missing: " + "; ".join(missing_snapshot_markers))

    try:
        pages = extract_pages(importer_text)
    except (OSError, ValueError, json.JSONDecodeError) as exc:
        reporter.add("fail", IMPORTER.name, "embedded page payload", str(exc))
        return

    reporter.pass_if(len(pages) == 5, IMPORTER.name, "priority page count", "5 pages embedded", f"{len(pages)} pages embedded")
    page_by_path = {str(page.get("path", "")): page for page in pages}
    missing_paths = sorted(set(PAGE_REQUIREMENTS) - set(page_by_path))
    extra_paths = sorted(set(page_by_path) - set(PAGE_REQUIREMENTS))
    reporter.pass_if(not missing_paths and not extra_paths, IMPORTER.name, "priority page paths", "paths match launch set", f"missing={missing_paths}; extra={extra_paths}")

    for path, requirement in PAGE_REQUIREMENTS.items():
        page = page_by_path.get(path)
        if not page:
            continue
        artifact = f"importer:{path}"
        content = str(page.get("content", ""))
        schema = page.get("schema")
        schema_types = collect_schema_types(schema)
        missing_schema = [schema_type for schema_type in requirement.required_schema_types if schema_type not in schema_types]
        missing_markers = [marker for marker in requirement.required_markers if not present(content, marker)]
        missing_links = [link for link in requirement.required_links if link not in content]
        forbidden = [marker for marker in FORBIDDEN_PAGE_MARKERS if present(content, marker)]
        has_affiliate = present(content, "affiliate disclosure") or present(content, "may earn a commission")
        has_medical_limit = any(present(content, marker) for marker in MEDICAL_LIMIT_MARKERS)

        reporter.pass_if(str(page.get("canonical", "")) == requirement.canonical, artifact, "canonical", "matches target URL", f"got {page.get('canonical')}")
        reporter.pass_if(str(page.get("meta_title", "")).strip() != "", artifact, "meta title", "present", "missing")
        reporter.pass_if(str(page.get("meta_description", "")).strip() != "", artifact, "meta description", "present", "missing")
        reporter.warn_if(len(str(page.get("meta_description", ""))) <= 165, artifact, "meta description length", "165 chars or fewer", f"{len(str(page.get('meta_description', '')))} chars")
        reporter.pass_if("Ferdie" in str(page.get("author_display", "")) or "Firdous" in str(page.get("author_display", "")), artifact, "author display", "Ferdie/Firdous present", f"got {page.get('author_display')}")
        reporter.pass_if("Ferdie" in str(page.get("lead_tester", "")) or "Firdous" in str(page.get("lead_tester", "")), artifact, "lead tester meta", "Ferdie/Firdous present", f"got {page.get('lead_tester')}")
        reporter.pass_if(not missing_schema, artifact, "schema type coverage", "types present: " + "; ".join(sorted(schema_types)), "missing: " + "; ".join(missing_schema))
        check_schema_entity_consistency(reporter, artifact, page, requirement, content)
        reporter.pass_if(not missing_markers, artifact, "content markers", "required markers present", "missing: " + "; ".join(missing_markers))
        reporter.pass_if(not missing_links, artifact, "required internal links", "required links present", "missing: " + "; ".join(missing_links))
        reporter.pass_if(has_affiliate, artifact, "affiliate disclosure", "present", "missing")
        reporter.pass_if(has_medical_limit, artifact, "medical limitation", "present", "missing")
        reporter.pass_if(not forbidden, artifact, "forbidden public markers", "none found", "found: " + "; ".join(forbidden))


def check_as3_media(reporter: Reporter) -> None:
    if not MEDIA_MANIFEST.exists():
        reporter.add("fail", MEDIA_MANIFEST.name, "manifest readable", "missing")
        return
    with MEDIA_MANIFEST.open(newline="") as f:
        rows = list(csv.DictReader(f, delimiter="\t"))

    manifest_files = {row.get("optimized_file", "") for row in rows}
    manifest_tokens = {row.get("wp_block_token", "") for row in rows}
    reporter.pass_if(len(rows) == 13, MEDIA_MANIFEST.name, "media manifest rows", "13 rows", f"{len(rows)} rows")
    reporter.pass_if(manifest_files == EXPECTED_MEDIA_FILES, MEDIA_MANIFEST.name, "media file set", "matches expected AS3 assets", f"missing={sorted(EXPECTED_MEDIA_FILES - manifest_files)}; extra={sorted(manifest_files - EXPECTED_MEDIA_FILES)}")
    reporter.pass_if(manifest_tokens == EXPECTED_MEDIA_TOKENS, MEDIA_MANIFEST.name, "media token set", "matches expected tokens", f"missing={sorted(EXPECTED_MEDIA_TOKENS - manifest_tokens)}; extra={sorted(manifest_tokens - EXPECTED_MEDIA_TOKENS)}")

    for index, row in enumerate(rows, start=2):
        artifact = f"{MEDIA_MANIFEST.name}:row{index}"
        required_fields = ("optimized_file", "media_type", "wp_media_title", "alt_text", "caption", "target_section", "wp_block_token")
        missing_fields = [field for field in required_fields if not str(row.get(field, "")).strip()]
        reporter.pass_if(not missing_fields, artifact, "required metadata", "present", "missing: " + "; ".join(missing_fields))
        if row.get("media_type") == "video":
            reporter.pass_if(str(row.get("duration_seconds", "")).strip() != "", artifact, "video duration", "present", "missing")

    if not MEDIA_ZIP.exists():
        reporter.add("fail", MEDIA_ZIP.name, "zip readable", "missing")
    else:
        try:
            with zipfile.ZipFile(MEDIA_ZIP) as archive:
                bad_file = archive.testzip()
                zip_files = set(archive.namelist())
        except zipfile.BadZipFile as exc:
            reporter.add("fail", MEDIA_ZIP.name, "zip readable", str(exc))
        else:
            reporter.pass_if(bad_file is None, MEDIA_ZIP.name, "zip integrity", "all files readable", f"bad member: {bad_file}")
            reporter.pass_if(zip_files == EXPECTED_MEDIA_FILES, MEDIA_ZIP.name, "zip file set", "matches manifest assets", f"missing={sorted(EXPECTED_MEDIA_FILES - zip_files)}; extra={sorted(zip_files - EXPECTED_MEDIA_FILES)}")

    if not AS3_MEDIA_SECTION.exists():
        reporter.add("fail", AS3_MEDIA_SECTION.name, "section readable", "missing")
        return
    section = AS3_MEDIA_SECTION.read_text()
    missing_section_tokens = [token for token in sorted(EXPECTED_MEDIA_TOKENS) if token not in section]
    forbidden = [marker for marker in FORBIDDEN_PAGE_MARKERS if present(section, marker)]
    reporter.pass_if(not missing_section_tokens, AS3_MEDIA_SECTION.name, "section tokens", "all expected tokens present", "missing: " + "; ".join(missing_section_tokens))
    reporter.pass_if(section.lower().count("<video") >= 3, AS3_MEDIA_SECTION.name, "video blocks", "3 or more video blocks", "fewer than 3 video blocks")
    reporter.pass_if(not forbidden, AS3_MEDIA_SECTION.name, "forbidden public markers", "none found", "found: " + "; ".join(forbidden))


def check_lab_media(reporter: Reporter) -> None:
    importer = OUTPUTS / "simplyrest_phase1_lab_media_import.php"
    if not importer.exists():
        reporter.add("fail", importer.name, "lab media importer readable", "missing")
    else:
        text = importer.read_text()
        missing_markers = [marker for marker in LAB_MEDIA_IMPORTER_REQUIRED_MARKERS if marker not in text]
        reporter.pass_if(not missing_markers, importer.name, "required importer markers", "all required controls present", "missing: " + "; ".join(missing_markers))
        reporter.pass_if("wp_update_post" in text and "--force-update-published" in text, importer.name, "published-page guard", "update path and force flag present", "missing update guard")
        reporter.pass_if("media_handle_sideload" in text and "_wp_attachment_image_alt" in text, importer.name, "media upload metadata", "upload and alt metadata present", "missing upload/alt metadata")

    if not LAB_MEDIA_MANIFEST.exists():
        reporter.add("fail", LAB_MEDIA_MANIFEST.name, "manifest readable", "missing")
        return

    with LAB_MEDIA_MANIFEST.open(newline="") as f:
        rows = list(csv.DictReader(f, delimiter="\t"))

    files = {row.get("optimized_file", "") for row in rows}
    reporter.pass_if(len(rows) == 5, LAB_MEDIA_MANIFEST.name, "lab media manifest rows", "5 rows", f"{len(rows)} rows")
    reporter.pass_if(files == EXPECTED_LAB_MEDIA_FILES, LAB_MEDIA_MANIFEST.name, "lab media file set", "matches expected lab proof assets", f"missing={sorted(EXPECTED_LAB_MEDIA_FILES - files)}; extra={sorted(files - EXPECTED_LAB_MEDIA_FILES)}")

    for index, row in enumerate(rows, start=2):
        artifact = f"{LAB_MEDIA_MANIFEST.name}:row{index}"
        required_fields = ("optimized_file", "approval_status", "media_type", "dimensions", "size_kb", "wp_media_title", "target_page_path", "alt_text", "caption", "notes")
        missing_fields = [field for field in required_fields if not str(row.get(field, "")).strip()]
        reporter.pass_if(not missing_fields, artifact, "required metadata", "present", "missing: " + "; ".join(missing_fields))
        expected_status = LAB_MEDIA_REQUIRED_STATUSES.get(row.get("optimized_file", ""))
        reporter.pass_if(row.get("approval_status") == expected_status, artifact, "approval status", f"{expected_status}", f"got {row.get('approval_status')}")
        reporter.pass_if(row.get("media_type") == "image", artifact, "media type", "image", f"got {row.get('media_type')}")
        if row.get("approval_status") == "candidate_requires_approval":
            reporter.pass_if("approval" in row.get("notes", "").lower(), artifact, "candidate approval note", "approval note present", "candidate row lacks approval note")

    if not LAB_MEDIA_ZIP.exists():
        reporter.add("fail", LAB_MEDIA_ZIP.name, "zip readable", "missing")
        return

    try:
        with zipfile.ZipFile(LAB_MEDIA_ZIP) as archive:
            bad_file = archive.testzip()
            zip_files = set(archive.namelist())
    except zipfile.BadZipFile as exc:
        reporter.add("fail", LAB_MEDIA_ZIP.name, "zip readable", str(exc))
        return

    reporter.pass_if(bad_file is None, LAB_MEDIA_ZIP.name, "zip integrity", "all files readable", f"bad member: {bad_file}")
    reporter.pass_if(zip_files == EXPECTED_LAB_MEDIA_FILES, LAB_MEDIA_ZIP.name, "zip file set", "matches manifest assets", f"missing={sorted(EXPECTED_LAB_MEDIA_FILES - zip_files)}; extra={sorted(zip_files - EXPECTED_LAB_MEDIA_FILES)}")


def check_scoring_matrix(reporter: Reporter) -> None:
    if not SCORING_TSV.exists():
        reporter.add("fail", SCORING_TSV.name, "scoring TSV readable", "missing")
        return

    with SCORING_TSV.open(newline="") as f:
        reader = csv.DictReader(f, delimiter="\t")
        rows = list(reader)
        fieldnames = tuple(reader.fieldnames or ())

    reporter.pass_if(fieldnames == SCORING_FIELDNAMES, SCORING_TSV.name, "fieldnames", "scoring fields match source matrix", f"got {fieldnames}")
    reporter.pass_if(len(rows) == 23, SCORING_TSV.name, "row count", "23 mattress score rows", f"{len(rows)} rows")
    row_by_model = {row.get("Model", ""): row for row in rows}
    missing_models = sorted(set(SCORING_REQUIRED_MODELS) - set(row_by_model))
    reporter.pass_if(not missing_models, SCORING_TSV.name, "required models", "required scoring models present", "missing: " + "; ".join(missing_models))
    for model, expected_scores in SCORING_REQUIRED_MODELS.items():
        row = row_by_model.get(model, {})
        for field, expected in expected_scores.items():
            reporter.pass_if(
                row.get(field) == expected,
                f"{SCORING_TSV.name}:{model}",
                f"{field} score",
                expected,
                f"got {row.get(field)}",
            )
    for index, row in enumerate(rows, start=2):
        artifact = f"{SCORING_TSV.name}:row{index}"
        for field in SCORING_FIELDNAMES[1:]:
            value = row.get(field, "")
            reporter.pass_if(value.isdigit() and 1 <= int(value) <= 10, artifact, f"{field} valid", "1-10 score", f"got {value}")

    if not SCORING_JSON.exists():
        reporter.add("fail", SCORING_JSON.name, "scoring JSON readable", "missing")
    else:
        try:
            json_rows = json.loads(SCORING_JSON.read_text())
        except json.JSONDecodeError as exc:
            reporter.add("fail", SCORING_JSON.name, "json parse", str(exc))
        else:
            reporter.pass_if(isinstance(json_rows, list) and len(json_rows) == len(rows), SCORING_JSON.name, "json parity", "JSON row count matches TSV", "JSON row count mismatch")

    if IMPORTER.exists():
        importer_text = IMPORTER.read_text()
        reporter.pass_if("Current Simply Rest Lab Score Database" in importer_text, IMPORTER.name, "score database table", "Lab page includes score database", "missing score database table")
        reporter.pass_if("Simply Rest Lab Score:</strong> 10/10" in importer_text, IMPORTER.name, "AS3 visible score", "AS3 page shows 10/10", "AS3 visible score is not 10/10")
        reporter.pass_if('"ratingValue": "10"' in importer_text, IMPORTER.name, "AS3 review rating", "Review schema ratingValue is 10", "AS3 Review schema ratingValue is not 10")


def check_official_source_pack(reporter: Reporter) -> None:
    if not OFFICIAL_SOURCE_ZIP.exists():
        reporter.add("fail", OFFICIAL_SOURCE_ZIP.name, "official source zip readable", "missing")
    else:
        try:
            with zipfile.ZipFile(OFFICIAL_SOURCE_ZIP) as archive:
                bad_file = archive.testzip()
                names = set(archive.namelist())
        except zipfile.BadZipFile as exc:
            reporter.add("fail", OFFICIAL_SOURCE_ZIP.name, "zip readable", str(exc))
        else:
            required_members = {
                "simplyrest-official-source-content-pack/CONTENT_GENERATION_STATUS.csv",
                "simplyrest-official-source-content-pack/official_source_evidence/OFFICIAL_PRODUCT_FACT_DATABASE.csv",
                "simplyrest-official-source-content-pack/official_source_evidence/OFFICIAL_SOURCE_REGISTER.csv",
                "simplyrest-official-source-content-pack/official_source_evidence/FINAL_CMS_QA_REMAINING_ITEMS.csv",
                "simplyrest-official-source-content-pack/wordpress_content_ready_for_qa/reviews/amerisleep-as3/amerisleep-as3.md",
            }
            missing = sorted(required_members - names)
            reporter.pass_if(bad_file is None, OFFICIAL_SOURCE_ZIP.name, "zip integrity", "all files readable", f"bad member: {bad_file}")
            reporter.pass_if(not missing, OFFICIAL_SOURCE_ZIP.name, "required source files", "official source evidence files present", "missing: " + "; ".join(missing))

    if not OFFICIAL_SOURCE_SUMMARY_TSV.exists():
        reporter.add("fail", OFFICIAL_SOURCE_SUMMARY_TSV.name, "summary TSV readable", "missing")
    else:
        with OFFICIAL_SOURCE_SUMMARY_TSV.open(newline="") as f:
            rows = list(csv.DictReader(f, delimiter="\t"))
        metric_map = {row.get("metric", ""): row.get("value", "") for row in rows}
        reporter.pass_if(metric_map.get("content_status_rows") == "46", OFFICIAL_SOURCE_SUMMARY_TSV.name, "content row count", "46 rows", f"got {metric_map.get('content_status_rows')}")
        reporter.pass_if(metric_map.get("official_product_fact_rows") == "42", OFFICIAL_SOURCE_SUMMARY_TSV.name, "fact row count", "42 rows", f"got {metric_map.get('official_product_fact_rows')}")
        reporter.pass_if(metric_map.get("remaining_cms_qa_rows") == "32", OFFICIAL_SOURCE_SUMMARY_TSV.name, "remaining QA row count", "32 rows", f"got {metric_map.get('remaining_cms_qa_rows')}")
        reporter.pass_if(metric_map.get("placeholder_rows_after_update") == "0", OFFICIAL_SOURCE_SUMMARY_TSV.name, "placeholder scan", "0 placeholder rows after official-source update", f"got {metric_map.get('placeholder_rows_after_update')}")

    if not OFFICIAL_SOURCE_SUMMARY_MD.exists():
        reporter.add("fail", OFFICIAL_SOURCE_SUMMARY_MD.name, "summary markdown readable", "missing")
    else:
        text = OFFICIAL_SOURCE_SUMMARY_MD.read_text()
        required_markers = (
            "Official-source content package copied",
            "Replace affiliate-link placeholders",
            "Bear Star Hybrid",
            "GhostBed Flex",
            "Dreamfoam Essential",
            "fiberglass/fire-barrier claims",
        )
        missing = [marker for marker in required_markers if marker not in text]
        reporter.pass_if(not missing, OFFICIAL_SOURCE_SUMMARY_MD.name, "publish gate markers", "required source-pack gates present", "missing: " + "; ".join(missing))


def check_static_preview(reporter: Reporter) -> None:
    if not STATIC_PREVIEW_DIR.exists():
        reporter.add("warn", STATIC_PREVIEW_DIR.name, "static preview directory", "missing; regenerate before visual QA")
        return
    html_files = sorted(STATIC_PREVIEW_DIR.glob("*.html"))
    reporter.pass_if(len(html_files) >= 5, STATIC_PREVIEW_DIR.name, "html page count", f"{len(html_files)} html files", f"{len(html_files)} html files")
    for html_file in html_files:
        text = html_file.read_text()
        artifact = f"static:{html_file.name}"
        reporter.pass_if("mattress-reviews.htmlamerisleep-as3/" not in text, artifact, "localized AS3 link", "no malformed AS3 local link", "malformed AS3 local link found")
        reporter.pass_if("drive.google.com" not in text and "docs.google.com" not in text, artifact, "private links", "none found", "private link marker found")


def check_launch_gate(reporter: Reporter) -> None:
    if not LAUNCH_GATE.exists():
        reporter.add("fail", LAUNCH_GATE.name, "launch gate readable", "missing")
        return

    text = LAUNCH_GATE.read_text()
    missing_markers = [marker for marker in LAUNCH_GATE_REQUIRED_MARKERS if marker not in text]
    forbidden_markers = [marker for marker in LAUNCH_GATE_FORBIDDEN_WRITE_MARKERS if marker in text]
    reporter.pass_if(not missing_markers, LAUNCH_GATE.name, "required gate markers", "all required controls present", "missing: " + "; ".join(missing_markers))
    reporter.pass_if(not forbidden_markers, LAUNCH_GATE.name, "read-only write markers", "no write APIs found", "found: " + "; ".join(forbidden_markers))
    reporter.pass_if(text.count("simplyrest_gate_pass_if(") >= 20, LAUNCH_GATE.name, "gate check count", "20 or more pass/fail checks", "fewer than 20 pass/fail checks")
    reporter.pass_if("best-online-mattress" in text and "amerisleep-as3" in text, LAUNCH_GATE.name, "AS3 redirect guard", "AS3 redirect guard present", "missing AS3 redirect guard")


def check_rollback_helper(reporter: Reporter) -> None:
    if not ROLLBACK.exists():
        reporter.add("fail", ROLLBACK.name, "rollback helper readable", "missing")
        return

    text = ROLLBACK.read_text()
    missing_markers = [marker for marker in ROLLBACK_REQUIRED_MARKERS if marker not in text]
    forbidden_markers = [marker for marker in ROLLBACK_FORBIDDEN_MARKERS if marker in text]
    reporter.pass_if(not missing_markers, ROLLBACK.name, "required rollback markers", "all required rollback controls present", "missing: " + "; ".join(missing_markers))
    reporter.pass_if(not forbidden_markers, ROLLBACK.name, "page deletion markers", "no page delete/trash markers found", "found: " + "; ".join(forbidden_markers))
    reporter.pass_if("$apply = in_array('--apply', $args, true);" in text, ROLLBACK.name, "explicit apply flag", "apply flag required", "missing explicit apply flag")
    reporter.pass_if("if (!$apply)" in text and "DRY RUN" in text, ROLLBACK.name, "dry-run default behavior", "dry-run and apply paths present", "missing dry-run/apply split")
    for target_path in PAGE_REQUIREMENTS:
        reporter.pass_if("'" + target_path + "'" in text, ROLLBACK.name, f"rollback target {target_path}", "target path present", "target path missing")


def check_as3_redirect_cleanup(reporter: Reporter) -> None:
    if not AS3_REDIRECT_CLEANUP.exists():
        reporter.add("fail", AS3_REDIRECT_CLEANUP.name, "redirect cleanup readable", "missing")
        return

    text = AS3_REDIRECT_CLEANUP.read_text()
    missing_markers = [marker for marker in AS3_REDIRECT_CLEANUP_REQUIRED_MARKERS if marker not in text]
    forbidden_markers = [marker for marker in AS3_REDIRECT_CLEANUP_FORBIDDEN_MARKERS if marker in text]
    reporter.pass_if(not missing_markers, AS3_REDIRECT_CLEANUP.name, "required cleanup markers", "all required controls present", "missing: " + "; ".join(missing_markers))
    reporter.pass_if(not forbidden_markers, AS3_REDIRECT_CLEANUP.name, "destructive markers", "no destructive SQL/post deletes found", "found: " + "; ".join(forbidden_markers))
    reporter.pass_if("$apply = in_array('--apply', $args, true);" in text, AS3_REDIRECT_CLEANUP.name, "explicit apply flag", "apply flag required", "missing explicit apply flag")
    reporter.pass_if("if ($apply)" in text and "would_disable" in text, AS3_REDIRECT_CLEANUP.name, "dry-run default behavior", "dry-run and apply paths present", "missing dry-run/apply split")
    reporter.pass_if("strpos($text, 'amerisleep-as3') !== false && strpos($text, 'best-online-mattress') !== false" in text, AS3_REDIRECT_CLEANUP.name, "narrow AS3 target check", "requires AS3 and best-online markers", "missing narrow bad-redirect predicate")


def check_live_qa_checker(reporter: Reporter) -> None:
    if not LIVE_QA.exists():
        reporter.add("fail", LIVE_QA.name, "live QA readable", "missing")
        return

    text = LIVE_QA.read_text()
    missing_markers = [marker for marker in LIVE_QA_REQUIRED_MARKERS if marker not in text]
    reporter.pass_if(not missing_markers, LIVE_QA.name, "entity/media validation markers", "all required live QA markers present", "missing: " + "; ".join(missing_markers))
    reporter.pass_if("curl_fetch" in text and "overall_pass" in text, LIVE_QA.name, "live fetch and pass gate", "curl fetch and overall pass gate present", "missing live fetch/pass gate")
    reporter.pass_if("FORBIDDEN_PUBLIC_MARKERS" in text and "drive.google.com" in text, LIVE_QA.name, "private-link guard", "private-link guard present", "missing private-link guard")


def check_goal_audit(reporter: Reporter) -> None:
    if not GOAL_AUDIT.exists():
        reporter.add("fail", GOAL_AUDIT.name, "goal audit script readable", "missing")
        return

    text = GOAL_AUDIT.read_text()
    missing_markers = [marker for marker in GOAL_AUDIT_REQUIRED_MARKERS if marker not in text]
    reporter.pass_if(not missing_markers, GOAL_AUDIT.name, "required audit markers", "all required controls present", "missing: " + "; ".join(missing_markers))

    if not GOAL_AUDIT_REPORT.exists():
        reporter.add("fail", GOAL_AUDIT_REPORT.name, "goal audit report readable", "missing")
        return

    with GOAL_AUDIT_REPORT.open(newline="") as f:
        reader = csv.DictReader(f, delimiter="\t")
        report_rows = list(reader)
        fieldnames = tuple(reader.fieldnames or ())

    reporter.pass_if(fieldnames == GOAL_AUDIT_FIELDNAMES, GOAL_AUDIT_REPORT.name, "report fields", "fields match expected audit schema", f"got {fieldnames}")
    row_by_id = {row.get("requirement_id", ""): row for row in report_rows}
    missing_ids = sorted(GOAL_AUDIT_REQUIRED_REQUIREMENT_IDS - set(row_by_id))
    reporter.pass_if(not missing_ids, GOAL_AUDIT_REPORT.name, "requirement id coverage", "all launch requirements covered", "missing: " + "; ".join(missing_ids))
    reporter.pass_if(
        all(row.get("evidence_files", "").strip() for row in report_rows),
        GOAL_AUDIT_REPORT.name,
        "evidence files populated",
        "each audit row cites evidence files",
        "one or more rows have blank evidence files",
    )
    live_passes = None
    if LIVE_QA_REPORT.exists():
        with LIVE_QA_REPORT.open(newline="") as f:
            live_rows = list(csv.DictReader(f, delimiter="\t"))
        live_passes = sum(1 for row in live_rows if row.get("overall_pass", "").lower() == "yes")
        if live_rows and live_passes < 5:
            reporter.pass_if(
                any(row.get("status") == "production_blocked" for row in report_rows),
                GOAL_AUDIT_REPORT.name,
                "production completion guard",
                "report preserves production-blocked state when live QA is failing",
                "no production_blocked rows found",
            )
            reporter.pass_if(
                row_by_id.get("1", {}).get("status") == "production_blocked",
                GOAL_AUDIT_REPORT.name,
                "core proof page live blocker",
                "core proof page requirement remains production_blocked",
                f"got {row_by_id.get('1', {}).get('status')}",
            )
            reporter.pass_if(
                row_by_id.get("7", {}).get("status") == "production_blocked",
                GOAL_AUDIT_REPORT.name,
                "launch QA live blocker",
                "launch QA requirement remains production_blocked",
                f"got {row_by_id.get('7', {}).get('status')}",
            )

    if LAB_MEDIA_MANIFEST.exists():
        with LAB_MEDIA_MANIFEST.open(newline="") as f:
            lab_rows = list(csv.DictReader(f, delimiter="\t"))
        has_candidate = any(row.get("approval_status") == "candidate_requires_approval" for row in lab_rows)
        if has_candidate:
            reporter.pass_if(
                row_by_id.get("2.1", {}).get("status") == "needs_approval",
                GOAL_AUDIT_REPORT.name,
                "Ferdie candidate approval gate",
                "candidate media remains approval-gated",
                f"got {row_by_id.get('2.1', {}).get('status')}",
            )

    if not GOAL_AUDIT_MARKDOWN.exists():
        reporter.add("fail", GOAL_AUDIT_MARKDOWN.name, "goal audit markdown readable", "missing")
        return

    markdown = GOAL_AUDIT_MARKDOWN.read_text()
    has_production_blockers = any(row.get("status") == "production_blocked" for row in report_rows)
    reporter.pass_if(
        not has_production_blockers or "Production is not launch-ready yet." in markdown,
        GOAL_AUDIT_MARKDOWN.name,
        "markdown no-go summary",
        "no-go summary present when blockers exist",
        "missing no-go summary while production_blocked rows exist",
    )
    reporter.pass_if("## Blocking Requirements" in markdown and "## Next Actions" in markdown, GOAL_AUDIT_MARKDOWN.name, "markdown sections", "blocking and next-action sections present", "missing required sections")


def check_local_readiness(reporter: Reporter, require_report: bool = True) -> None:
    if not LOCAL_READINESS.exists():
        reporter.add("fail", LOCAL_READINESS.name, "local readiness script readable", "missing")
        return

    text = LOCAL_READINESS.read_text()
    missing_markers = [marker for marker in LOCAL_READINESS_REQUIRED_MARKERS if marker not in text]
    reporter.pass_if(not missing_markers, LOCAL_READINESS.name, "required readiness markers", "all required controls present", "missing: " + "; ".join(missing_markers))
    reporter.pass_if("shell=True" not in text, LOCAL_READINESS.name, "subprocess shell safety", "does not use shell=True", "shell=True found")
    reporter.pass_if("allow_nonzero=True" in text, LOCAL_READINESS.name, "live blocker handling", "live QA nonzero results become blockers", "missing allow_nonzero live blocker handling")

    if not require_report:
        reporter.add("pass", LOCAL_READINESS.name, "local readiness report requirement", "skipped because --skip-local-readiness-report was passed")
        return

    if not LOCAL_READINESS_REPORT.exists():
        reporter.add("fail", LOCAL_READINESS_REPORT.name, "local readiness report readable", "missing")
        return

    with LOCAL_READINESS_REPORT.open(newline="") as f:
        reader = csv.DictReader(f, delimiter="\t")
        rows = list(reader)
        fieldnames = tuple(reader.fieldnames or ())

    reporter.pass_if(fieldnames == LOCAL_READINESS_FIELDNAMES, LOCAL_READINESS_REPORT.name, "report fields", "fields match expected readiness schema", f"got {fieldnames}")
    step_names = {row.get("step", "") for row in rows}
    for required_step in ("phase_one_package_qa", "retrofit_package_qa", "completion_audit", "deploy_bundle_integrity", "completion_audit_summary"):
        reporter.pass_if(required_step in step_names, LOCAL_READINESS_REPORT.name, f"readiness step {required_step}", "present", "missing")
    if any(row.get("step") == "phase_one_live_qa" for row in rows):
        reporter.pass_if(
            any(row.get("status") == "blocker" for row in rows),
            LOCAL_READINESS_REPORT.name,
            "production blocker rows",
            "blocker rows present when live QA is no-go",
            "no blocker rows present",
        )

    if not LOCAL_READINESS_MARKDOWN.exists():
        reporter.add("fail", LOCAL_READINESS_MARKDOWN.name, "local readiness markdown readable", "missing")
        return

    markdown = LOCAL_READINESS_MARKDOWN.read_text()
    reporter.pass_if("Go/no-go:" in markdown and "## Blocking Rows" in markdown, LOCAL_READINESS_MARKDOWN.name, "markdown sections", "go/no-go and blocking sections present", "missing required sections")


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument(
        "--output",
        default=str(OUTPUTS / "simplyrest-phase1-package-qa-report-2026-06-25.tsv"),
        help="TSV report output path",
    )
    parser.add_argument(
        "--skip-local-readiness-report",
        action="store_true",
        help="Validate the readiness runner script but do not require an existing readiness report. Used only by the readiness runner to avoid circular bootstrapping.",
    )
    args = parser.parse_args()

    reporter = Reporter()
    check_required_files(reporter)
    check_deploy_bundle(reporter)
    check_importer_pages(reporter)
    check_live_qa_checker(reporter)
    check_goal_audit(reporter)
    check_local_readiness(reporter, require_report=not args.skip_local_readiness_report)
    check_launch_gate(reporter)
    check_rollback_helper(reporter)
    check_as3_redirect_cleanup(reporter)
    check_as3_media(reporter)
    check_lab_media(reporter)
    check_scoring_matrix(reporter)
    check_official_source_pack(reporter)
    check_static_preview(reporter)
    reporter.write(Path(args.output))

    if reporter.failures:
        print(f"FAIL: {len(reporter.failures)} hard package QA failure(s). Report: {args.output}", file=sys.stderr)
        return 1
    if reporter.warnings:
        print(f"PASS with {len(reporter.warnings)} warning(s). Report: {args.output}")
    else:
        print(f"PASS: package QA passed. Report: {args.output}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
