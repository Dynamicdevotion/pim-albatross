<?php

namespace Modules\Localization\Tests\Unit;

use Modules\Localization\Support\MultilanguageFeature;
use Tests\TestCase;

class MultilanguageFeatureTest extends TestCase
{
    public function test_enabled_when_the_config_flag_is_on(): void
    {
        config(['localization.multilanguage_enabled' => true]);

        $this->assertTrue(MultilanguageFeature::enabled());
    }

    public function test_disabled_when_the_config_flag_is_off(): void
    {
        config(['localization.multilanguage_enabled' => false]);

        $this->assertFalse(MultilanguageFeature::enabled());
    }
}
