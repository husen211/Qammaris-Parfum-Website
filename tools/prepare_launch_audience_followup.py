"""Prepare clear audience-only updates and a current photographed launch review."""

import argparse
import hashlib
import json
import re
from collections import Counter
from pathlib import Path

from curate_shopee_launch_copy import audience, clean_copy
from prepare_launch_review import MAINTENANCE_HEADERS, safe_cell, write_csv
from prepare_shopee_description_preview import extract


def curate(capture, basic):
    if capture.get("schema") != "qammaris-shopee-pairing-capture-v1" or capture.get("environment") != "staging":
        raise ValueError("Expected a current staging capture")
    products, sources = capture["products"], capture["sources"]
    if not 1 <= len(products) <= 1000 or len({p["id"] for p in products}) != len(products):
        raise ValueError("Invalid or duplicate catalog products")
    if len({s["id"] for s in sources}) != len(sources) or len({b["id"] for b in basic}) != len(basic):
        raise ValueError("Duplicate source identity")
    sources, basic = {s["id"]: s for s in sources}, {b["id"]: b for b in basic}
    wave, changes, gaps, corrections = [], [], [], []
    seen_uuid, seen_shopee = set(), set()
    counts = Counter()
    for entry in products:
        p = entry["product"]
        if p["publication_status"] != "draft" or p["is_active"]:
            continue
        if not entry["images"]:
            counts["without_photos_untouched"] += 1
            continue
        identities = entry["identities"]
        app_ids = [i["external_product_id"] for i in identities if i["provider"] == "qammaris_app"]
        shopee_ids = [i["external_product_id"] for i in identities if i["provider"] == "shopee"]
        if len(app_ids) != 1 or len(shopee_ids) != 1 or app_ids[0] not in sources or shopee_ids[0] not in basic:
            raise ValueError("Photographed draft lacks exact source identities")
        if app_ids[0] in seen_uuid or shopee_ids[0] in seen_shopee:
            raise ValueError("Duplicate linked provider identity")
        seen_uuid.add(app_ids[0])
        seen_shopee.add(shopee_ids[0])
        source, raw = sources[app_ids[0]], basic[shopee_ids[0]]
        if source["hidden"] or p.get("qammaris_app_hidden") or source["source"] == "app":
            counts["protected_source_untouched"] += 1
            continue
        cleaned, cleanup = clean_copy(raw["description"])
        if not cleaned or p["description"] != cleaned:
            raise ValueError("Existing copy differs from approved Shopee source; manual review required")
        if not re.fullmatch(r"[a-f0-9]{64}", entry.get("fingerprint", "")) or not re.fullmatch(
                r"\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})", entry.get("expected_updated_at", "")):
            raise ValueError("Missing exact Laravel timestamp/fingerprint guard")
        gender, decision, evidence = audience([p["name"], raw["name"]], cleaned)
        counts["photographed"] += 1
        if set(entry["blockers"]) - {"gender"}:
            gaps.append([safe_cell(v) for v in [p["id"], p["name"],
                "kategori" if "category" in entry["blockers"] else "", "ukuran/harga" if "offer" in entry["blockers"] else "",
                "|".join(entry["blockers"])]])
            continue
        offers = [v for v in entry["variants"] if v["is_active"]]
        if len(offers) != 1 or int(offers[0]["volume"]) < 1 or float(offers[0]["price"]) <= 0:
            raise ValueError("Readiness/offer disagreement")
        if p["gender"] and p["gender"] != gender:
            # Never overwrite a human field, including when the source inference differs.
            decision, gender = "conflict", ""
            evidence.append({"value": p["gender"], "origin": "website", "text": "Existing website audience: " + p["gender"]})
        row = {"product_id": str(p["id"]), "uuid": app_ids[0], "shopee_id": shopee_ids[0],
            "name": p["name"], "slug": p["slug"], "brand": entry["brand"], "category": entry["category"],
            "price": str(offers[0]["price"]), "size_ml": str(offers[0]["volume"]),
            "source": source["source"], "availability": p["availability_status"], "image_count": len(entry["images"]),
            "expected_updated_at": entry["expected_updated_at"], "expected_row_fingerprint": entry["fingerprint"],
            "source_name": raw["name"], "raw_description": raw["description"], "cleaned_description": cleaned,
            "proposed_gender": gender, "audience_decision": decision, "audience_evidence": evidence, "cleanup": cleanup,
            "blockers": entry["blockers"]}
        wave.append(row)
        if not p["gender"] and gender:
            values = dict.fromkeys(MAINTENANCE_HEADERS, "")
            for key in MAINTENANCE_HEADERS[:3]:
                values[key] = row[key]
            values["gender"] = gender
            changes.append([values[k] for k in MAINTENANCE_HEADERS])
        if not gender:
            corrections.append([safe_cell(v) for v in [p["id"], p["name"], "", decision,
                " | ".join(e["text"] for e in evidence)]])
    summary = {**dict(counts), "wave": len(wave), "audience_updates": len(changes),
        "publication_ready": sum(not r["blockers"] and r["audience_decision"] == "clear" for r in wave),
        "audience": dict(Counter(r["audience_decision"] for r in wave)), "other_size_concentration_gaps": len(gaps)}
    return sorted(wave, key=lambda r: (r["name"].casefold(), int(r["product_id"]))), changes, corrections, gaps, summary


def prepare(source, capture_path, output):
    private = (Path.cwd() / "storage/app/private").resolve()
    if private not in capture_path.resolve().parents or private not in output.resolve().parents or output.exists():
        raise ValueError("Use a private capture and new private output")
    if any(p.stat().st_size > 16 * 1024 * 1024 for p in (source, capture_path)):
        raise ValueError("Input exceeds limit")
    source_hash = hashlib.sha256(source.read_bytes()).hexdigest()
    capture_bytes = capture_path.read_bytes()
    capture = json.loads(capture_bytes)
    wave, changes, corrections, gaps, summary = curate(capture, extract(source))
    if hashlib.sha256(source.read_bytes()).hexdigest() != source_hash:
        raise ValueError("Source changed during preparation")
    output.mkdir()
    result = {"schema": "qammaris-wave-one-v1", "source_file": source.name, "source_sha256": source_hash,
        "capture_sha256": hashlib.sha256(capture_bytes).hexdigest(), "captured_at": capture["captured_at"],
        "checkpoint": capture["checkpoint"], "summary": summary, "data": wave, "published": False}
    (output / "wave-one.json").write_text(json.dumps(result, ensure_ascii=False, indent=2), encoding="utf8")
    if changes:
        write_csv(output / "maintenance-audience.csv", MAINTENANCE_HEADERS, changes)
    write_csv(output / "audience-corrections.csv", ["product_id", "name", "gender_owner", "reason", "evidence"], corrections)
    write_csv(output / "size-concentration-corrections.csv", ["product_id", "name", "category_owner", "size_price_owner", "blockers"], gaps)
    headers = ["product_id", "uuid", "expected_updated_at", "expected_row_fingerprint", "slug", "name", "brand", "category", "gender", "price", "size_ml", "availability"]
    ready = [r for r in wave if not r["blockers"] and r["audience_decision"] == "clear"]
    write_csv(output / "publication-candidates.csv", headers, [[safe_cell(r["proposed_gender"] if k == "gender" else r[k]) for k in headers] for r in ready])
    return summary


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    for name in ("source", "capture", "output"):
        parser.add_argument(name, type=Path)
    args = parser.parse_args()
    print(json.dumps(prepare(args.source, args.capture, args.output)))
