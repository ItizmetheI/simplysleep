#!/usr/bin/env python3
"""
Live QA checker for the Simply Rest 122-URL retrofit rollout.

Run after the phase-one proof pages are live and after retrofit batches are
applied. It verifies URL/canonical behavior, Ferdie attribution, methodology
links, disclosure language, retrofit schema coverage, and private-link leaks.
"""

from __future__ import annotations

import argparse
import csv
import html
import json
import re
import subprocess
import sys
from dataclasses import dataclass
from pathlib import Path
from typing import Any


BASE_URL = "https://simplyrest.com"

REQUIRED_SCHEMA_TYPES = ("Article", "FAQPage", "BreadcrumbList", "Person", "Organization")

FORBIDDEN_PUBLIC_MARKERS = (
    "drive.google.com",
    "docs.google.com",
    "It seems we can",
    "physical therapist",
    "licensed massage therapist",
    "sports performance specialist",
)

RISKY_CLAIM_PATTERNS = (
    r"\bfix(?:es|ed|ing)?\s+(?:back\s+)?pain\b",
    r"\bcure(?:s|d)?\b",
    r"\btreat(?:s|ed|ing)?\s+(?:pain|sleep disorders?|medical conditions?)\b",
    r"\bguarantee(?:s|d)?\s+relief\b",
    r"\bcorrect(?:s|ed|ing)?\s+spinal alignment\b",
    r"\bmedical[- ]grade support\b",
)

COMMERCIAL_PAGE_TYPES = {"best-of", "comparison", "sales", "local"}


@dataclass(frozen=True)
class RetrofitPage:
    slug: str
    page_type: str
    priority: str
    claim_sensitive: bool

    @property
    def expected_url(self) -> str:
        if self.slug == "home":
            return f"{BASE_URL}/"
        return f"{BASE_URL}/{self.slug}/"

    @property
    def requires_affiliate_disclosure(self) -> bool:
        return self.page_type in COMMERCIAL_PAGE_TYPES or "mattress" in self.slug

    @property
    def requires_medical_limit(self) -> bool:
        return self.claim_sensitive


def curl_fetch(url: str, timeout: int) -> tuple[int, str, str]:
    cmd = [
        "curl",
        "-L",
        "--max-time",
        str(timeout),
        "-sS",
        "-w",
        "\\n__SIMPLYREST_STATUS__:%{http_code}\\n__SIMPLYREST_FINAL_URL__:%{url_effective}\\n",
        url,
    ]
    proc = subprocess.run(cmd, text=True, capture_output=True, check=False)
    combined = proc.stdout
    if proc.returncode != 0 and not combined:
        return 0, "", proc.stderr.strip()

    status_match = re.search(r"\n__SIMPLYREST_STATUS__:(\d+)\n", combined)
    final_match = re.search(r"\n__SIMPLYREST_FINAL_URL__:(.+)\n?$", combined)
    status = int(status_match.group(1)) if status_match else 0
    final_url = final_match.group(1).strip() if final_match else ""
    body = re.sub(r"\n__SIMPLYREST_STATUS__:\d+\n__SIMPLYREST_FINAL_URL__:.+\n?$", "", combined, flags=re.S)
    return status, final_url, body


def normalize_url(url: str) -> str:
    return url.rstrip("/") + "/" if url else ""


def present(body: str, marker: str) -> bool:
    return marker.lower() in body.lower()


def yes_no(value: bool) -> str:
    return "yes" if value else "no"


def extract_canonical(body: str) -> str:
    match = re.search(
        r"<link[^>]+rel=[\"']canonical[\"'][^>]+href=[\"']([^\"']+)[\"']|"
        r"<link[^>]+href=[\"']([^\"']+)[\"'][^>]+rel=[\"']canonical[\"']",
        body,
        flags=re.I,
    )
    if not match:
        return ""
    return html.unescape(next(group for group in match.groups() if group))


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


def extract_schemas(body: str) -> tuple[list[Any], int, int]:
    scripts = re.findall(
        r"<script[^>]+type=[\"']application/ld\+json[\"'][^>]*>(.*?)</script>",
        body,
        flags=re.I | re.S,
    )
    schemas: list[Any] = []
    parse_errors = 0
    for raw in scripts:
        text = html.unescape(raw.strip())
        if not text:
            continue
        try:
            schemas.append(json.loads(text))
        except json.JSONDecodeError:
            parse_errors += 1
    return schemas, len(scripts), parse_errors


def collect_schema_types(schemas: list[Any]) -> set[str]:
    found: set[str] = set()
    for schema in schemas:
        walk_schema_types(schema, found)
    return found


def schema_type_list(node: dict[str, Any]) -> list[str]:
    schema_type = node.get("@type")
    if isinstance(schema_type, list):
        return [str(item) for item in schema_type]
    if isinstance(schema_type, str):
        return [schema_type]
    return []


def append_schema_nodes(value: Any, nodes: list[dict[str, Any]]) -> None:
    if isinstance(value, dict):
        graph = value.get("@graph")
        if isinstance(graph, list):
            for graph_node in graph:
                append_schema_nodes(graph_node, nodes)
        elif "@type" in value or "@id" in value:
            nodes.append(value)
        else:
            for child in value.values():
                append_schema_nodes(child, nodes)
    elif isinstance(value, list):
        for item in value:
            append_schema_nodes(item, nodes)


def schema_nodes(schemas: list[Any]) -> list[dict[str, Any]]:
    nodes: list[dict[str, Any]] = []
    for schema in schemas:
        append_schema_nodes(schema, nodes)
    return nodes


def schema_nodes_by_type(nodes: list[dict[str, Any]], schema_type: str) -> list[dict[str, Any]]:
    return [node for node in nodes if schema_type in schema_type_list(node)]


def schema_ref_id(value: Any) -> str:
    if isinstance(value, dict):
        return str(value.get("@id", ""))
    return str(value or "")


def schema_node_url(node: dict[str, Any]) -> str:
    url = node.get("url")
    if isinstance(url, dict):
        return schema_ref_id(url)
    return str(url or "")


def schema_node_id(url: str, fragment: str) -> str:
    return normalize_url(url).rstrip("/") + f"/#{fragment.lstrip('#')}"


def schema_entity_issues(page: RetrofitPage, schemas: list[Any], body: str) -> list[str]:
    issues: list[str] = []
    nodes = schema_nodes(schemas)
    expected_url = page.expected_url
    article_id = schema_node_id(expected_url, "article")
    webpage_id = schema_node_id(expected_url, "webpage")
    person_id = "https://simplyrest.com/ferdie-farhad/#person"
    organization_id = "https://simplyrest.com/#organization"
    body_lower = body.lower()

    if not schemas:
        return ["no parseable JSON-LD schema"]
    if not nodes:
        return ["no schema graph nodes found"]

    context_found = any(isinstance(schema, dict) and schema.get("@context") == "https://schema.org" for schema in schemas)
    if not context_found:
        issues.append("missing schema.org context")

    ids = [str(node.get("@id", "")) for node in nodes if node.get("@id")]
    duplicate_ids = sorted({node_id for node_id in ids if ids.count(node_id) > 1})
    if duplicate_ids:
        issues.append("duplicate schema @id values: " + ", ".join(duplicate_ids))

    article_nodes = schema_nodes_by_type(nodes, "Article")
    if not article_nodes:
        issues.append("missing Article node")
    else:
        for article in article_nodes:
            if str(article.get("@id", "")) and str(article.get("@id", "")) != article_id:
                issues.append("Article @id mismatch")
            if schema_ref_id(article.get("author")) != person_id:
                issues.append("Article author does not reference Ferdie")
            if schema_ref_id(article.get("publisher")) != organization_id:
                issues.append("Article publisher does not reference Simply Rest")
            if schema_ref_id(article.get("mainEntityOfPage")) != webpage_id:
                issues.append("Article mainEntityOfPage mismatch")

    person_nodes = schema_nodes_by_type(nodes, "Person")
    if not person_nodes:
        issues.append("missing Person node")
    else:
        person = person_nodes[0]
        if str(person.get("@id", "")) != person_id:
            issues.append("Person @id mismatch")
        if "firdous" not in str(person.get("name", "")).lower():
            issues.append("Person name missing Firdous")
        if "ferdie" not in str(person.get("alternateName", "")).lower():
            issues.append("Person alternateName missing Ferdie")
        if "lead hands-on tester" not in str(person.get("jobTitle", "")).lower():
            issues.append("Person jobTitle missing lead hands-on tester")

    organization_nodes = schema_nodes_by_type(nodes, "Organization")
    if not organization_nodes:
        issues.append("missing Organization node")
    else:
        organization = organization_nodes[0]
        if str(organization.get("@id", "")) != organization_id:
            issues.append("Organization @id mismatch")
        if normalize_url(schema_node_url(organization)) != "https://simplyrest.com/":
            issues.append("Organization url mismatch")

    breadcrumb_nodes = schema_nodes_by_type(nodes, "BreadcrumbList")
    if not breadcrumb_nodes:
        issues.append("missing BreadcrumbList node")
    else:
        crumbs = breadcrumb_nodes[0].get("itemListElement")
        crumb_items = crumbs if isinstance(crumbs, list) else []
        if not crumb_items:
            issues.append("BreadcrumbList has no items")
        else:
            terminal = str(crumb_items[-1].get("item", "")) if isinstance(crumb_items[-1], dict) else ""
            if normalize_url(terminal) != normalize_url(expected_url):
                issues.append("Breadcrumb terminal URL mismatch")

    faq_nodes = schema_nodes_by_type(nodes, "FAQPage")
    if not faq_nodes:
        issues.append("missing FAQPage node")
    else:
        questions = faq_nodes[0].get("mainEntity")
        question_items = questions if isinstance(questions, list) else []
        if len(question_items) < 3:
            issues.append(f"FAQPage has fewer than 3 questions: {len(question_items)}")
        for question in question_items:
            if not isinstance(question, dict):
                issues.append("FAQPage contains non-object question")
                continue
            name = str(question.get("name", "")).strip()
            accepted_answer = question.get("acceptedAnswer")
            answer_text = str(accepted_answer.get("text", "")).strip() if isinstance(accepted_answer, dict) else ""
            if not name or not answer_text:
                issues.append("FAQ question missing name or acceptedAnswer text")
            if name and name.lower() not in body_lower:
                issues.append("FAQ question missing from visible content: " + name)

    return sorted(set(issues))


def load_manifest(path: Path) -> list[RetrofitPage]:
    rows: list[RetrofitPage] = []
    with path.open(newline="") as f:
        reader = csv.DictReader(f, delimiter="\t")
        required = {"slug", "page_type", "priority", "claim_sensitive"}
        missing = required.difference(reader.fieldnames or ())
        if missing:
            raise SystemExit(f"Manifest missing columns: {', '.join(sorted(missing))}")
        for row in reader:
            rows.append(
                RetrofitPage(
                    slug=row["slug"],
                    page_type=row["page_type"],
                    priority=row["priority"],
                    claim_sensitive=row["claim_sensitive"].strip().lower() == "yes",
                )
            )
    return rows


def filter_pages(pages: list[RetrofitPage], priorities: str, limit: int, offset: int) -> list[RetrofitPage]:
    selected = pages
    if priorities:
        allowed = {priority.strip() for priority in priorities.split(",") if priority.strip()}
        selected = [page for page in selected if page.priority in allowed]
    if offset:
        selected = selected[offset:]
    if limit:
        selected = selected[:limit]
    return selected


def find_risky_claims(body: str) -> list[str]:
    text = re.sub(r"\s+", " ", body)
    risky: list[str] = []
    for pattern in RISKY_CLAIM_PATTERNS:
        if re.search(pattern, text, flags=re.I):
            risky.append(pattern)
    return risky


def evaluate_page(page: RetrofitPage, timeout: int) -> dict[str, str]:
    status, final_url, body = curl_fetch(page.expected_url, timeout)
    canonical = extract_canonical(body)
    schemas, schema_script_count, schema_parse_errors = extract_schemas(body)
    schema_types = collect_schema_types(schemas)
    missing_schema = [schema_type for schema_type in REQUIRED_SCHEMA_TYPES if schema_type not in schema_types]
    entity_issues = schema_entity_issues(page, schemas, body) if schema_parse_errors == 0 else ["schema parse errors prevent entity validation"]

    status_ok = 200 <= status < 300
    final_url_ok = normalize_url(final_url) == normalize_url(page.expected_url)
    canonical_ok = normalize_url(canonical) == normalize_url(page.expected_url)

    ferdie_found = present(body, "Firdous") or present(body, "Ferdie")
    lead_tester_found = present(body, "lead hands-on tester") or present(body, "lead tester")
    lab_link_found = f"{BASE_URL}/mattress-lab/" in body or "/mattress-lab/" in body
    methodology_link_found = f"{BASE_URL}/how-we-test-mattresses/" in body or "/how-we-test-mattresses/" in body
    faq_found = present(body, "FAQ") or "FAQPage" in schema_types
    affiliate_found = present(body, "affiliate disclosure") or present(body, "may earn a commission")
    medical_found = (
        present(body, "not medical advice")
        or present(body, "medical limitation")
        or present(body, "do not diagnose")
        or present(body, "not substitutes for medical care")
    )
    forbidden = [marker for marker in FORBIDDEN_PUBLIC_MARKERS if present(body, marker)]
    risky_claims = find_risky_claims(body)

    schema_ok = not missing_schema and not entity_issues and schema_script_count > 0 and schema_parse_errors == 0
    attribution_ok = ferdie_found and lead_tester_found
    links_ok = lab_link_found and methodology_link_found
    disclosure_ok = (not page.requires_affiliate_disclosure or affiliate_found) and (
        not page.requires_medical_limit or medical_found
    )
    safety_ok = not forbidden and not risky_claims
    content_ok = attribution_ok and links_ok and faq_found and disclosure_ok and safety_ok
    overall = status_ok and final_url_ok and canonical_ok and schema_ok and content_ok

    return {
        "slug": page.slug,
        "priority": page.priority,
        "page_type": page.page_type,
        "claim_sensitive": yes_no(page.claim_sensitive),
        "url": page.expected_url,
        "status": str(status),
        "final_url": final_url,
        "status_ok": yes_no(status_ok),
        "final_url_ok": yes_no(final_url_ok),
        "canonical": canonical,
        "canonical_ok": yes_no(canonical_ok),
        "schema_script_count": str(schema_script_count),
        "schema_parse_errors": str(schema_parse_errors),
        "schema_types_found": "; ".join(sorted(schema_types)),
        "missing_schema_types": "; ".join(missing_schema),
        "schema_entity_issues": "; ".join(entity_issues),
        "ferdie_found": yes_no(ferdie_found),
        "lead_tester_found": yes_no(lead_tester_found),
        "lab_link_found": yes_no(lab_link_found),
        "methodology_link_found": yes_no(methodology_link_found),
        "faq_found": yes_no(faq_found),
        "affiliate_disclosure_required": yes_no(page.requires_affiliate_disclosure),
        "affiliate_disclosure_found": yes_no(affiliate_found),
        "medical_limit_required": yes_no(page.requires_medical_limit),
        "medical_limit_found": yes_no(medical_found),
        "forbidden_public_markers": "; ".join(forbidden),
        "risky_claim_patterns_found": "; ".join(risky_claims),
        "overall_pass": yes_no(overall),
    }


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument(
        "--manifest",
        default="outputs/simplyrest-retrofit-slug-manifest-2026-06-25.tsv",
        help="Retrofit slug manifest TSV",
    )
    parser.add_argument(
        "--output",
        default="outputs/simplyrest-retrofit-live-qa-report-2026-06-25.tsv",
        help="TSV report output path",
    )
    parser.add_argument("--priority", default="", help="Comma-separated priority filter, e.g. P0,P1")
    parser.add_argument("--limit", type=int, default=0)
    parser.add_argument("--offset", type=int, default=0)
    parser.add_argument("--timeout", type=int, default=20)
    args = parser.parse_args()

    manifest = Path(args.manifest)
    pages = filter_pages(load_manifest(manifest), args.priority, args.limit, args.offset)
    if not pages:
        raise SystemExit("No retrofit pages matched the requested filters.")

    rows = [evaluate_page(page, args.timeout) for page in pages]
    output = Path(args.output)
    output.parent.mkdir(parents=True, exist_ok=True)
    with output.open("w", newline="") as f:
        writer = csv.DictWriter(f, fieldnames=list(rows[0].keys()), delimiter="\t")
        writer.writeheader()
        writer.writerows(rows)

    failures = [row for row in rows if row["overall_pass"] != "yes"]
    print(f"Wrote {output}")
    print(f"Pages checked: {len(rows)}")
    print(f"Failures: {len(failures)}")
    for row in failures[:20]:
        print(f"- {row['priority']} {row['slug']}: status={row['status']} final={row['final_url']} pass={row['overall_pass']}")
    if len(failures) > 20:
        print(f"... {len(failures) - 20} more failures in report")
    return 1 if failures else 0


if __name__ == "__main__":
    sys.exit(main())
