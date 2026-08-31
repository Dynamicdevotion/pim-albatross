<?php

namespace Modules\ExportProdotti\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Modules\ExportProdotti\Database\Factories\ExportRecordFactory;

/**
 * One product-export run: the chosen format and columns, the filter snapshot
 * the list was showing, and — once generated — the file ready for download.
 *
 * `status`: pending → processing → completed | failed.
 */
class ExportRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'format',
        'columns',
        'filters',
        'sort',
        'status',
        'total_rows',
        'row_count',
        'stored_path',
        'original_filename',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'columns' => 'array',
            'filters' => 'array',
            'sort' => 'array',
            'total_rows' => 'integer',
            'row_count' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Deleting a run (row action, bulk action or the prune command) also
        // removes its generated file.
        static::deleting(function (ExportRecord $record): void {
            if (filled($record->stored_path)) {
                Storage::disk(config('exportprodotti.disk'))->delete($record->stored_path);
            }
        });
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

    public function isDownloadable(): bool
    {
        return $this->isCompleted() && filled($this->stored_path);
    }

    protected static function newFactory(): ExportRecordFactory
    {
        return ExportRecordFactory::new();
    }
}
