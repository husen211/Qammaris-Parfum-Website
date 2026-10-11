<?php

namespace Tests\Unit;

use App\Support\FragranceNoteNormalizer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FragranceNoteNormalizerTest extends TestCase
{
    private function reader(): FragranceNoteNormalizer
    {
        return new FragranceNoteNormalizer(json_decode(file_get_contents(__DIR__.'/../../resources/data/fragrance-note-aliases.json'), true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_known_typos_languages_and_compounds_keep_original_evidence(): void
    {
        $raw = 'Vanila, Melati & Patchoulli';
        $result = $this->reader()->note($raw);
        $this->assertSame($raw, $result['raw']);
        $this->assertSame(['jasmine', 'patchouli', 'vanilla'], $result['notes']);
        $this->assertSame('recognized', $result['status']);
        $this->assertSame(['bergamot', 'lemon', 'tonka bean'], $this->reader()->note('Bergamot dan Lemon; Tonka beans')['notes']);
    }

    public function test_aliases_and_duplicates_do_not_multiply_a_layer(): void
    {
        $layers = $this->reader()->layers(['top' => ['Vanilla', 'Vanila', 'Vanilla and benzoin'], 'middle' => ['Musk'], 'base' => ['Musk']]);
        $this->assertSame(['benzoin', 'vanilla'], $layers['top']['notes']);
        $this->assertCount(3, $layers['top']['entries']);
        $this->assertSame(['musk'], $layers['middle']['notes']);
        $this->assertSame(['musk'], $layers['base']['notes']);
    }

    public function test_placeholders_and_opaque_trade_labels_remain_unknown(): void
    {
        $this->assertSame('placeholder', $this->reader()->note('Tidak dirinci oleh brand')['status']);
        $this->assertSame([], $this->reader()->note('TBC')['notes']);
        $this->assertSame('unmapped', $this->reader()->note('Blue Crystal')['status']);
        $result = $this->reader()->note('Mysterywood and Vanilla');
        $this->assertSame(['vanilla'], $result['notes']);
        $this->assertSame('mysterywood', $result['unmapped']);
        $this->assertSame('partial', $result['status']);
    }

    public function test_short_words_do_not_match_inside_other_notes_and_typos_are_not_guessed(): void
    {
        $this->assertSame(['ambergris'], $this->reader()->note('Ambergris')['notes']);
        $this->assertSame([], $this->reader()->note('Vannillax')['notes']);
        $this->assertSame('recognized', $this->reader()->note('Ylang-Ylang / Lilly of the Valley')['status']);
    }

    public function test_unknown_modifiers_are_preserved_and_source_does_not_invent_performance(): void
    {
        $note = $this->reader()->note('Genetic Magic Musk');
        $this->assertSame('genetic magic', $note['unmapped']);
        $this->assertArrayNotHasKey('projection', $note);
        $this->assertArrayNotHasKey('sweetness', $this->reader()->note('Vanilla'));
        $this->assertSame([], $this->reader()->note('EDP')['notes']);
    }

    public function test_malformed_notes_do_not_become_valid_filled_layers(): void
    {
        $this->assertFalse($this->reader()->layers(null)['top']['valid']);
        $this->assertFalse($this->reader()->layers(['top' => 'Lemon'])['top']['valid']);
        $this->assertFalse($this->reader()->layers(['top' => [['name' => 'Lemon']]])['top']['valid']);
        $this->assertFalse($this->reader()->layers(['top' => [str_repeat('x', 1001)]])['top']['valid']);
    }

    public function test_dictionary_cannot_silently_reassign_an_alias(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FragranceNoteNormalizer(['schema' => 'qammaris-note-aliases-v1', 'notes' => ['a' => ['duplicate'], 'b' => ['duplicate']]]);
    }
}
