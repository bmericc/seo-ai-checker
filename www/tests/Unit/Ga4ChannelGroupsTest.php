<?php

namespace Tests\Unit;

use App\Services\Ga4\Ga4ChannelGroups;
use PHPUnit\Framework\TestCase;

class Ga4ChannelGroupsTest extends TestCase
{
    public function test_missing_channel_data_groups_to_null_instead_of_zeros(): void
    {
        $this->assertNull(Ga4ChannelGroups::group(null));
    }

    public function test_channels_are_bucketed_and_organic_search_is_excluded(): void
    {
        $groups = Ga4ChannelGroups::group([
            'Organic Search' => 500,
            'Direct' => 100,
            'Referral' => 50,
            'Organic Social' => 30,
            'Paid Search' => 20,
            'Paid Social' => 10,
            'Display' => 5,
            'Cross-network' => 4,
            'Email' => 3,
            'Unassigned' => 2,
        ]);

        $this->assertSame([
            'direct' => 100.0,
            'referral' => 50.0,
            'social' => 30.0,
            'paid' => 39.0,
            'other' => 5.0,
        ], $groups);
    }
}
