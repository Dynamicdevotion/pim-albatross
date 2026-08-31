<?php

namespace Modules\Products\Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Products\Filament\Resources\Products\Pages\CreateProduct;
use Modules\Products\Filament\Resources\Products\Pages\ListProducts;
use Modules\Products\Models\Product;
use Tests\TestCase;

class ProductMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
        $this->seed(LanguageSeeder::class);
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_main_image_collection_keeps_only_the_latest_file(): void
    {
        $product = Product::factory()->create();

        $product->addMedia(UploadedFile::fake()->image('first.jpg'))->toMediaCollection('main_image');
        $product->addMedia(UploadedFile::fake()->image('second.jpg'))->toMediaCollection('main_image');

        $media = $product->fresh()->getMedia('main_image');
        $this->assertCount(1, $media);
        $this->assertSame('second', $media->first()->name);
    }

    public function test_thumb_conversion_is_a_filled_300_square(): void
    {
        $product = Product::factory()->create();
        $product->addMedia(UploadedFile::fake()->image('tall.jpg', 600, 900))
            ->toMediaCollection('main_image');

        $media = $product->fresh()->getFirstMedia('main_image');

        $this->assertTrue($media->hasGeneratedConversion('thumb'));
        $this->assertSame(
            [300, 300],
            array_slice(getimagesize($media->getPath('thumb')), 0, 2),
        );
    }

    public function test_gallery_collection_accepts_several_images_in_order(): void
    {
        $product = Product::factory()->create();

        foreach (['alpha', 'bravo', 'charlie'] as $name) {
            $product->addMedia(UploadedFile::fake()->image("{$name}.jpg"))->toMediaCollection('gallery');
        }

        $this->assertSame(
            ['alpha', 'bravo', 'charlie'],
            $product->fresh()->getMedia('gallery')->pluck('name')->all(),
        );
    }

    public function test_a_variant_without_its_own_main_image_inherits_the_parents(): void
    {
        $parent = Product::factory()->variable()->create();
        $parent->addMedia(UploadedFile::fake()->image('parent.jpg'))->toMediaCollection('main_image');

        $variant = Product::factory()->variantOf($parent)->create();

        $this->assertNotSame('', $variant->getMainImageUrl());
        $this->assertSame($parent->getFirstMediaUrl('main_image'), $variant->getMainImageUrl());

        // its own image takes precedence once set
        $variant->addMedia(UploadedFile::fake()->image('variant.jpg'))->toMediaCollection('main_image');
        $variant = $variant->fresh();

        $this->assertSame($variant->getFirstMediaUrl('main_image'), $variant->getMainImageUrl());
        $this->assertNotSame($parent->getFirstMediaUrl('main_image'), $variant->getMainImageUrl());
    }

    public function test_a_variable_container_may_carry_its_own_main_image(): void
    {
        $variable = Product::factory()->variable()->create();
        $variable->addMedia(UploadedFile::fake()->image('cover.jpg'))->toMediaCollection('main_image');

        $this->assertNotNull($variable->getMainImageUrl());
    }

    public function test_main_image_url_is_null_when_nothing_is_set(): void
    {
        $this->assertNull(Product::factory()->create()->getMainImageUrl());
        $this->assertNull(Product::factory()->variantOf(Product::factory()->variable()->create())->create()->getMainImageUrl());
    }

    public function test_form_upload_stores_media_on_the_public_disk(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'type' => 'simple',
                'sku' => 'DISK-1',
                'status' => 'draft',
                'translations' => ['it' => ['name' => 'X']],
                'main_image' => [UploadedFile::fake()->image('a.jpg', 300, 300)],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $media = Product::where('sku', 'DISK-1')->sole()->getFirstMedia('main_image');

        $this->assertNotNull($media);
        $this->assertSame('public', $media->disk);
        Storage::disk('public')->assertExists($media->getPathRelativeToRoot());
    }

    public function test_the_form_rejects_a_non_image_file(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'type' => 'simple',
                'sku' => 'IMG-1',
                'status' => 'draft',
                'translations' => ['it' => ['name' => 'X']],
                'main_image' => UploadedFile::fake()->create('brochure.pdf', 200, 'application/pdf'),
            ])
            ->call('create')
            ->assertHasFormErrors(['main_image']);
    }

    public function test_the_form_rejects_an_image_over_five_megabytes(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'type' => 'simple',
                'sku' => 'IMG-2',
                'status' => 'draft',
                'translations' => ['it' => ['name' => 'X']],
                'main_image' => UploadedFile::fake()->image('huge.jpg')->size(6000),
            ])
            ->call('create')
            ->assertHasFormErrors(['main_image']);
    }

    public function test_every_products_list_column_is_freely_toggleable(): void
    {
        $component = Livewire::test(ListProducts::class)
            ->assertTableColumnExists('main_image')
            ->assertCanRenderTableColumn('main_image');

        // No column is locked visible: the column manager can hide any of them,
        // the image column included.
        foreach ($component->instance()->getTable()->getColumns() as $column) {
            $this->assertTrue(
                $column->isToggleable(),
                "Column [{$column->getName()}] should be toggleable.",
            );
        }
    }
}
