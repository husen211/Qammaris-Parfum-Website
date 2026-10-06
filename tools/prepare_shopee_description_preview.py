"""Prepare description-only proposals from Owner's Shopee export; never apply them."""

import argparse
import hashlib
import json
import re
import zipfile
from collections import Counter
from pathlib import Path

from prepare_launch_review import MAINTENANCE_HEADERS, safe_cell, validate, write_csv
from prepare_shopee_media_export import read_xml


def extract(source):
    ns = {"s": "http://schemas.openxmlformats.org/spreadsheetml/2006/main"}
    rows, seen, header_found = [], set(), False
    with zipfile.ZipFile(source) as archive:
        shared = []
        if "xl/sharedStrings.xml" in archive.namelist():
            shared = ["".join(node.itertext()) for node in read_xml(archive, "xl/sharedStrings.xml").findall("s:si", ns)]
        for row in read_xml(archive, "xl/worksheets/sheet1.xml").findall("s:sheetData/s:row", ns):
            cells = {}
            for cell in row.findall("s:c", ns):
                column = re.sub(r"\d+$", "", cell.get("r", ""))
                if column not in ("A", "B", "C", "D"):
                    continue
                if cell.find("s:f", ns) is not None:
                    raise ValueError("Source product cells must contain literal values, not formulas")
                node = cell.find("s:v", ns)
                value = node.text or "" if node is not None else ""
                if cell.get("t") == "s":
                    value = shared[int(value)]
                elif cell.get("t") == "inlineStr":
                    value = "".join(cell.find("s:is", ns).itertext())
                cells[column] = value
            if cells.get("A") == "Kode Produk":
                if header_found or [cells.get(k) for k in ("B", "C", "D")] != ["SKU Induk", "Nama Produk", "Deskripsi Produk"]:
                    raise ValueError("Unexpected Shopee basic header")
                header_found = True
                continue
            if not header_found or not cells.get("A", "").isdigit():
                continue
            if cells["A"] in seen or not cells.get("C", "").strip():
                raise ValueError("Duplicate Shopee ID or missing source name")
            seen.add(cells["A"])
            rows.append({"id": cells["A"], "name": cells["C"], "description": cells.get("D", "")})
    if not header_found or not 1 <= len(rows) <= 5000:
        raise ValueError("No supported Shopee basic rows")
    return rows


def prepare(source, capture, baseline, output):
    private = (Path.cwd() / "storage/app/private").resolve()
    capture, baseline, output = capture.resolve(), baseline.resolve(), output.resolve()
    if any(private not in path.parents for path in (capture, baseline, output)) or not output.is_dir():
        raise ValueError("Capture, media baseline and output directory must be private")
    if any(path.stat().st_size > 16 * 1024 * 1024 for path in (source, capture, baseline)):
        raise ValueError("Input exceeds supported size")
    source_hash = hashlib.sha256(source.read_bytes()).hexdigest()
    snapshot = json.loads(capture.read_text(encoding="utf-8"))
    catalog = validate(snapshot)
    media = json.loads(baseline.read_text(encoding="utf-8"))
    if media.get("schema") != "qammaris-shopee-media-v1":
        raise ValueError("Unsupported media pairing baseline")
    media_by_id = {row["id"]: row for row in media["data"]}
    if len(media_by_id) != len(media["data"]):
        raise ValueError("Duplicate media provider ID")
    source_rows = extract(source)
    source_by_id = {row["id"]: row for row in source_rows}
    report, preview, maintenance, statuses, paired = [], [], [], Counter(), set()
    for row in catalog:
        shopee_id = row["shopee_id"]
        text = source_by_id.get(shopee_id)
        original = media_by_id.get(shopee_id)
        status = "candidate"
        if not shopee_id:
            status = "no_shopee_identity"
        elif not text:
            status = "missing_source_id"
        elif not original or original["name"].strip() != text["name"].strip():
            status = "source_name_changed_review"
        elif not text["description"].strip():
            status = "empty_source_description"
        elif len(text["description"].strip()) > 20000:
            status = "description_too_long"
        elif str(row.get("description") or "").strip():
            status = "existing_description_review"
        elif safe_cell(text["description"].strip()) != text["description"].strip():
            status = "formula_sensitive_text_review"
        if text:
            paired.add(shopee_id)
        statuses[status] += 1
        description = text["description"] if text else ""
        report.append([safe_cell(value) for value in [row["product_id"], row["uuid"], row["name"],
            shopee_id, text["name"] if text else "", status, row["description"], description,
            "|".join(row["blockers"]), source.name, source_hash]])
        if status == "candidate":
            preview.append([safe_cell(value) for value in [row["product_id"], row["name"], text["name"], description]])
            values = dict.fromkeys(MAINTENANCE_HEADERS, "")
            for key in MAINTENANCE_HEADERS[:3]:
                values[key] = row[key]
            values["deskripsi_produk"] = description.strip()
            maintenance.append([values[key] for key in MAINTENANCE_HEADERS])
    if any((output / name).exists() for name in ("description-review.csv", "description-preview.csv", "maintenance-descriptions.csv", "summary.json")):
        raise ValueError("Outputs exist; use a new private directory")
    write_csv(output / "description-review.csv", ["product_id", "uuid", "website_name", "shopee_id",
        "shopee_name", "decision", "current_description", "proposed_description", "current_blockers",
        "source_file", "source_sha256"], report)
    if maintenance:
        write_csv(output / "description-preview.csv", ["product_id", "website_name", "shopee_name", "proposed_description"], preview)
        write_csv(output / "maintenance-descriptions.csv", MAINTENANCE_HEADERS, maintenance)
    if hashlib.sha256(source.read_bytes()).hexdigest() != source_hash:
        raise ValueError("Source workbook changed during extraction")
    summary = {"schema": "qammaris-shopee-description-preview-v1", "source_file": source.name,
        "source_sha256": source_hash, "capture_sha256": hashlib.sha256(capture.read_bytes()).hexdigest(),
        "captured_at": snapshot["captured_at"], "checkpoint": snapshot["checkpoint"],
        "source_products": len(source_rows), "source_descriptions": sum(bool(r["description"].strip()) for r in source_rows),
        "drafts": len(catalog), "decisions": dict(statuses), "proposed_rows": len(maintenance),
        "source_ids_without_draft_pair": len(set(source_by_id) - paired), "source_unchanged": True,
        "applied_or_published": False}
    with (output / "summary.json").open("x", encoding="utf-8") as stream:
        json.dump(summary, stream, ensure_ascii=False, indent=2)
    return summary


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    for name in ("source", "capture", "baseline", "output"):
        parser.add_argument(name, type=Path)
    args = parser.parse_args()
    print(json.dumps(prepare(args.source, args.capture, args.baseline, args.output)))


if __name__ == "__main__":
    main()
