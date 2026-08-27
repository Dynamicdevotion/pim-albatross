<?php

namespace Modules\Localization\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A content language, managed from the admin panel. `is_base` marks the single
 * language every translatable record must have (currently Italian); `active`
 * controls whether the language is offered for editing in the panel.
 */
class Language extends Model
{
    protected $fillable = [
        'code',
        'name',
        'active',
        'is_base',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'is_base' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }
}
