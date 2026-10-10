<?php

namespace Tests\Unit;

use App\Support\FragrancePreference\PreferenceAnswers;
use App\Support\FragrancePreference\ProfileBuilder;
use App\Support\FragrancePreference\RecommendationEngine;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FragrancePreferenceEngineTest extends TestCase
{
    private ProfileBuilder $builder;

    private RecommendationEngine $engine;

    protected function setUp(): void
    {
        $this->builder = ProfileBuilder::fromFiles();
        $this->engine = new RecommendationEngine($this->builder);
    }

    private function answers(array $changes = []): array
    {
        return array_replace(['budget_max' => 300000, 'likes' => ['citrus'], 'avoid' => [], 'use' => 'any', 'environment' => 'any', 'sweetness' => 'any', 'projection' => 'unknown', 'longevity' => 'not_priority', 'gender' => 'all', 'favorite_product_id' => null], $changes);
    }

    private function row(int $id = 1, array $changes = []): array
    {
        $row = array_replace(['id' => $id, 'name' => 'Synthetic '.$id, 'description' => '', 'notes' => ['top' => ['Bergamot'], 'middle' => ['Jasmine'], 'base' => ['Musk']], 'public' => true, 'hidden' => false, 'archived' => false, 'active_offer_count' => 1, 'price' => '200000.00', 'size_ml' => 50, 'availability' => 'available', 'gender' => 'Unisex'], $changes);
        $row['profile'] = $this->builder->build($row);

        return $row;
    }

    private function ids(array $result, string $slot = 'main'): array
    {
        return array_column($result[$slot], 'product_id');
    }

    public function test_e01_conflicting_liked_and_avoided_aroma_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->engine->recommend($this->answers(['avoid' => ['citrus']]), []);
    }

    public function test_e02_avoid_is_applied_before_ranking_and_partial_unknown_is_disclosed(): void
    {
        $oud = $this->row(1, ['notes' => ['top' => ['Bergamot'], 'middle' => ['Oud'], 'base' => ['Musk']]]);
        $uncertain = $this->row(2, ['notes' => ['top' => ['Bergamot'], 'middle' => ['TBC'], 'base' => ['Musk']]]);
        $result = $this->engine->recommend($this->answers(['avoid' => ['oud']]), [$oud, $uncertain]);
        $this->assertSame([2], $this->ids($result));
        $this->assertStringContainsString('tidak membuktikan bebas', implode(' ', $result['main'][0]['limitations']));
    }

    public function test_e03_exact_budget_and_exact_110_percent_use_separate_slots(): void
    {
        $result = $this->engine->recommend($this->answers(['budget_max' => 200000]), [$this->row(1), $this->row(2, ['price' => '220000.00']), $this->row(3, ['price' => '220000.01'])]);
        $this->assertSame([1], $this->ids($result));
        $this->assertSame([2], $this->ids($result, 'alternative'));
        $this->assertStringContainsString('10%', $result['alternative'][0]['budget_label']);
    }

    public function test_alternative_requires_fewer_than_three_or_strictly_better_score(): void
    {
        $rows = [$this->row(1), $this->row(2), $this->row(3), $this->row(4, ['price' => '220000.00'])];
        $this->assertSame([], $this->engine->recommend($this->answers(['budget_max' => 200000]), $rows)['alternative']);
        $rows[3] = $this->row(4, ['price' => '220000.00', 'description' => 'Kekuatan Aroma: Kuat']);
        $result = $this->engine->recommend($this->answers(['budget_max' => 200000, 'projection' => 'strong']), $rows);
        $this->assertSame([4], $this->ids($result, 'alternative'));
    }

    public function test_e04_ready_habis_and_filter_before_selection_without_changing_favorite(): void
    {
        $rows = [$this->row(1, ['availability' => 'sold_out']), $this->row(2), $this->row(3), $this->row(4)];
        $answers = $this->answers(['favorite_product_id' => 1, 'likes' => []]);
        $result = $this->engine->recommend($answers, $rows);
        $this->assertSame([2, 3, 4], $this->ids($result));
        $this->assertSame('personal', $this->engine->recommend($answers, $rows, true)['mode']);
        $onlySoldOut = $this->engine->recommend($answers, [$rows[0]]);
        $this->assertSame('sold_out', $onlySoldOut['main'][0]['availability']);
        $this->assertTrue($this->engine->recommend($answers, [$rows[0]], true)['empty']);
    }

    public function test_e05_gender_is_light_and_unisex_remains_eligible(): void
    {
        $unisex = $this->row(1);
        $male = $this->row(2, ['gender' => 'Pria']);
        $result = $this->engine->recommend($this->answers(['gender' => 'pria']), [$unisex, $male]);
        $this->assertSame([2, 1], $this->ids($result));
        $this->assertSame(5000, $result['main'][0]['score_components_internal']['gender']);
    }

    public function test_e06_unknown_attributes_do_not_get_points_or_concentration_shortcuts(): void
    {
        $row = $this->row(1, ['description' => 'EDP Extrait, sangat tahan lama, terbaik dan manis sekali.', 'notes' => ['top' => ['Vanila', 'Vanilla'], 'middle' => ['TBC'], 'base' => ['Bergamot']]]);
        $this->assertNull($row['profile']['attributes']['sweetness']);
        $this->assertNull($row['profile']['attributes']['longevity']);
        $this->assertSame(['vanilla'], $row['profile']['normalized_layers']['top']['notes']);
        $result = $this->engine->recommend($this->answers(['sweetness' => 'sweet', 'longevity' => 'all_day']), [$row]);
        $this->assertSame(40000, $result['main'][0]['score_internal']);
    }

    public function test_negation_conflict_and_unlabelled_promotional_performance_stay_unknown(): void
    {
        $profile = $this->row(1, ['description' => "Projection: Strong\nKekuatan Aroma: Sedang\nKemanisan: tidak manis\nLongevity: 8–10 hours\nKetahanan Aroma: 10-12 jam\nTahan sampai 24 jam!\nAroma: bukan oud"])['profile'];
        $this->assertNull($profile['attributes']['projection']);
        $this->assertNull($profile['attributes']['sweetness']);
        $this->assertNull($profile['attributes']['longevity']);
        $this->assertNotContains('oud', $profile['attributes']['families']);
        $this->assertContains('conflicting_fact', array_column($profile['review_issues'], 'code'));
    }

    public function test_factual_description_dominance_outranks_notes_but_avoids_keep_note_evidence(): void
    {
        $row = $this->row(1, ['description' => "Profile Aroma: Woody Musky\nKekuatan Aroma: Strong", 'notes' => ['top' => ['Bergamot'], 'middle' => ['Cedar'], 'base' => ['Musk']]]);
        $this->assertSame(['musk', 'wood'], $row['profile']['attributes']['aroma_target']);
        $this->assertTrue($this->engine->recommend($this->answers(), [$row])['empty']);
        $this->assertTrue($this->engine->recommend($this->answers(['likes' => ['wood'], 'avoid' => ['citrus']]), [$row])['empty']);
    }

    public function test_e07_only_verified_identity_deduplicates_sizes(): void
    {
        $first = $this->row(1);
        $second = $this->row(2, ['size_ml' => 100]);
        $this->assertSame([1, 2], $this->ids($this->engine->recommend($this->answers(), [$first, $second])));
        foreach ([$first['id'], $second['id']] as $id) {
            $identity = ['key' => 'verified-same-fragrance', 'status' => 'verified', 'evidence' => 'Existing admin verified brand and fragrance identity.'];
            if ($id === 1) {
                $first['profile']['verified_identity'] = $identity;
            } else {
                $second['profile']['verified_identity'] = $identity;
            }
        }
        $this->assertSame([1], $this->ids($this->engine->recommend($this->answers(), [$first, $second])));
    }

    public function test_e08_zero_match_is_empty_and_does_not_fill_with_popularity(): void
    {
        $row = $this->row(1, ['notes' => ['top' => ['Rose'], 'middle' => ['Jasmine'], 'base' => ['Musk']], 'is_best_seller' => true]);
        $this->assertTrue($this->engine->recommend($this->answers(), [$row])['empty']);
        $this->assertSame([], $this->ids($this->engine->recommend($this->answers(), [$row])));
    }

    public function test_e09_stale_hidden_archived_and_invalid_offer_candidates_are_excluded(): void
    {
        $stale = $this->row(1);
        $stale['notes']['middle'][] = 'Oud';
        $rows = [$stale, $this->row(2, ['hidden' => true]), $this->row(3, ['archived' => true]), $this->row(4, ['public' => false]), $this->row(5, ['price' => '0']), $this->row(6, ['active_offer_count' => 2]), $this->row(7)];
        $this->assertSame([7], $this->ids($this->engine->recommend($this->answers(), $rows)));
        $rows[6]['profile']['parser_fingerprint'] = str_repeat('0', 64);
        $this->assertTrue($this->engine->recommend($this->answers(), $rows)['empty']);
    }

    public function test_e10_unknown_taste_requires_context_and_uses_exploration_label(): void
    {
        $row = $this->row(1, ['description' => 'Penggunaan: Kantor, indoor']);
        $this->assertTrue($this->engine->recommend($this->answers(['likes' => []]), [$row])['empty']);
        $result = $this->engine->recommend($this->answers(['likes' => [], 'use' => 'office', 'environment' => 'ac']), [$row]);
        $this->assertSame('exploration', $result['mode']);
        $this->assertSame('Pilihan awal untuk dieksplorasi', $result['title']);
        $this->assertSame(10000, $result['main'][0]['score_internal']);
        $this->assertCount(2, $result['main'][0]['reasons']);
    }

    public function test_repeated_synonyms_do_not_increase_score_and_ranking_is_stable(): void
    {
        $one = $this->row(1);
        $repeat = $this->row(2, ['notes' => ['top' => ['Bergamot', 'Calabrian Bergamot', 'Bergamot'], 'middle' => ['Jasmine', 'Melati'], 'base' => ['Musk']]]);
        $result = $this->engine->recommend($this->answers(), [$repeat, $one]);
        $this->assertSame([1, 2], $this->ids($result));
        $this->assertSame($result['main'][0]['score_internal'], $result['main'][1]['score_internal']);
        $this->assertSame($result, $this->engine->recommend($this->answers(), [$one, $repeat]));
    }

    public function test_evidence_completeness_and_fit_precede_ready_and_lower_price_ties(): void
    {
        $known = $this->row(1, ['availability' => 'sold_out', 'price' => '299000.00', 'description' => 'Kekuatan Aroma: Sedang']);
        $unknown = $this->row(2, ['price' => '190000.00']);
        $result = $this->engine->recommend($this->answers(), [$unknown, $known]);
        $this->assertSame([1, 2], $this->ids($result));
        $matched = $this->row(3, ['price' => '300000.00', 'availability' => 'sold_out', 'description' => "Kekuatan Aroma: Kuat\nKetahanan Aroma: 8 Jam+"]);
        $result = $this->engine->recommend($this->answers(['projection' => 'strong', 'longevity' => 'all_day']), [$unknown, $known, $matched]);
        $this->assertSame(3, $result['main'][0]['product_id']);
        $this->assertSame(['min_hours' => 8, 'max_hours' => null], $matched['profile']['attributes']['longevity']);
    }

    public function test_favorite_helps_but_cannot_override_explicit_aroma_choice(): void
    {
        $favorite = $this->row(1, ['notes' => ['top' => ['Rose'], 'middle' => ['Oud'], 'base' => ['Musk']]]);
        $citrus = $this->row(2);
        $result = $this->engine->recommend($this->answers(['favorite_product_id' => 1]), [$favorite, $citrus]);
        $this->assertSame([2], $this->ids($result));
        $this->assertLessThanOrEqual(50000, $result['main'][0]['score_components_internal']['aroma']);
    }

    public function test_unicode_bullets_use_claims_and_ambiguous_story_are_handled_without_warnings(): void
    {
        $profile = $this->row(1, ['description' => "• Cocok untuk: kantor, indoor\nCocok dipakai harian, indoor maupun outdoor.\n‘Promosi café yang terbaik!’\nKetahanan Aroma: 7 Jam+"])['profile'];
        $this->assertContains('office', $profile['attributes']['context']);
        $this->assertContains('daily', $profile['attributes']['context']);
        $this->assertContains('outdoor', $profile['attributes']['context']);
        $this->assertSame(['min_hours' => 7, 'max_hours' => null], $profile['attributes']['longevity']);
        $this->assertNull($profile['attributes']['sweetness']);
    }

    public function test_explicit_character_priority_does_not_import_story_words_or_claim_sweetness(): void
    {
        $character = $this->row(1, ['description' => 'Parfum gourmand dengan karakter creamy; bukan janji performa.']);
        $this->assertNotContains('gourmand', $character['profile']['attributes']['families']);
        $story = $this->row(2, ['description' => 'Cerita tentang taman floral dan dessert gourmand di kafe.']);
        $this->assertSame(['citrus', 'floral', 'musk'], $story['profile']['attributes']['aroma_target']);
        $clear = $this->row(3, ['description' => 'Parfum gourmand menghadirkan pengalaman aroma coffee.', 'notes' => ['top' => ['Bergamot'], 'middle' => ['Coffee'], 'base' => ['Musk']]]);
        $this->assertSame(['gourmand'], $clear['profile']['attributes']['aroma_target']);
        $this->assertNull($clear['profile']['attributes']['sweetness']);
        $result = $this->engine->recommend($this->answers(['likes' => ['gourmand']]), [$clear]);
        $this->assertSame(50000, $result['main'][0]['score_components_internal']['aroma']);
    }

    public function test_favorite_reason_cites_only_families_used_for_the_match(): void
    {
        $favorite = $this->row(1, ['notes' => ['top' => ['Bergamot'], 'middle' => [], 'base' => []]]);
        $candidate = $this->row(2);
        $result = $this->engine->recommend($this->answers(['likes' => [], 'favorite_product_id' => 1]), [$favorite, $candidate]);
        foreach ($result['main'] as $row) {
            $this->assertSame(['families.citrus'], $row['reasons'][0]['evidence_keys']);
        }
    }

    public function test_input_rejects_unlisted_fields_non_integer_budget_and_unknown_families(): void
    {
        foreach ([['age' => 30], ['budget_max' => '200000'], ['likes' => ['unlisted']], ['avoid' => [['nested']]], ['likes' => ['fruit', 'wood', 'citrus', 'oud']]] as $change) {
            try {
                PreferenceAnswers::validate($this->answers($change));
                $this->fail('Invalid answers accepted.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
