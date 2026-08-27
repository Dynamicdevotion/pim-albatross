<?php

namespace Modules\Pricing\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Pricing\Models\PriceList;

/**
 * Ensures a "Standard" price list exists and that exactly one list is the
 * default. Idempotent.
 */
class PricingSeeder extends Seeder
{
    public function run(): void
    {
        $standard = PriceList::query()->firstOrCreate(
            ['name' => 'Standard'],
            ['is_default' => true, 'active' => true],
        );

        if (! PriceList::query()->where('is_default', true)->exists()) {
            $standard->forceFill(['is_default' => true, 'active' => true])->save();
        }
    }
}
