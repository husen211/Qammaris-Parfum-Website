<?php

namespace Tests\Unit;

use App\Support\SearchMatcher;
use PHPUnit\Framework\TestCase;

class SearchMatcherTest extends TestCase
{
    public function test_shared_browser_and_server_examples(): void
    {
        $cases = json_decode(file_get_contents(__DIR__.'/../fixtures/search-cases.json'), true, flags: JSON_THROW_ON_ERROR);
        foreach ($cases as $case) {
            $this->assertSame($case['matches'], SearchMatcher::score($case['term'], $case['texts'], $case['identifiers'] ?? []) !== null, $case['term']);
        }
    }

    public function test_exact_match_outranks_typo_and_partial_matches(): void
    {
        $exact = SearchMatcher::score('reverie', ['Reverie Aqua']);
        $this->assertLessThan(SearchMatcher::score('reverie', ['Reveria Aqua']), $exact);
        $this->assertLessThan(SearchMatcher::score('reverie', ['Reveries']), $exact);
        $this->assertNull(SearchMatcher::score('reverie aqua', ['Reverie Noir']));
    }
}
