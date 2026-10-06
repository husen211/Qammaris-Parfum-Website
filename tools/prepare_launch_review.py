"""Prepare a read-only launch review and blank guarded maintenance templates."""

import argparse
import csv
import hashlib
import json
import re
from collections import Counter
from pathlib import Path


MAINTENANCE_HEADERS = [
    "product_id", "expected_updated_at", "expected_row_fingerprint", "nama_produk",
    "deskripsi_produk", "harga", "brand", "gender", "stok_snapshot", "terlaris",
    "kategori", "ukuran_ml", "top_notes", "middle_notes", "base_notes",
]

REVIEW_HEADERS = [
    "review_group", "product_id", "uuid", "shopee_id", "name", "brand", "price",
    "size_ml", "category", "availability", "source", "image_count", "publish_blockers",
]


def safe_cell(value):
    text = "" if value is None else str(value).replace("\r\n", "\n").replace("\r", "\n")
    if text.startswith(("\t", "\n")) or text.lstrip().startswith(("=", "+", "-", "@")):
        return "'" + text
    return text


def review_group(row):
    if row["source"] == "app":
        return "4_source_app_review"
    if set(row["blockers"]).issubset({"description", "gender"}):
        return "1_description_audience_only"
    if row["image_count"] > 0:
        return "2_has_photos_other_fields"
    return "3_needs_photos_and_data"


def validate(snapshot):
    if snapshot.get("schema") != "qammaris-launch-review-v1" or snapshot.get("environment") != "staging":
        raise ValueError("Expected a fresh staging launch review capture")
    rows = snapshot.get("data")
    if not isinstance(rows, list) or not 1 <= len(rows) <= 1000:
        raise ValueError("Expected 1 to 1000 draft rows")
    ids, uuids, shopee_ids = set(), set(), set()
    for row in rows:
        product_id = row.get("product_id", "")
        uuid = row.get("uuid", "")
        shopee_id = row.get("shopee_id")
        if not isinstance(product_id, str) or not product_id.isdigit() or int(product_id) < 1:
            raise ValueError("Invalid product ID")
        if not isinstance(uuid, str) or not re.fullmatch(r"[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}", uuid):
            raise ValueError("Invalid source UUID")
        if product_id in ids or uuid in uuids or (shopee_id is not None and shopee_id in shopee_ids):
            raise ValueError("Duplicate website/source/provider identity")
        ids.add(product_id)
        uuids.add(uuid)
        if shopee_id is not None:
            if not isinstance(shopee_id, str) or not shopee_id.isdigit():
                raise ValueError("Invalid Shopee identity")
            shopee_ids.add(shopee_id)
        if row.get("publication_status") != "draft" or row.get("hidden") is not False:
            raise ValueError("Only visible linked drafts belong in this review")
        if not isinstance(row.get("blockers"), dict) or row.get("source") not in ("majoo", "app"):
            raise ValueError("Missing actual readiness/source classification")
        if not isinstance(row.get("image_count"), int) or not 0 <= row["image_count"] <= 3:
            raise ValueError("Invalid image count")
        if not isinstance(row.get("name"), str) or not row["name"].strip():
            raise ValueError("Missing product name")
        if not re.fullmatch(r"[a-f0-9]{64}", row.get("expected_row_fingerprint", "")):
            raise ValueError("Missing current Laravel row fingerprint")
        if not re.fullmatch(r"\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})", row.get("expected_updated_at", "")):
            raise ValueError("Missing current Laravel timestamp guard")
    return sorted(rows, key=lambda row: (review_group(row), row["name"].casefold(), int(row["product_id"])))


def maintenance_row(row):
    values = dict.fromkeys(MAINTENANCE_HEADERS, "")
    for key in MAINTENANCE_HEADERS[:3]:
        values[key] = row[key]
    # Repeating a safe current name gives the reviewer context; all proposed edits stay blank.
    # A formula-sensitive name stays only in the sanitized review report, never as a mutation.
    if safe_cell(row["name"]) == row["name"] and row["name"] == row["name"].strip():
        values["nama_produk"] = row["name"]
    return [values[key] for key in MAINTENANCE_HEADERS]


def write_csv(path, headers, rows):
    with path.open("x", encoding="utf-8-sig", newline="") as stream:
        writer = csv.writer(stream)
        writer.writerow(headers)
        writer.writerows(rows)


def prepare(source, output):
    private = (Path.cwd() / "storage/app/private").resolve()
    source, output = source.resolve(), output.resolve()
    if private not in source.parents or private not in output.parents or not output.is_dir():
        raise ValueError("Capture and existing output directory must be inside storage/app/private")
    if source.stat().st_size > 16 * 1024 * 1024:
        raise ValueError("Capture exceeds supported size")
    raw = source.read_bytes()
    snapshot = json.loads(raw)
    rows = validate(snapshot)
    first = [row for row in rows if review_group(row) == "1_description_audience_only"]
    paths = [output / name for name in ["launch-review.csv", "maintenance-first.csv", "maintenance-all.csv", "summary.json"]]
    if any(path.exists() for path in paths):
        raise ValueError("Review outputs already exist; capture into a new directory")
    write_csv(paths[0], REVIEW_HEADERS, [
        [safe_cell(value) for value in [review_group(row), *[row.get(key) for key in REVIEW_HEADERS[1:-1]], "|".join(row["blockers"])]]
        for row in rows
    ])
    if first:
        write_csv(paths[1], MAINTENANCE_HEADERS, [maintenance_row(row) for row in first])
    write_csv(paths[2], MAINTENANCE_HEADERS, [maintenance_row(row) for row in rows])
    summary = {
        "schema": "qammaris-launch-review-package-v1", "captured_at": snapshot["captured_at"],
        "environment": "staging", "checkpoint": snapshot["checkpoint"],
        "capture_sha256": hashlib.sha256(raw).hexdigest(), "drafts": len(rows),
        "first_review": len(first), "groups": dict(Counter(review_group(row) for row in rows)),
        "blockers": dict(Counter(key for row in rows for key in row["blockers"])),
        "published_or_applied": False,
    }
    with paths[3].open("x", encoding="utf-8") as stream:
        json.dump(summary, stream, ensure_ascii=False, indent=2)
    return summary


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("capture", type=Path)
    parser.add_argument("output", type=Path)
    args = parser.parse_args()
    summary = prepare(args.capture, args.output)
    print(json.dumps({key: summary[key] for key in ("drafts", "first_review", "groups", "blockers", "published_or_applied")}))


if __name__ == "__main__":
    main()
