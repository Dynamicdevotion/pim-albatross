<?php

namespace Modules\ImportGestionali\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Modules\ImportGestionali\Database\Factories\ImportRecordFactory;

/**
 * One product-import run: the file, the chosen mapping, and the outcome.
 *
 * `status`: pending → processing → completed | failed.
 */
class ImportRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'original_filename',
        'stored_path',
        'status',
        'update_existing',
        'mapping',
        'meta',
        'total_rows',
        'created_count',
        'updated_count',
        'skipped_count',
        'issues',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'update_existing' => 'boolean',
            'mapping' => 'array',
            'meta' => 'array',
            'issues' => 'array',
            'total_rows' => 'integer',
            'created_count' => 'integer',
            'updated_count' => 'integer',
            'skipped_count' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Deleting a run (row action, bulk action or the prune command) also
        // removes its stored source file.
        static::deleting(function (ImportRecord $record): void {
            if (filled($record->stored_path)) {
                Storage::disk(config('importgestionali.disk'))->delete($record->stored_path);
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

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    protected static function newFactory(): ImportRecordFactory
    {
        return ImportRecordFactory::new();
    }
}
