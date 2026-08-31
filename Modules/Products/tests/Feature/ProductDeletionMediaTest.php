<?php

namespace Modules\Products\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Products\Models\Product;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class ProductDeletionMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(LanguageSeeder::class);
    }

    public function test_deleting_a_simple_product_removes_its_media_files(): void
    {
        $product = Product::factory()->create();
        $product->addMedia(UploadedFile::fake()->image('main.jpg'))->toMediaCollection('main_image');
        $product->addMedia(UploadedFile::fake()->image('g1.jpg'))->toMediaCollection('gallery');
        $product->addMedia(UploadedFile::fake()->image('g2.jpg'))->toMediaCollection('gallery');

        $paths = $product->media->map->getPathRelativeToRoot();
        $this->assertCount(3, $paths);

        $product->delete();

        $this->assertSame(0, Media::count());
        foreach ($paths as $path) {
            Storage::disk('public')->assertMissing($path);
        }
    }

    public function test_deleting_a_variable_parent_also_cleans_up_its_variants_media(): void
    {
        $parent = Product::factory()->variable()->create();
        $parent->addMedia(UploadedFile::fake()->image('cover.jpg'))->toMediaCollection('main_image');

        $variantA = Product::factory()->variantOf($parent)->create();
        $variantA->addMedia(UploadedFile::fake()->image('va.jpg'))->toMediaCollection('main_image');
        $variantA->addMedia(UploadedFile::fake()->image('va-g.jpg'))->toMediaCollection('gallery');

        $variantB = Product::factory()->variantOf($parent)->create();
        $variantB->addMedia(UploadedFile::fake()->image('vb.jpg'))->toMediaCollection('main_image');

        $allPaths = Media::all()->map->getPathRelativeToRoot();
        $this->assertCount(4, $allPaths);

        $parent->delete();

        $this->assertSame(0, Product::count(), 'the FK cascade removes the variant rows');
        $this->assertSame(0, Media::count(), 'no orphan media rows');
        foreach ($allPaths as $path) {
            Storage::disk('public')->assertMissing($path);
        }
    }
}
