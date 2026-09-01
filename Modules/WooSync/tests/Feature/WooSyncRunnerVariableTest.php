<?php

namespace Modules\WooSync\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Pricing\Models\PriceList;
use Modules\Products\Models\Product;
use Modules\Taxonomies\Models\Taxonomy;
use Modules\Taxonomies\Models\TaxonomyTerm;
use Modules\WooSync\Exceptions\WooSyncException;
use Modules\WooSync\Models\WooSyncProductLink;
use Modules\WooSync\Models\WooSyncRun;
use Modules\WooSync\Support\WooSyncRunner;
use Modules\WooSync\Tests\Support\FakeWooClient;
use Tests\TestCase;

class WooSyncRunnerVariableTest extends TestCase
{
    use RefreshDatabase;

    private Taxonomy $colore;

    private TaxonomyTerm $rosso;

    private TaxonomyTerm $blu;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LanguageSeeder::class);
        PriceList::create(['name' => 'Standard', 'is_default' => true]);
        config(['woosync.request_delay_ms' => 0]);
        Storage::fake('public');

        $this->colore = Taxonomy::create(['slug' => 'colore']);
        $this->colore->translations()->create(['locale' => 'it', 'name' => 'Colore']);
        $this->rosso = $this->term($this->colore, 'Rosso');
        $this->blu = $this->term($this->colore, 'Blu');
    }

    private function term(Taxonomy $taxonomy, string $name): TaxonomyTerm
    {
        $term = TaxonomyTerm::create(['taxonomy_id' => $taxonomy->id, 'slug' => Str::slug($name)]);
        $term->translations()->create(['locale' => 'it', 'name' => $name]);

        return $term->fresh();
    }

    private function variableWithVariants(string $sku, int $count = 2): Product
    {
        $parent = Product::factory()->variable()->create(['sku' => $sku]);
        $parent->translations()->create(['locale' => 'it', 'name' => 'Prodotto '.$sku]);

        $terms = [$this->rosso, $this->blu];

        for ($i = 0; $i < $count; $i++) {
            $variant = Product::factory()->variantOf($parent)->create([
                'sku' => $sku.'-V'.($i + 1),
                'stock' => 3 + $i,
            ]);
            $variant->translations()->create(['locale' => 'it', 'name' => 'Variante '.($i + 1)]);
            $variant->taxonomyTerms()->sync([$terms[$i % 2]->id]);
            $variant->prices()->create(['price_list_id' => PriceList::default()->id, 'price' => '10.00']);
        }

        return $parent->fresh();
    }

    private function makeRun(array $productIds): WooSyncRun
    {
        return WooSyncRun::create([
            'trigger' => 'bulk',
            'status' => 'pending',
            'product_ids' => $productIds,
            'total' => count($productIds),
        ]);
    }

    public function test_first_sync_creates_the_parent_and_every_variation(): void
    {
        $parent = $this->variableWithVariants('VARB-1');
        $client = new FakeWooClient;

        (new WooSyncRunner($client))->run($run = $this->makeRun([$parent->id]));
        $run->refresh();

        $this->assertSame('completed', $run->status);
        $this->assertSame(1, $run->created_count);
        $this->assertSame('created', $run->items[0]['result']);
        $this->assertContains('createProduct', $client->calls);
        $this->assertSame('variable', $client->createPayloads[0]['type']);
        $this->assertCount(1, $client->createPayloads[0]['attributes']);
        $this->assertTrue($client->createPayloads[0]['attributes'][0]['variation']);
        $this->assertEqualsCanonicalizing(['Rosso', 'Blu'], $client->createPayloads[0]['attributes'][0]['options']);

        $this->assertCount(2, $client->createVariationPayloads);
        foreach ($client->createVariationPayloads as $entry) {
            $this->assertTrue($entry['payload']['manage_stock']);
            $this->assertArrayHasKey('stock_quantity', $entry['payload']);
            $this->assertCount(1, $entry['payload']['attributes']);
        }

        $parentLink = WooSyncProductLink::firstWhere('product_id', $parent->id);
        $this->assertNotNull($parentLink->woocommerce_id);

        foreach ($parent->variants as $variant) {
            $this->assertNotNull(WooSyncProductLink::firstWhere('product_id', $variant->id)?->woocommerce_id);
        }
    }

    public function test_second_sync_updates_without_recreating_and_without_touching_stock(): void
    {
        $parent = $this->variableWithVariants('VARB-2');
        $client = new FakeWooClient;

        (new WooSyncRunner($client))->run($this->makeRun([$parent->id]));
        $client->calls = [];

        (new WooSyncRunner($client))->run($run = $this->makeRun([$parent->fresh()->id]));
        $run->refresh();

        $this->assertSame('updated', $run->items[0]['result']);
        $this->assertNotContains('createProduct', $client->calls);
        $this->assertSame(
            [],
            array_values(array_filter($client->calls, static fn (string $c): bool => str_starts_with($c, 'createVariation:'))),
        );

        foreach ($client->updateVariationPayloads as $payload) {
            $this->assertArrayNotHasKey('stock_quantity', $payload);
            $this->assertArrayNotHasKey('manage_stock', $payload);
        }
    }

    public function test_a_variant_added_after_the_first_sync_is_created_on_the_next_sync_only(): void
    {
        $parent = $this->variableWithVariants('VARB-3');
        $client = new FakeWooClient;
        (new WooSyncRunner($client))->run($this->makeRun([$parent->id]));

        $newVariant = Product::factory()->variantOf($parent)->create(['sku' => 'VARB-3-V3', 'stock' => 9]);
        $newVariant->translations()->create(['locale' => 'it', 'name' => 'Variante 3']);
        $newVariant->taxonomyTerms()->sync([$this->rosso->id]);
        $newVariant->prices()->create(['price_list_id' => PriceList::default()->id, 'price' => '12.00']);

        $client->calls = [];
        (new WooSyncRunner($client))->run($run = $this->makeRun([$parent->fresh()->id]));
        $run->refresh();

        $createCalls = array_values(array_filter($client->calls, static fn (string $c): bool => str_starts_with($c, 'createVariation:')));
        $updateCalls = array_values(array_filter($client->calls, static fn (string $c): bool => str_starts_with($c, 'updateVariation:')));

        $this->assertCount(1, $createCalls);
        $this->assertCount(2, $updateCalls);
        $this->assertNotNull(WooSyncProductLink::firstWhere('product_id', $newVariant->id)?->woocommerce_id);
    }

    public function test_a_failed_variation_does_not_abort_the_others_or_the_parent_result(): void
    {
        $parent = $this->variableWithVariants('VARB-4');
        $failSku = $parent->variants->first()->sku;

        $client = new FakeWooClient;
        $client->onCreateVariation = function (int $productId, array $payload) use ($client, $failSku): array {
            if ($payload['sku'] === $failSku) {
                throw WooSyncException::unreachable('boom');
            }

            return array_merge($payload, ['id' => $client->nextId++]);
        };

        (new WooSyncRunner($client))->run($run = $this->makeRun([$parent->id]));
        $run->refresh();

        $this->assertSame('created', $run->items[0]['result']);
        $this->assertStringContainsString($failSku, (string) $run->items[0]['reason']);

        $failedVariant = $parent->variants->firstWhere('sku', $failSku);
        $this->assertNull(WooSyncProductLink::firstWhere('product_id', $failedVariant->id)?->woocommerce_id);

        $okVariant = $parent->variants->firstWhere('sku', '!=', $failSku);
        $this->assertNotNull(WooSyncProductLink::firstWhere('product_id', $okVariant->id)?->woocommerce_id);
    }

    public function test_a_variation_with_an_unchanged_image_omits_it_from_the_update(): void
    {
        $parent = $this->variableWithVariants('VARB-5', count: 1);
        $variant = $parent->variants->first();
        $variant->addMedia(UploadedFile::fake()->image('v.jpg'))->toMediaCollection('main_image');

        $client = new FakeWooClient;
        (new WooSyncRunner($client))->run($this->makeRun([$parent->id]));

        $parentLink = WooSyncProductLink::firstWhere('product_id', $parent->id);
        $variantLink = WooSyncProductLink::firstWhere('product_id', $variant->id);
        $this->assertNotNull($variantLink->images_hash);

        (new WooSyncRunner($client))->run($run = $this->makeRun([$parent->fresh()->id]));
        $run->refresh();

        $updatePayload = $client->updateVariationPayloads[$parentLink->woocommerce_id.':'.$variantLink->woocommerce_id];
        $this->assertArrayNotHasKey('image', $updatePayload);
    }

    public function test_a_variant_missing_a_default_price_still_creates_but_is_noted(): void
    {
        $parent = $this->variableWithVariants('VARB-6', count: 1);
        $parent->variants->first()->prices()->delete();

        $client = new FakeWooClient;
        (new WooSyncRunner($client))->run($run = $this->makeRun([$parent->id]));
        $run->refresh();

        $this->assertSame('created', $run->items[0]['result']);
        $this->assertNotNull($run->items[0]['reason']);
    }

    public function test_a_variant_missing_a_term_for_an_attribute_is_excluded_and_noted(): void
    {
        $parent = Product::factory()->variable()->create(['sku' => 'VARB-7']);
        $parent->translations()->create(['locale' => 'it', 'name' => 'P7']);

        $v1 = Product::factory()->variantOf($parent)->create(['sku' => 'VARB-7-V1', 'stock' => 1]);
        $v1->translations()->create(['locale' => 'it', 'name' => 'V1']);
        $v1->taxonomyTerms()->sync([$this->rosso->id]);
        $v1->prices()->create(['price_list_id' => PriceList::default()->id, 'price' => '5.00']);

        $v2 = Product::factory()->variantOf($parent)->create(['sku' => 'VARB-7-V2', 'stock' => 1]);
        $v2->translations()->create(['locale' => 'it', 'name' => 'V2']);
        // No taxonomy term at all: v2 is missing the "Colore" axis entirely.
        $v2->prices()->create(['price_list_id' => PriceList::default()->id, 'price' => '5.00']);

        $client = new FakeWooClient;
        (new WooSyncRunner($client))->run($run = $this->makeRun([$parent->id]));
        $run->refresh();

        $this->assertStringContainsString('VARB-7-V2', (string) $run->items[0]['reason']);
        $this->assertSame(['Rosso'], $client->createPayloads[0]['attributes'][0]['options']);
    }

    public function test_a_rate_limit_during_a_variation_sync_stops_the_whole_run(): void
    {
        $parent = $this->variableWithVariants('VARB-8');
        $client = new FakeWooClient;
        $client->onCreateVariation = function (): array {
            throw WooSyncException::rateLimited(5);
        };

        (new WooSyncRunner($client))->run($run = $this->makeRun([$parent->id]));
        $run->refresh();

        $this->assertSame('failed', $run->status);
        $this->assertNotNull($run->error_message);
    }
}
