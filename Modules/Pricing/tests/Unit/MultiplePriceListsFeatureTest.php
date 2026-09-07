<?php

namespace Modules\Pricing\Tests\Unit;

use Modules\Pricing\Support\MultiplePriceListsFeature;
use Tests\TestCase;

class MultiplePriceListsFeatureTest extends TestCase
{
    public function test_enabled_when_the_config_flag_is_on(): void
    {
        config(['pricing.multiple_price_lists_enabled' => true]);

        $this->assertTrue(MultiplePriceListsFeature::enabled());
    }

    public function test_disabled_when_the_config_flag_is_off(): void
    {
        config(['pricing.multiple_price_lists_enabled' => false]);

        $this->assertFalse(MultiplePriceListsFeature::enabled());
    }
}
