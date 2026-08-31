<?php

namespace Modules\WooSync\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\WooSync\Database\Factories\WooSyncRunFactory;

/**
 * One "Sincronizza con WooCommerce" run — a single product or a bulk
 * selection. Mirrors ImportRecord / ExportRecord: `status` goes
 * pending → processing → completed | failed, and the per-product outcomes are
 * kept as a JSON `items` list, each `{product, sku, result, reason}` where
 * result is created | updated | skipped | failed.
 */
class WooSyncRun extends Model
{
    use HasFactory;

    protected $table = 'woosync_runs';

    protected $fillable = [
        'user_id',
        'trigger',
        'status',
        'product_ids',
        'total',
        'created_count',
        'updated_count',
        'skipped_count',
        'failed_count',
        'items',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'product_ids' => 'array',
            'items' => 'array',
            'total' => 'integer',
            'created_count' => 'integer',
            'updated_count' => 'integer',
            'skipped_count' => 'integer',
            'failed_count' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRunning(): bool
    {
        return in_array($this->status, ['pending', 'processing'], true);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    protected static function newFactory(): WooSyncRunFactory
    {
        return WooSyncRunFactory::new();
    }
}
