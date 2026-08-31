<?php

namespace Modules\WooSync\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Pricing\Models\PriceList;
use Modules\Products\Models\Product;
use Modules\WooSync\Support\ProductPayload;
use Tests\TestCase;

class ProductPayloadTest extends TestCase
{
    use RefreshDatabase;

    private PriceList $defaultList;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LanguageSeeder::class);
        Storage::fake('public');
        $this->defaultList = PriceList::create(['name' => 'Standard', 'is_default' => true]);
    }

    private function product(array $attributes = []): Product
    {
        $product = Product::factory()->create(array_merge(['sku' => 'SKU-1'], $attributes));
        $product->translations()->create([
            'locale' => 'it',
            'name' => 'Sedia',
            'description' => 'Una sedia comoda',
        ]);

        return $product->fresh();
    }

    public function test_it_builds_the_v1_field_set_for_a_simple_product(): void
    {
        $product = $this->product(['weight' => '1.250', 'length' => '10', 'width' => '20', 'height' => null]);
        $product->prices()->create(['price_list_id' => $this->defaultList->id, 'price' => '19.90']);
        $product->addMedia(UploadedFile::fake()->image('main.jpg'))->toMediaCollection('main_image');
        $product->addMedia(UploadedFile::fake()->image('g1.jpg'))->toMediaCollection('gallery');

        $payload = ProductPayload::for($product->fresh());
        $body = $payload->build([42]);

        $this->assertSame('simple', $body['type']);
        $this->assertSame('SKU-1', $body['sku']);
        $this->assertSame('Sedia', $body['name']);
        $this->assertSame('Una sedia comoda', $body['description']);
        $this->assertSame('19.90', $body['regular_price']);
        $this->assertTrue($body['manage_stock']);
        $this->assertSame('1.250', $body['weight']);
        $this->assertSame(['length' => '10.00', 'width' => '20.00'], $body['dimensions']);
        $this->assertCount(2, $body['images']);
        $this->assertArrayHasKey('src', $body['images'][0]);
        $this->assertSame([['id' => 42]], $body['categories']);
        $this->assertSame([], $payload->warnings);
    }

    public function test_a_missing_default_list_price_is_a_warning_not_a_blocker(): void
    {
        $payload = ProductPayload::for($this->product());
        $body = $payload->build();

        $this->assertArrayNotHasKey('regular_price', $body);
        $this->assertNotSame([], $payload->warnings);
    }

    public function test_a_missing_base_name_falls_back_to_the_sku_with_a_warning(): void
    {
        $product = Product::factory()->create(['sku' => 'NO-NAME']);

        $payload = ProductPayload::for($product->fresh());
        $body = $payload->build();

        $this->assertSame('NO-NAME', $body['name']);
        $this->assertNotSame([], $payload->warnings);
    }

    public function test_images_hash_changes_with_the_image_set(): void
    {
        $product = $this->product();
        $before = ProductPayload::for($product->fresh())->imagesHash();

        $product->addMedia(UploadedFile::fake()->image('main.jpg'))->toMediaCollection('main_image');

        $this->assertNotSame($before, ProductPayload::for($product->fresh())->imagesHash());
    }
}
