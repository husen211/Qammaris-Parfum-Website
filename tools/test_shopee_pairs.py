"""Regression checks for external mapping parsing, validation and protected review output."""

import base64
import csv
import hashlib
import json
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

from build_shopee_pair_review import build
from prepare_shopee_pairs import HEADERS, candidates, prepare


class ShopeePairsTest(unittest.TestCase):
    def test_multiline_candidate_and_leading_zero_sku_are_preserved(self):
        row = {"kandidat_ambigu": "Name\n100 ml [000123] (0.5) | Other [SKU-2] (0.42)"}
        self.assertEqual([c["sku"] for c in candidates(row)], ["000123", "SKU-2"])
        self.assertEqual(candidates(row)[0]["name"], "Name\n100 ml")

    def test_malformed_candidate_is_rejected_instead_of_dropped(self):
        with self.assertRaises(ValueError):
            candidates({"kandidat_ambigu": "Name missing identifier"})

    def fixture(self, root):
        row = dict(zip(HEADERS, ["123", "Source EDP 50ML", "000123", "Feed name", "1.00", "kuat", ""]))
        source = {"id": "uuid-one", "sku": "000123", "name": "Feed name", "brand": "Brand", "hidden": False}
        product = {"id": 1, "fingerprint": "hash", "product": {"publication_status": "draft"}, "identities": [{"provider": "qammaris_app", "external_product_id": "uuid-one"}]}
        capture = {"schema": "qammaris-shopee-pairing-capture-v1", "environment": "staging", "captured_at": "2026-10-06T03:00:00Z", "sources": [source], "products": [product]}
        (root / "capture.json").write_text(json.dumps(capture), encoding="utf8")
        with (root / "mapping.csv").open("w", encoding="utf-8-sig", newline="") as stream:
            writer = csv.DictWriter(stream, fieldnames=HEADERS)
            writer.writeheader()
            writer.writerow(row)
        for name in ("media.xlsx", "basic.xlsx"):
            (root / name).write_bytes(b"read-only source fixture")
        return row, capture

    def run_prepare(self, root, output):
        with patch("prepare_shopee_pairs.media_extract", return_value={"data": [{"id": "123", "name": "Source EDP 50ML", "photos": ["https://cf.shopee.co.id/cover", "", ""]}]}), patch("prepare_shopee_pairs.basic_extract", return_value=[{"id": "123", "name": "Source EDP 50ML", "description": "READY STOCK\nFacts 🧴\n#promo"}]):
            return prepare(root / "mapping.csv", root / "media.xlsx", root / "basic.xlsx", root / "capture.json", root / output)

    def test_exact_sku_provenance_cleanup_and_no_source_mutation(self):
        with tempfile.TemporaryDirectory(dir=Path.cwd() / "storage/app/private") as directory:
            root = Path(directory)
            self.fixture(root)
            before = {p.name: p.read_bytes() for p in root.iterdir()}
            summary = self.run_prepare(root, "result")
            self.assertEqual(summary["missing_skus"], [])
            data = json.loads((root / "result/pairs.json").read_text(encoding="utf8"))
            self.assertEqual(data["data"][0]["majoo_sku"], "000123")
            self.assertEqual(data["data"][0]["description"], "Facts")
            self.assertEqual(data["provenance"]["mapping_sha256"], hashlib.sha256(before["mapping.csv"]).hexdigest())
            self.assertTrue(all((root / p).read_bytes() == raw for p, raw in before.items()))

    def test_changed_sku_is_reported_without_name_fallback(self):
        with tempfile.TemporaryDirectory(dir=Path.cwd() / "storage/app/private") as directory:
            root = Path(directory)
            _, capture = self.fixture(root)
            capture["sources"][0]["sku"] = "changed"
            (root / "capture.json").write_text(json.dumps(capture), encoding="utf8")
            result = self.run_prepare(root, "result")
            self.assertEqual(result["missing_skus"], ["000123"])
            self.assertEqual(len(result["protected_or_unresolved"]), 1)

    def test_duplicate_source_ids_are_rejected(self):
        with tempfile.TemporaryDirectory(dir=Path.cwd() / "storage/app/private") as directory:
            root = Path(directory)
            row, _ = self.fixture(root)
            with (root / "mapping.csv").open("a", encoding="utf8", newline="") as stream:
                csv.DictWriter(stream, fieldnames=HEADERS).writerow(row)
            with self.assertRaises(ValueError):
                self.run_prepare(root, "result")

    def test_review_escapes_imported_text_flags_occupied_pairs_and_has_no_preselection(self):
        with tempfile.TemporaryDirectory(dir=Path.cwd() / "storage/app/private") as directory:
            root = Path(directory)
            raw = b"test image bytes"
            covers = [{"id": str(i), "mime": "image/png", "bytes": base64.b64encode(raw).decode(), "sha256": hashlib.sha256(raw).hexdigest()} for i in range(56)]
            candidate = {"sku": "001", "name": "</script><script>alert(1)</script>", "score": "0.5", "publication_status": "draft", "existing_shopee_id": "other", "website_id": 1, "uuid": "uuid", "fingerprint": "hash"}
            rows = [{"shopee_id": str(i), "shopee_name": "<img onerror=alert(1)>", "status": "ambigu", "description": "<script>bad</script>", "candidates": [candidate]} for i in range(56)]
            (root / "review.json").write_text(json.dumps({"schema": "qammaris-shopee-pair-review-v1", "provenance": {}, "capture_sha256": "hash", "captured_at": "now", "data": rows}), encoding="utf8")
            (root / "pairs.json").write_text(json.dumps({"schema": "qammaris-pairs-v1", "provenance": {}, "data": []}), encoding="utf8")
            (root / "covers.json").write_text(json.dumps(covers), encoding="utf8")
            build(root / "review.json", root / "covers.json", root / "review")
            text = (root / "review/index.html").read_text(encoding="utf8")
            self.assertNotIn("<script>bad", text)
            self.assertNotIn("</script><script>alert", text)
            self.assertNotIn(" selected", text)
            self.assertEqual(text.count('value="0" disabled'), 0)
            self.assertEqual(text.count('"requires_conflict_review": true'), 56)
            self.assertEqual(text.count('class="choice"'), 56)


if __name__ == "__main__":
    unittest.main()
