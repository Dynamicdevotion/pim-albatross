<?php

namespace Modules\Branding\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Branding\Models\Setting;

/**
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    protected $model = Setting::class;

    public function definition(): array
    {
        return [
            'brand_name' => null,
            'primary_color' => null,
        ];
    }
}
