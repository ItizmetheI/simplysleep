#!/usr/bin/env python3
"""
Live QA checker for the Simply Rest phase-one proof pages.

Run after WordPress draft import, media upload, schema placement, and publish.
It writes a TSV with URL status, redirect/canonical checks, disclosure checks,
schema type coverage, schema entity consistency, media proof checks, Drive-link
leaks, and key content markers.
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


@dataclass(frozen=True)
class PageRequirement:
    name: str
    url: str
    expected_final_url: str
    required_schema_types: tuple[str, ...]
    required_markers: tuple[str, ...]
    required_links: tuple[str, ...]
    requires_affiliate_disclosure: bool = True
    requires_medical_limit: bool = True
    requires_visible_media: bool = False
    requires_as3_media: bool = False


PAGES = (
    PageRequirement(
        name="Mattress Lab",
        url="https://simplyrest.com/mattress-lab/",
        expected_final_url="https://simplyrest.com/mattress-lab/",
        required_schema_types=("WebPage", "Article", "FAQPage", "BreadcrumbList", "Person", "Organization"),
        required_markers=("Simply Rest Lab", "Firdous", "Ferdie", "lead hands-on tester"),
        required_links=(
            "https://simplyrest.com/how-we-test-mattresses/",
            "https://simplyrest.com/ferdie-farhad/",
            "https://simplyrest.com/mattress-reviews/",
        ),
    ),
    PageRequirement(
        name="How We Test Mattresses",
        url="https://simplyrest.com/how-we-test-mattresses/",
        expected_final_url="https://simplyrest.com/how-we-test-mattresses/",
        required_schema_types=("WebPage", "Article", "FAQPage", "BreadcrumbList", "Person", "Organization"),
        required_markers=("How We Test Mattresses", "pressure relief", "spinal alignment", "motion isolation", "edge support"),
        required_links=("https://simplyrest.com/how-we-test-mattresses/",),
    ),
    PageRequirement(
        name="Ferdie Farhad",
        url="https://simplyrest.com/ferdie-farhad/",
        expected_final_url="https://simplyrest.com/ferdie-farhad/",
        required_schema_types=("ProfilePage", "Person", "FAQPage", "BreadcrumbList", "Organization"),
        required_markers=("Firdous", "Ferdie", "lead hands-on tester", "author"),
        required_links=("https://simplyrest.com/how-we-test-mattresses/",),
        requires_visible_media=True,
    ),
    PageRequirement(
        name="Mattress Reviews Hub",
        url="https://simplyrest.com/mattress-reviews/",
        expected_final_url="https://simplyrest.com/mattress-reviews/",
        required_schema_types=("CollectionPage", "ItemList", "FAQPage", "BreadcrumbList", "Person", "Organization"),
        required_markers=("Mattress Reviews", "Simply Rest Lab", "Amerisleep AS3"),
        required_links=(
            "https://simplyrest.com/mattress-reviews/amerisleep-as3/",
            "https://simplyrest.com/how-we-test-mattresses/",
        ),
    ),
    PageRequirement(
        name="Amerisleep AS3 Review",
        url="https://simplyrest.com/mattress-reviews/amerisleep-as3/",
        expected_final_url="https://simplyrest.com/mattress-reviews/amerisleep-as3/",
        required_schema_types=("WebPage", "Review", "Product", "Article", "FAQPage", "BreadcrumbList", "Person", "Organization"),
        required_markers=("Amerisleep AS3", "Simply Rest Lab Score", "Testing Evidence", "Ferdie"),
        required_links=("https://simplyrest.com/how-we-test-mattresses/",),
        requires_as3_media=True,
    ),
)


DEFAULT_BASE_URL = "https://simplyrest.com"


def rebase_pages(pages: tuple[PageRequirement, ...], base_url: str) -> tuple[PageRequirement, ...]:
    base_url = base_url.rstrip("/")

    def rebase(url: str) -> str:
        return url.replace(DEFAULT_BASE_URL, base_url)

    return tuple(
        PageRequirement(
            name=req.name,
            url=rebase(req.url),
            expected_final_url=rebase(req.expected_final_url),
            required_schema_types=req.required_schema_types,
            required_markers=req.required_markers,
            required_links=tuple(rebase(link) for link in req.required_links),
            requires_affiliate_disclosure=req.requires_affiliate_disclosure,
            requires_medical_limit=req.requires_medical_limit,
            requires_visible_media=req.requires_visible_media,
            requires_as3_media=req.requires_as3_media,
        )
        for req in pages
    )


FORBIDDEN_PUBLIC_MARKERS = (
    "drive.google.com",
    "docs.google.com",
    "It seems we can",
    "/best-online-mattress/",
    "physical therapist",
    "licensed massage therapist",
    "sports performance specialist",
)


def curl_fetch(url: str, timeout: int) -> tuple[int, str, str]:
    """Return status code, final URL, body using curl for predictable redirects."""
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
    proc = subprocess.run(cmd, text=True, capture_output=True, check=False, encoding="utf-8", errors="replace")
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


def collect_schema_types_from_schemas(schemas: list[Any]) -> set[str]:
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
        else:
            nodes.append(value)
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


def page_path(req: PageRequirement) -> str:
    path = re.sub(r"^https?://[^/]+/?", "", req.expected_final_url)
    return path.strip("/")


def schema_entity_issues(req: PageRequirement, schemas: list[Any], body: str) -> list[str]:
    issues: list[str] = []
    nodes = schema_nodes(schemas)
    canonical = req.expected_final_url
    canonical_no_trailing = normalize_url(canonical).rstrip("/")
    webpage_id = canonical_no_trailing + "/#webpage"
    person_id = "https://simplyrest.com/ferdie-farhad/#person"
    organization_id = "https://simplyrest.com/#organization"
    current_path = page_path(req)
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

    page_type = "ProfilePage" if current_path == "ferdie-farhad" else "CollectionPage" if current_path == "mattress-reviews" else "WebPage"
    page_nodes = schema_nodes_by_type(nodes, page_type)
    if not page_nodes:
        issues.append(f"missing {page_type} node")
    else:
        page_node = page_nodes[0]
        if str(page_node.get("@id", "")) != webpage_id:
            issues.append(f"{page_type} @id mismatch")
        if normalize_url(schema_node_url(page_node)) != normalize_url(canonical):
            issues.append(f"{page_type} url mismatch")

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

    for article in schema_nodes_by_type(nodes, "Article"):
        if schema_ref_id(article.get("author")) != person_id:
            issues.append("Article author does not reference Ferdie")
        if schema_ref_id(article.get("publisher")) != organization_id:
            issues.append("Article publisher does not reference Simply Rest")
        if "mainEntityOfPage" in article and schema_ref_id(article.get("mainEntityOfPage")) != webpage_id:
            issues.append("Article mainEntityOfPage mismatch")

    breadcrumb_nodes = schema_nodes_by_type(nodes, "BreadcrumbList")
    if not breadcrumb_nodes:
        issues.append("missing BreadcrumbList node")
    else:
        crumbs = breadcrumb_nodes[0].get("itemListElement")
        crumb_items = crumbs if isinstance(crumbs, list) else []
        if not crumb_items:
            issues.append("BreadcrumbList has no items")
        elif normalize_url(str(crumb_items[-1].get("item", ""))) != normalize_url(canonical):
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

    if current_path == "mattress-reviews":
        item_lists = schema_nodes_by_type(nodes, "ItemList")
        if not item_lists:
            issues.append("missing ItemList node")
        elif "https://simplyrest.com/mattress-reviews/amerisleep-as3/" not in json.dumps(item_lists[0]):
            issues.append("ItemList missing AS3 review URL")

    if current_path == "mattress-reviews/amerisleep-as3":
        product_id = canonical_no_trailing + "/#product"
        review_id = canonical_no_trailing + "/#review"
        review_nodes = schema_nodes_by_type(nodes, "Review")
        product_nodes = schema_nodes_by_type(nodes, "Product")
        if not review_nodes:
            issues.append("missing Review node")
        else:
            review = review_nodes[0]
            if str(review.get("@id", "")) != review_id:
                issues.append("Review @id mismatch")
            if schema_ref_id(review.get("itemReviewed")) != product_id:
                issues.append("Review itemReviewed does not reference AS3 product")
            rating = review.get("reviewRating")
            try:
                rating_value = float(str(rating.get("ratingValue", ""))) if isinstance(rating, dict) else -1
                best_rating = float(str(rating.get("bestRating", ""))) if isinstance(rating, dict) else -1
            except ValueError:
                rating_value = -1
                best_rating = -1
            if not (0 <= rating_value <= 10):
                issues.append("Review ratingValue outside 0-10")
            if best_rating != 10:
                issues.append("Review bestRating is not 10")
        if not product_nodes:
            issues.append("missing Product node")
        else:
            product = product_nodes[0]
            if str(product.get("@id", "")) != product_id:
                issues.append("Product @id mismatch")
            brand = product.get("brand")
            brand_name = str(brand.get("name", "")) if isinstance(brand, dict) else str(brand or "")
            if brand_name != "Amerisleep":
                issues.append("Product brand is not Amerisleep")
        for article in schema_nodes_by_type(nodes, "Article"):
            if "about" in article and schema_ref_id(article.get("about")) != product_id:
                issues.append("Article about does not reference AS3 product")

    return sorted(set(issues))


def media_evidence_issues(req: PageRequirement, body: str) -> list[str]:
    issues: list[str] = []
    if req.requires_visible_media:
        if not re.search(r"<img\b|wp-block-image", body, flags=re.I):
            issues.append("missing visible Ferdie image/media")
    if req.requires_as3_media:
        image_count = len(re.findall(r"<img\b|wp-block-image", body, flags=re.I))
        video_count = len(re.findall(r"<video\b|wp-block-video", body, flags=re.I))
        if image_count < 5:
            issues.append(f"AS3 has fewer than 5 image blocks/assets: {image_count}")
        if video_count < 3:
            issues.append(f"AS3 has fewer than 3 video blocks/assets: {video_count}")
        if "{{AS3_" in body:
            issues.append("unresolved AS3 media token found")
        if present(body, "Approved AS3 testing assets should be uploaded"):
            issues.append("AS3 placeholder upload copy still present")
    return issues


def present(body: str, marker: str) -> bool:
    return marker.lower() in body.lower()


def yes_no(value: bool) -> str:
    return "yes" if value else "no"


def evaluate_page(req: PageRequirement, timeout: int) -> dict[str, str]:
    status, final_url, body = curl_fetch(req.url, timeout)
    canonical = extract_canonical(body)
    schemas, schema_script_count, schema_parse_errors = extract_schemas(body)
    schema_types = collect_schema_types_from_schemas(schemas)
    missing_schema = [schema_type for schema_type in req.required_schema_types if schema_type not in schema_types]
    entity_issues = schema_entity_issues(req, schemas, body) if schema_parse_errors == 0 else ["schema parse errors prevent entity validation"]
    media_issues = media_evidence_issues(req, body)
    missing_markers = [marker for marker in req.required_markers if not present(body, marker)]
    missing_links = [link for link in req.required_links if link not in body]
    forbidden = [marker for marker in FORBIDDEN_PUBLIC_MARKERS if present(body, marker)]

    has_affiliate = present(body, "affiliate disclosure") or present(body, "may earn a commission")
    has_medical = (
        present(body, "not medical advice")
        or present(body, "medical limitation")
        or present(body, "do not diagnose")
    )
    final_ok = normalize_url(final_url) == normalize_url(req.expected_final_url)
    canonical_ok = normalize_url(canonical) == normalize_url(req.expected_final_url)
    status_ok = 200 <= status < 300
    disclosure_ok = (not req.requires_affiliate_disclosure or has_affiliate) and (
        not req.requires_medical_limit or has_medical
    )
    schema_ok = not missing_schema and not entity_issues and schema_script_count > 0 and schema_parse_errors == 0
    content_ok = not missing_markers and not missing_links and not forbidden and not media_issues
    overall = status_ok and final_ok and canonical_ok and disclosure_ok and schema_ok and content_ok

    return {
        "page": req.name,
        "url": req.url,
        "status": str(status),
        "final_url": final_url,
        "status_ok": yes_no(status_ok),
        "final_url_ok": yes_no(final_ok),
        "canonical": canonical,
        "canonical_ok": yes_no(canonical_ok),
        "schema_script_count": str(schema_script_count),
        "schema_parse_errors": str(schema_parse_errors),
        "schema_types_found": "; ".join(sorted(schema_types)),
        "missing_schema_types": "; ".join(missing_schema),
        "schema_entity_issues": "; ".join(entity_issues),
        "media_evidence_issues": "; ".join(media_issues),
        "affiliate_disclosure_found": yes_no(has_affiliate),
        "medical_limit_found": yes_no(has_medical),
        "missing_content_markers": "; ".join(missing_markers),
        "missing_required_links": "; ".join(missing_links),
        "forbidden_public_markers": "; ".join(forbidden),
        "overall_pass": yes_no(overall),
    }


SCRIPT_DIR = Path(__file__).resolve().parent


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument(
        "--output",
        default=str(SCRIPT_DIR / "simplyrest-phase1-live-qa-report-2026-06-25.tsv"),
        help="TSV report output path",
    )
    parser.add_argument("--timeout", type=int, default=20)
    parser.add_argument(
        "--base-url",
        default=DEFAULT_BASE_URL,
        help="Base URL to validate, e.g. https://simplyrest.com (default) or http://localhost:8080 for local staging.",
    )
    args = parser.parse_args()

    pages = rebase_pages(PAGES, args.base_url)
    rows = [evaluate_page(req, args.timeout) for req in pages]
    output = Path(args.output)
    output.parent.mkdir(parents=True, exist_ok=True)
    with output.open("w", newline="", encoding="utf-8") as f:
        writer = csv.DictWriter(f, fieldnames=list(rows[0].keys()), delimiter="\t")
        writer.writeheader()
        writer.writerows(rows)

    failures = [row for row in rows if row["overall_pass"] != "yes"]
    print(f"Wrote {output}")
    print(f"Pages checked: {len(rows)}")
    print(f"Failures: {len(failures)}")
    for row in failures:
        print(f"- {row['page']}: status={row['status']} final={row['final_url']} pass={row['overall_pass']}")
    return 1 if failures else 0


if __name__ == "__main__":
    sys.exit(main())
