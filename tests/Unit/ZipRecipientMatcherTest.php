<?php

namespace Tests\Unit;

use App\Services\ZipRecipientMatcher;
use PHPUnit\Framework\TestCase;

class ZipRecipientMatcherTest extends TestCase
{
    public function test_exact_match(): void
    {
        $results = (new ZipRecipientMatcher())->match(
            [1 => 'Lucknow', 2 => 'Kanpur Nagar'],
            ['lucknow.pdf', 'kanpur-nagar.pdf'],
        );

        $this->assertSame('lucknow.pdf', $results[1]['filename']);
        $this->assertSame('filename_auto', $results[1]['matched_via']);
        $this->assertSame('kanpur-nagar.pdf', $results[2]['filename']);
    }

    public function test_fuzzy_match_within_threshold(): void
    {
        $results = (new ZipRecipientMatcher())->match(
            [1 => 'Kanpur Nagar'],
            ['kanpur_nager.pdf'], // one-letter typo
        );

        $this->assertSame('kanpur_nager.pdf', $results[1]['filename']);
    }

    public function test_no_match_leaves_filename_null(): void
    {
        $results = (new ZipRecipientMatcher())->match(
            [1 => 'Varanasi'],
            ['completely-different-name.pdf'],
        );

        $this->assertNull($results[1]['filename']);
        $this->assertSame('none', $results[1]['matched_via']);
    }

    public function test_each_filename_used_at_most_once(): void
    {
        $results = (new ZipRecipientMatcher())->match(
            [1 => 'Agra', 2 => 'Agra Division'],
            ['agra.pdf'],
        );

        $usedFilenames = array_filter(array_column($results, 'filename'));
        $this->assertCount(1, $usedFilenames);
    }

    public function test_decorative_prefix_on_filename(): void
    {
        $results = (new ZipRecipientMatcher())->match(
            [1 => 'Agra', 2 => 'Barabanki'],
            ['5_Shops_AGRA.xlsx', '5_Shops_BARA_BANKI.xlsx'],
        );

        $this->assertSame('5_Shops_AGRA.xlsx', $results[1]['filename']);
        $this->assertSame('5_Shops_BARA_BANKI.xlsx', $results[2]['filename']);
    }

    public function test_official_long_name_matches_short_recipient_name(): void
    {
        $results = (new ZipRecipientMatcher())->match(
            [1 => 'Bhadohi'],
            ['5_Shops_SANT_RAVIDAS_NAGAR_BHADOHI.xlsx'],
        );

        $this->assertSame('5_Shops_SANT_RAVIDAS_NAGAR_BHADOHI.xlsx', $results[1]['filename']);
    }

    public function test_short_filename_matches_long_recipient_name(): void
    {
        $results = (new ZipRecipientMatcher())->match(
            [1 => 'Lakhimpur Kheri'],
            ['5_Shops_KHERI.xlsx'],
        );

        $this->assertSame('5_Shops_KHERI.xlsx', $results[1]['filename']);
    }
}
