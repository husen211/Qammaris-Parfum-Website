"""Prepare Owner-approved Shopee/SKU pairs and explicit candidate review; never writes catalog."""

import argparse
import csv
import hashlib
import json
import re
from collections import Counter, defaultdict
from pathlib import Path

from curate_shopee_launch_copy import clean_copy
from prepare_shopee_description_preview import extract as basic_extract
from prepare_shopee_media_export import extract as media_extract

HEADERS = ["shopee_kode_produk", "shopee_nama", "majoo_sku", "majoo_nama", "skor", "status", "kandidat_ambigu"]
STATUSES = {"sku", "kuat", "perlu_cek", "ambigu", "tidak_ketemu"}


def candidates(row):
    if row["kandidat_ambigu"]:
        result = []
        for raw in row["kandidat_ambigu"].split(" | "):
            match = re.fullmatch(r"(.*?) \[(.*)\] \(([0-9.]+)\)", raw, re.S)
            if not match:
                raise ValueError("Invalid candidate format")
            result.append({"name": match[1], "sku": match[2], "score": match[3]})
        return result
    return [{"name": row["majoo_nama"], "sku": row["majoo_sku"], "score": row["skor"]}] if row["majoo_sku"] else []


def prepare(mapping_path, media_path, basic_path, capture_path, output):
    private = (Path.cwd() / "storage/app/private").resolve()
    if private not in output.resolve().parents or output.exists() or private not in capture_path.resolve().parents:
        raise ValueError("Use private capture and new output directory")
    for path in (mapping_path, media_path, basic_path, capture_path):
        if path.stat().st_size > 16 * 1024 * 1024:
            raise ValueError("Input exceeds limit")
    digests = {key: hashlib.sha256(path.read_bytes()).hexdigest() for key, path in
               [("mapping_sha256", mapping_path), ("media_sha256", media_path), ("basic_sha256", basic_path)]}
    with mapping_path.open(encoding="utf-8-sig", newline="") as stream:
        reader = csv.DictReader(stream)
        if reader.fieldnames != HEADERS:
            raise ValueError("Unexpected mapping headers")
        rows = list(reader)
    if not rows or len(rows) > 1000 or len({r["shopee_kode_produk"] for r in rows}) != len(rows):
        raise ValueError("Missing or duplicate source rows")
    media = {r["id"]: r for r in media_extract(media_path)["data"]}
    basic = {r["id"]: r for r in basic_extract(basic_path)}
    capture = json.loads(capture_path.read_text(encoding="utf8"))
    if capture.get("schema") != "qammaris-shopee-pairing-capture-v1" or capture.get("environment") != "staging":
        raise ValueError("Expected staging pairing capture")
    sources = defaultdict(list)
    websites = {}
    for source in capture["sources"]:
        if source.get("sku"):
            sources[source["sku"]].append(source)
    for product in capture["products"]:
        for identity in product["identities"]:
            if identity["provider"] == "qammaris_app":
                if identity["external_product_id"] in websites:
                    raise ValueError("Duplicate website UUID")
                websites[identity["external_product_id"]] = product

    prepared, review, issues, missing = [], [], [], set()

    def resolve(sku):
        matches = sources.get(sku, [])
        if not matches:
            missing.add(sku)
            return {"sku": sku, "issue": "SKU tidak ada di feed"}
        if len(matches) != 1:
            return {"sku": sku, "issue": "SKU ganda di feed"}
        source = matches[0]
        product = websites.get(source["id"])
        return {"sku": sku, "uuid": source["id"], "name": source["name"], "brand": source["brand"],
                "hidden": source["hidden"], "website_id": product["id"] if product else None,
                "publication_status": product["product"]["publication_status"] if product else None,
                "fingerprint": product["fingerprint"] if product else None,
                "existing_shopee_id": next((i["external_product_id"] for i in product["identities"] if i["provider"] == "shopee"), None) if product else None}

    for row in rows:
        code = row["shopee_kode_produk"]
        if row["status"] not in STATUSES or not code.isdecimal() or code not in media or code not in basic:
            raise ValueError("Invalid status or missing export identity")
        if row["shopee_nama"].strip() != media[code]["name"].strip() or media[code]["name"].strip() != basic[code]["name"].strip():
            raise ValueError("Export source name mismatch")
        description, cleanup = clean_copy(basic[code]["description"])
        if not description or len(description) > 20000:
            raise ValueError("Description needs manual review")
        candidate_list = candidates(row)
        prepared.append({"shopee_id": code, "shopee_name": row["shopee_nama"], "majoo_sku": row["majoo_sku"],
                         "status": row["status"], "description": description, "photos": media[code]["photos"], "candidates": candidate_list})
        if row["status"] in {"perlu_cek", "ambigu"}:
            if not candidate_list:
                raise ValueError("Review row lacks candidates")
            review.append({**prepared[-1], "cleanup": cleanup, "candidates": [{**c, **resolve(c["sku"])} for c in candidate_list]})
        elif row["status"] in {"sku", "kuat"}:
            target = resolve(row["majoo_sku"])
            if target.get("issue") or target.get("hidden") or target.get("publication_status") != "draft":
                issues.append({"shopee_id": code, "name": row["shopee_nama"], "target": target})
    output.mkdir(parents=True)
    summary = {"source_rows": len(rows), "statuses": dict(Counter(r["status"] for r in rows)),
               "review_rows": len(review), "candidate_references": sum(len(r["candidates"]) for r in review),
               "missing_skus": sorted(missing), "protected_or_unresolved": issues}
    (output / "pairs.json").write_text(json.dumps({"schema": "qammaris-pairs-v1", "provenance": digests, "data": prepared}, ensure_ascii=False), encoding="utf8")
    (output / "review-data.json").write_text(json.dumps({"schema": "qammaris-shopee-pair-review-v1", "provenance": digests,
        "capture_sha256": hashlib.sha256(capture_path.read_bytes()).hexdigest(), "captured_at": capture["captured_at"], "data": review, "summary": summary}, ensure_ascii=False), encoding="utf8")
    (output / "summary.json").write_text(json.dumps(summary, ensure_ascii=False, indent=2), encoding="utf8")
    return summary


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    for name in ("mapping", "media", "basic", "capture", "output"):
        parser.add_argument(name, type=Path)
    args = parser.parse_args()
    print(json.dumps(prepare(args.mapping, args.media, args.basic, args.capture, args.output), ensure_ascii=False))
