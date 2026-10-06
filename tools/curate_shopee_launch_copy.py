"""Clean Owner-approved Shopee copy and prepare the fixed first-wave draft cohort."""

import argparse
import hashlib
import json
import re
from collections import Counter
from pathlib import Path

from prepare_launch_review import MAINTENANCE_HEADERS, safe_cell, validate, write_csv
from prepare_shopee_description_preview import extract


EMOJI = re.compile("[\U0001F100-\U0001FAFF\u2600-\u27BF\uFE0E\uFE0F\u200D\u20E3]")
READY = re.compile(r"\bready[\s-]*stock\b", re.I)
SHIPPING = re.compile(r"\b(?:ongkir|ongkos\s+kirim|free\s+shipping|shipping\s+fee|biaya\s+kirim)\b", re.I)
HASHTAG = re.compile(r"(?<!\w)#[\w-]+", re.UNICODE)
PAIR = re.compile(r"\b(?:pria\s*(?:dan|maupun|&|/)\s*wanita|wanita\s*(?:dan|maupun|&|/)\s*pria|men\s*(?:and|&|/)\s*women|women\s*(?:and|&|/)\s*men)\b", re.I)
MALE_NAME = re.compile(r"\b(?:for\s+(?:men|man|him)|pour\s+homme|homme|him)\b", re.I)
FEMALE_NAME = re.compile(r"\b(?:for\s+(?:women|woman|her)|pour\s+femme|femme|her)\b", re.I)
COMPARISON = re.compile(r"\b(?:inspirasi|inspired|berbeda|dibanding|mirip|comparison|similar)\b", re.I)


def clean_copy(text):
    removed_shipping, hashtags = [], []
    emoji_count = len(EMOJI.findall(text))
    ready_count = len(READY.findall(text))
    lines = []
    for line in text.replace("\r\n", "\n").replace("\r", "\n").split("\n"):
        if SHIPPING.search(line):
            removed_shipping.append(line.strip())
            continue
        hashtags.extend(HASHTAG.findall(line))
        line = HASHTAG.sub("", READY.sub("", EMOJI.sub("", line)))
        line = re.sub(r"[ \t]+", " ", line).strip()
        if line and not re.search(r"\w", line, re.UNICODE):
            line = ""
        lines.append(line)
    cleaned = re.sub(r"\n{3,}", "\n\n", "\n".join(lines)).strip()
    return cleaned, {"emoji_removed": emoji_count, "ready_stock_removed": ready_count,
                     "shipping_lines_removed": removed_shipping, "hashtags_removed": hashtags}


def audience(names, description):
    found, evidence = set(), []

    def add(value, origin, text):
        found.add(value)
        item = {"value": value, "origin": origin, "text": text.strip()}
        if item not in evidence:
            evidence.append(item)

    for name in names:
        if re.search(r"\bunisex\b", name, re.I) or PAIR.search(name):
            add("Unisex", "name", name)
        else:
            if MALE_NAME.search(name):
                add("Pria", "name", name)
            if FEMALE_NAME.search(name):
                add("Wanita", "name", name)
    for line in description.splitlines():
        if COMPARISON.search(line):
            continue
        label = re.search(r"^\s*(?:gender|perfume|peruntukan|kategori|suitable\s+for)\s*:\s*(?:parfum\s+)?(man|men|pria|woman|women|wanita)\b", line, re.I)
        # A dual label such as Gender: MEN/Unisex is conflicting, not a guess.
        if label and not PAIR.search(line):
            add("Pria" if label[1].lower() in ("man", "men", "pria") else "Wanita", "description", line)
        if re.search(r"\bunisex\b", line, re.I) or PAIR.search(line):
            add("Unisex", "description", line)
            continue
        if re.search(r"\b(?:parfum|perfume|wewangian)\s+(?:pria|men)\b|\b(?:for|untuk|bagi)\s+(?:pria|men|him)\b", line, re.I):
            add("Pria", "description", line)
        if re.search(r"\b(?:parfum|perfume|wewangian)\s+(?:wanita|women)\b|\b(?:for|untuk|bagi)\s+(?:wanita|women|her)\b", line, re.I):
            add("Wanita", "description", line)
    return (next(iter(found)) if len(found) == 1 else ""), ("clear" if len(found) == 1 else "conflict" if found else "unknown"), evidence


def prepare(source, capture, baseline, output):
    private = (Path.cwd() / "storage/app/private").resolve()
    capture, baseline, output = capture.resolve(), baseline.resolve(), output.resolve()
    if any(private not in p.parents for p in (capture, baseline, output)) or not output.is_dir():
        raise ValueError("Use private capture, baseline and output paths")
    if any(p.stat().st_size > 16 * 1024 * 1024 for p in (source, capture, baseline)):
        raise ValueError("Input too large")
    source_hash = hashlib.sha256(source.read_bytes()).hexdigest()
    snapshot = json.loads(capture.read_text(encoding="utf-8"))
    catalog = validate(snapshot)
    media = json.loads(baseline.read_text(encoding="utf-8"))
    if media.get("schema") != "qammaris-shopee-media-v1":
        raise ValueError("Unsupported pairing baseline")
    media_by_id = {r["id"]: r for r in media["data"]}
    source_by_id = {r["id"]: r for r in extract(source)}
    wave = [r for r in catalog if r["source"] != "app" and set(r["blockers"]) <= {"description", "gender"}]
    if len(wave) != 113 or any(r["description"] or r["gender"] for r in wave):
        raise ValueError("Expected the original 113 blank-copy wave; review changed cohort before proceeding")
    paths = [output / p for p in ("wave-one.json", "maintenance-wave-one.csv", "wave-one-review.csv", "audience-corrections.csv", "size-concentration-corrections.csv")]
    if any(p.exists() for p in paths):
        raise ValueError("Output exists; use a new private directory")
    proposals, csv_rows, review, corrections = [], [], [], []
    for row in wave:
        raw = source_by_id.get(row["shopee_id"])
        original = media_by_id.get(row["shopee_id"])
        if not raw or not original or raw["name"].strip() != original["name"].strip():
            raise ValueError("Wave identity/source name changed")
        description, cleanup = clean_copy(raw["description"])
        if not description or len(description) > 20000 or safe_cell(description) != description:
            raise ValueError("Cleaned description requires manual review")
        gender, decision, evidence = audience([row["name"], raw["name"]], description)
        values = dict.fromkeys(MAINTENANCE_HEADERS, "")
        for key in MAINTENANCE_HEADERS[:3]:
            values[key] = row[key]
        values.update(deskripsi_produk=description, gender=gender)
        csv_rows.append([values[k] for k in MAINTENANCE_HEADERS])
        proposals.append({**row, "source_name": raw["name"], "raw_description": raw["description"],
                          "cleaned_description": description, "proposed_gender": gender,
                          "audience_decision": decision, "audience_evidence": evidence, "cleanup": cleanup})
        review.append([safe_cell(v) for v in [row["product_id"], row["name"], gender, decision,
            " | ".join(e["text"] for e in evidence), description]])
        if not gender:
            corrections.append([safe_cell(v) for v in [row["product_id"], row["name"], "", decision, " | ".join(e["text"] for e in evidence)]])
    gaps = [[safe_cell(v) for v in [r["product_id"], r["name"],
        "kategori" if "category" in r["blockers"] else "", "ukuran/harga" if "offer" in r["blockers"] else ""]]
        for r in catalog if "category" in r["blockers"] or "offer" in r["blockers"]]
    if hashlib.sha256(source.read_bytes()).hexdigest() != source_hash:
        raise ValueError("Source changed during preparation")
    result = {"schema": "qammaris-wave-one-v1", "source_file": source.name, "source_sha256": source_hash,
        "capture_sha256": hashlib.sha256(capture.read_bytes()).hexdigest(), "captured_at": snapshot["captured_at"],
        "checkpoint": snapshot["checkpoint"], "summary": {"wave": len(wave),
        "audience": dict(Counter(r["audience_decision"] for r in proposals)), "other_size_concentration_gaps": len(gaps)},
        "data": proposals, "published": False}
    with paths[0].open("x", encoding="utf-8") as f:
        json.dump(result, f, ensure_ascii=False, indent=2)
    write_csv(paths[1], MAINTENANCE_HEADERS, csv_rows)
    write_csv(paths[2], ["product_id", "name", "proposed_gender", "decision", "evidence", "cleaned_description"], review)
    write_csv(paths[3], ["product_id", "name", "gender_owner", "reason", "evidence"], corrections)
    write_csv(paths[4], ["product_id", "name", "category_owner", "size_price_owner"], gaps)
    return result["summary"]


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    for name in ("source", "capture", "baseline", "output"):
        parser.add_argument(name, type=Path)
    args = parser.parse_args()
    print(json.dumps(prepare(args.source, args.capture, args.baseline, args.output)))
