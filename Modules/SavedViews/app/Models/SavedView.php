<?php

namespace Modules\SavedViews\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\SavedViews\Database\Factories\SavedViewFactory;

/**
 * A personal, reusable snapshot of a table/page's filters and visible columns.
 *
 * `resource` is a free string identifying which screen the view belongs to
 * (e.g. "pricing.prices"); views are always scoped to their owner.
 */
class SavedView extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'resource',
        'name',
        'filters',
        'columns',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'columns' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    public function scopeForResource(Builder $query, string $resource): void
    {
        $query->where('resource', $resource);
    }

    protected static function newFactory(): SavedViewFactory
    {
        return SavedViewFactory::new();
    }
}
