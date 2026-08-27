<?php

namespace Modules\Pricing\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Pricing\Database\Factories\PriceListFactory;
use Modules\Products\Models\Product;
use RuntimeException;

/**
 * A named set of product prices. Exactly one list is the default at any time
 * (mirrors is_base on languages): the default cannot be deactivated or deleted,
 * and marking another list default demotes the previous one.
 */
class PriceList extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_default',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PriceList $list): void {
            if ($list->is_default) {
                $list->active = true;

                static::query()
                    ->when($list->exists, fn (Builder $q) => $q->whereKeyNot($list->getKey()))
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });

        static::deleting(function (PriceList $list): void {
            if ($list->is_default) {
                throw new RuntimeException('The default price list cannot be deleted.');
            }
        });
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_prices')->withPivot('price');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    public static function default(): ?self
    {
        return static::query()->where('is_default', true)->first();
    }

    protected static function newFactory(): PriceListFactory
    {
        return PriceListFactory::new();
    }
}
