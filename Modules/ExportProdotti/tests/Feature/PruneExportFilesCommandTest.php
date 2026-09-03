<?php

namespace Modules\ExportProdotti\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\ExportProdotti\Models\ExportRecord;
use Tests\TestCase;

/**
 * Both an inline and a queued export now go through {@see
 * \Modules\ExportProdotti\Support\ExportRunner::run()} and land on the same
 * disk layout, so the prune command needs no special casing for either origin
 * — these tests exercise it against records shaped like each one.
 */
class PruneExportFilesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function completedRecordWithFile(string $path): ExportRecord
    {
        Storage::disk('local')->put($path, 'stub content');

        return ExportRecord::factory()->completed()->create(['stored_path' => $path]);
    }

    public function test_it_prunes_old_files_regardless_of_whether_the_export_ran_inline_or_queued(): void
    {
        // "queued"-shaped: a large export that went through the job.
        $queuedRecord = $this->completedRecordWithFile('exports/old-queued.xlsx');
        $queuedRecord->forceFill(['created_at' => now()->subDays(10)])->save();

        // "inline"-shaped: a small export run synchronously in the request.
        $inlineRecord = $this->completedRecordWithFile('exports/old-inline.csv');
        $inlineRecord->forceFill(['created_at' => now()->subDays(10)])->save();

        $this->artisan('exportprodotti:prune-files')->assertSuccessful();

        $queuedRecord->refresh();
        $inlineRecord->refresh();

        $this->assertFalse(Storage::disk('local')->exists('exports/old-queued.xlsx'));
        $this->assertFalse(Storage::disk('local')->exists('exports/old-inline.csv'));
        $this->assertNull($queuedRecord->stored_path);
        $this->assertNull($inlineRecord->stored_path);

        // The report rows themselves are kept — only the file is removed.
        $this->assertSame(2, ExportRecord::count());
    }

    public function test_it_leaves_recent_files_alone(): void
    {
        $recent = $this->completedRecordWithFile('exports/recent.xlsx');

        $this->artisan('exportprodotti:prune-files')->assertSuccessful();

        $recent->refresh();

        $this->assertTrue(Storage::disk('local')->exists('exports/recent.xlsx'));
        $this->assertSame('exports/recent.xlsx', $recent->stored_path);
    }
}
