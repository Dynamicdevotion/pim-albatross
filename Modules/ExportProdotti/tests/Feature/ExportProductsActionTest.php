<?php

namespace Modules\ExportProdotti\Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\ExportProdotti\Enums\ExportColumn;
use Modules\ExportProdotti\Filament\Resources\ExportRecords\ExportRecordResource;
use Modules\ExportProdotti\Jobs\RunProductExport;
use Modules\ExportProdotti\Models\ExportRecord;
use Modules\ExportProdotti\Support\ExportRunner;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Pricing\Models\PriceList;
use Modules\Products\Filament\Resources\Products\Pages\ListProducts;
use Modules\Products\Models\Product;
use RuntimeException;
use Tests\TestCase;

class ExportProductsActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LanguageSeeder::class);
        PriceList::create(['name' => 'Standard', 'is_default' => true]);
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Storage::fake('local');
        Storage::fake('public');
    }

    private function product(string $sku, string $name = 'P'): Product
    {
        $product = Product::factory()->create(['sku' => $sku]);
        $product->translations()->create(['locale' => 'it', 'name' => $name]);

        return $product;
    }

    public function test_a_small_export_is_streamed_back_inline_and_still_leaves_a_report_record(): void
    {
        $this->product('A', 'Alpha');
        $this->product('B', 'Beta');

        Queue::fake();

        Livewire::test(ListProducts::class)
            ->callAction('export', ['format' => 'csv', 'columns' => ['sku', 'name']])
            ->assertFileDownloaded();

        Queue::assertNothingPushed();

        $record = ExportRecord::query()->sole();

        $this->assertSame('completed', $record->status);
        $this->assertSame('csv', $record->format);
        $this->assertSame(2, $record->total_rows);
        $this->assertSame(2, $record->row_count);
        $this->assertNotNull($record->stored_path);
        $this->assertTrue(Storage::disk('local')->exists($record->stored_path));
        $this->assertNotNull($record->finished_at);
    }

    public function test_a_small_xlsx_export_is_streamed_back_inline_and_still_leaves_a_report_record(): void
    {
        $this->product('A', 'Alpha');

        Livewire::test(ListProducts::class)
            ->callAction('export', ['format' => 'xlsx', 'columns' => ['sku', 'name']])
            ->assertFileDownloaded();

        $record = ExportRecord::query()->sole();

        $this->assertSame('completed', $record->status);
        $this->assertSame('xlsx', $record->format);
        $this->assertNotNull($record->stored_path);
        $this->assertTrue(Storage::disk('local')->exists($record->stored_path));
    }

    public function test_a_failed_inline_export_redirects_to_the_report_instead_of_downloading(): void
    {
        $this->product('A', 'Alpha');

        $this->app->bind(ExportRunner::class, fn () => new class extends ExportRunner
        {
            public function write(Builder $query, array $columns, string $format, string $absolutePath): int
            {
                throw new RuntimeException('boom');
            }
        });

        $component = Livewire::test(ListProducts::class)
            ->callAction('export', ['format' => 'csv', 'columns' => ['sku', 'name']]);

        $record = ExportRecord::query()->sole();

        $this->assertSame('failed', $record->status);
        $this->assertNotNull($record->error_message);
        $this->assertNull($record->stored_path);

        $component->assertRedirect(ExportRecordResource::getUrl('view', ['record' => $record]));
    }

    public function test_a_large_export_is_queued_and_redirects_to_its_report(): void
    {
        config()->set('exportprodotti.inline_max_rows', 1);

        $this->product('A', 'Alpha');
        $this->product('B', 'Beta');

        Queue::fake();

        $component = Livewire::test(ListProducts::class)
            ->callAction('export', ['format' => 'xlsx', 'columns' => ['sku', 'name']]);

        $record = ExportRecord::query()->sole();

        $this->assertSame('pending', $record->status);
        $this->assertSame('xlsx', $record->format);
        $this->assertSame(2, $record->total_rows);
        $this->assertSame((int) auth()->id(), (int) $record->user_id);
        $this->assertContains('sku', $record->columns);
        $this->assertContains('name', $record->columns);
        // stored in the canonical enum order, never the order they were ticked
        $this->assertSame(ExportColumn::ordered($record->columns), $record->columns);

        Queue::assertPushed(
            RunProductExport::class,
            fn (RunProductExport $job): bool => $job->record->is($record),
        );

        $component->assertRedirect(ExportRecordResource::getUrl('view', ['record' => $record]));
    }

    public function test_default_columns_come_from_the_visible_list_columns(): void
    {
        $page = Livewire::test(ListProducts::class)->instance();

        $defaults = $page->defaultExportColumns();

        // sku / name / status ship visible; the dimension columns ship hidden;
        // description / price / gallery have no list column at all.
        $this->assertContains('sku', $defaults);
        $this->assertContains('name', $defaults);
        $this->assertContains('status', $defaults);
        $this->assertNotContains('weight', $defaults);
        $this->assertNotContains('description', $defaults);
    }
}
