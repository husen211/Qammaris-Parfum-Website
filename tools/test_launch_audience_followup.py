"""Regression checks for supplemental factual audience preparation and review."""

import base64
import copy
import hashlib
import json
import tempfile
import unittest
from pathlib import Path

from build_launch_copy_review import build
from prepare_launch_audience_followup import curate


class LaunchAudienceFollowupTest(unittest.TestCase):
    def fixture(self):
        basic = [{"id": "123", "name": "Source EDP 50ML", "description": "Gender: Unisex\nNotes: Rose"}]
        product = {"id": 1, "product": {"id": 1, "name": "Website EDP 50ML", "slug": "website-edp-50ml", "description": basic[0]["description"],
            "gender": None, "publication_status": "draft", "is_active": False, "availability_status": "available"},
            "images": [{"id": 1}], "identities": [{"provider": "qammaris_app", "external_product_id": "uuid"},
                {"provider": "shopee", "external_product_id": "123"}], "variants": [{"is_active": True, "volume": 50, "price": 100000}],
            "brand": "Source brand", "category": "Eau de Parfum", "blockers": {"gender": "Missing"},
            "expected_updated_at": "2026-10-06T03:59:11+00:00", "fingerprint": "a" * 64}
        return {"schema": "qammaris-shopee-pairing-capture-v1", "environment": "staging", "products": [product],
            "sources": [{"id": "uuid", "hidden": False, "source": "majoo"}]}, basic

    def test_only_clear_blank_audience_is_proposed(self):
        cap, basic = self.fixture()
        before = copy.deepcopy(cap)
        wave, changes, _, _, summary = curate(cap, basic)
        self.assertEqual(cap, before)
        self.assertEqual(summary["audience_updates"], 1)
        self.assertEqual([v for v in changes[0][3:] if v], ["Unisex"])
        self.assertEqual(wave[0]["expected_row_fingerprint"], "a" * 64)

    def test_unknown_conflict_existing_audience_and_nonstructural_rows_are_not_changed(self):
        for scenario in ("unknown", "conflict", "existing", "category"):
            cap, basic = self.fixture()
            p = cap["products"][0]
            if scenario == "unknown":
                basic[0]["description"] = p["product"]["description"] = "Notes: Rose"
            elif scenario == "conflict":
                basic[0]["name"] = "Source For Men"
            elif scenario == "existing":
                p["product"]["gender"] = "Wanita"
            else:
                p["blockers"]["category"] = "Missing"
            _, changes, corrections, gaps, _ = curate(cap, basic)
            self.assertEqual(changes, [])
            self.assertTrue(gaps if scenario == "category" else corrections)

    def test_missing_photo_hidden_source_app_and_published_remain_untouched(self):
        for scenario in ("image", "hidden", "app", "published"):
            cap, basic = self.fixture()
            if scenario == "image": cap["products"][0]["images"] = []
            if scenario == "hidden": cap["sources"][0]["hidden"] = True
            if scenario == "app": cap["sources"][0]["source"] = "app"
            if scenario == "published": cap["products"][0]["product"]["publication_status"] = "published"
            wave, changes, _, _, _ = curate(cap, basic)
            self.assertEqual((wave, changes), ([], []))

    def test_missing_provider_duplicate_source_changed_copy_and_invalid_guards_refuse(self):
        for scenario in ("provider", "duplicate", "copy", "guard"):
            cap, basic = self.fixture()
            if scenario == "provider": cap["products"][0]["identities"].pop()
            if scenario == "duplicate": basic.append(basic[0])
            if scenario == "copy": cap["products"][0]["product"]["description"] = "Human edit"
            if scenario == "guard": cap["products"][0]["expected_updated_at"] = "no timezone"
            with self.assertRaises(ValueError): curate(cap, basic)

    def test_current_review_counts_safe_output_and_cover_cohort_guard(self):
        cap, basic = self.fixture()
        wave, _, _, _, summary = curate(cap, basic)
        wave[0]["name"] = '<script>alert(1)</script>'
        image = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=')
        with tempfile.TemporaryDirectory(dir=Path.cwd() / 'storage/app/private') as directory:
            root = Path(directory)
            (root / 'wave.json').write_text(json.dumps({"schema": "qammaris-wave-one-v1", "data": wave,
                "summary": {**summary, "without_photos_untouched": 95}, "published": False}), encoding='utf8')
            cover = {"id": "1", "mime": "image/png", "bytes": base64.b64encode(image).decode(), "sha256": hashlib.sha256(image).hexdigest()}
            (root / 'capture.json').write_text(json.dumps({"products": cap['products'], "covers": [cover]}), encoding='utf8')
            for name in ('audience-corrections.csv', 'size-concentration-corrections.csv', 'publication-candidates.csv'):
                (root / name).write_text('product_id,name\n', encoding='utf8')
            build(root / 'wave.json', root / 'capture.json', root / 'review')
            html = (root / 'review/index.html').read_text(encoding='utf8')
            self.assertIn('1 draft untuk review', html)
            self.assertIn('95 draft tanpa foto', html)
            self.assertNotIn('<script>alert(1)', html)
            (root / 'capture.json').write_text(json.dumps({"products": cap['products'], "covers": [cover, cover]}), encoding='utf8')
            with self.assertRaises(ValueError): build(root / 'wave.json', root / 'capture.json', root / 'bad-review')


if __name__ == '__main__':
    unittest.main()
