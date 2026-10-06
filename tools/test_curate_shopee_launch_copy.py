"""Regression checks for factual launch copy curation; no catalog writes."""

import unittest

from curate_shopee_launch_copy import audience, clean_copy


class LaunchCopyCurationTest(unittest.TestCase):
    def test_removes_promotions_preserving_notes_and_size(self):
        raw = "🍋 READY STOCK\nTop notes: Citrus #parfum\nGratis ongkir seluruh Indonesia\nEDP 100 ml"
        text, removals = clean_copy(raw)
        self.assertEqual(text, "Top notes: Citrus\nEDP 100 ml")
        self.assertEqual(removals["emoji_removed"], 1)
        self.assertEqual(removals["ready_stock_removed"], 1)
        self.assertEqual(removals["hashtags_removed"], ["#parfum"])
        self.assertEqual(len(removals["shipping_lines_removed"]), 1)

    def test_conflicting_name_and_description_require_review(self):
        value, decision, evidence = audience(["MPF Leather For Men"], "Perfume: Unisex")
        self.assertEqual((value, decision), ("", "conflict"))
        self.assertEqual({e["value"] for e in evidence}, {"Pria", "Unisex"})

    def test_dual_label_requires_review(self):
        self.assertEqual(audience(["Bling"], "Gender: MEN/Unisex")[:2], ("", "conflict"))

    def test_explicit_both_audiences_are_unisex(self):
        for text in ["untuk pria maupun wanita", "Pria dan wanita", "for women and men"]:
            self.assertEqual(audience(["Scent"], text)[:2], ("Unisex", "clear"))

    def test_comparison_and_inspiration_do_not_assign_audience(self):
        self.assertEqual(audience(["Fattan Pour Femme"], "Berbeda dari versi pour homme")[0], "Wanita")
        self.assertEqual(audience(["Azul"], "Inspired by Dior Homme")[:2], ("", "unknown"))

    def test_aroma_and_bottle_color_are_not_audience(self):
        self.assertEqual(audience(["Floral"], "aroma maskulin dan botol pink")[:2], ("", "unknown"))

    def test_unicode_facts_and_plain_source_are_preserved(self):
        text = "Eau de Parfum 100 ml\nTop notes: Rose & Bergamot\nDeskripsi: keharuman élégante."
        self.assertEqual(clean_copy(text)[0], text)
        self.assertEqual(audience(["For Her"], text)[0], "Wanita")


if __name__ == "__main__":
    unittest.main()
