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
use Modules\Products\Filament\Resources\Products\Pages\EditProduct;
use Modules\Products\Models\Product;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class ExistingImagePickerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(LanguageSeeder::class);
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function productWith(string $sku, string $name): Product
    {
        $product = Product::factory()->create(['sku' => $sku]);
        $product->translations()->create(['locale' => 'it', 'name' => $name]);

        return $product;
    }

    public function test_the_picker_copies_an_existing_image_into_this_product_as_its_own_file(): void
    {
        $source = $this->productWith('SRC', 'Sorgente');
        $source->addMedia(UploadedFile::fake()->image('shared.jpg', 400, 400))->toMediaCollection('main_image');
        $sourceMedia = $source->getFirstMedia('main_image');

        $target = $this->productWith('TGT', 'Destinazione');

        Livewire::test(EditProduct::class, ['record' => $target->getKey()])
            ->callFormComponentAction('main_image', 'pick_existing_main_image', ['media_id' => $sourceMedia->id])
            ->assertHasNoErrors();

        $targetMedia = $target->fresh()->getFirstMedia('main_image');

        $this->assertNotNull($targetMedia);
        $this->assertNotSame($sourceMedia->id, $targetMedia->id);
        $this->assertNotSame($sourceMedia->getPathRelativeToRoot(), $targetMedia->getPathRelativeToRoot());
        Storage::disk('public')->assertExists($sourceMedia->getPathRelativeToRoot());
        Storage::disk('public')->assertExists($targetMedia->getPathRelativeToRoot());
    }

    public function test_the_picker_replaces_the_single_main_image(): void
    {
        $source = $this->productWith('SRC-R', 'Sorgente R');
        $source->addMedia(UploadedFile::fake()->image('new.jpg'))->toMediaCollection('main_image');

        $target = $this->productWith('TGT-R', 'Destinazione R');
        $target->addMedia(UploadedFile::fake()->image('old.jpg'))->toMediaCollection('main_image');

        Livewire::test(EditProduct::class, ['record' => $target->getKey()])
            ->callFormComponentAction('main_image', 'pick_existing_main_image', [
                'media_id' => $source->getFirstMedia('main_image')->id,
            ]);

        $media = $target->fresh()->getMedia('main_image');
        $this->assertCount(1, $media);
        $this->assertSame('new', $media->first()->name);
    }

    public function test_the_picker_appends_to_the_gallery(): void
    {
        $source = $this->productWith('SRC2', 'Sorgente 2');
        $source->addMedia(UploadedFile::fake()->image('extra.jpg'))->toMediaCollection('gallery');

        $target = $this->productWith('TGT2', 'Destinazione 2');
        $target->addMedia(UploadedFile::fake()->image('own.jpg'))->toMediaCollection('gallery');

        Livewire::test(EditProduct::class, ['record' => $target->getKey()])
            ->callFormComponentAction('gallery', 'pick_existing_gallery', [
                'media_id' => $source->getFirstMedia('gallery')->id,
            ]);

        $this->assertCount(2, $target->fresh()->getMedia('gallery'));
    }

    public function test_on_the_create_form_the_picker_asks_to_save_first(): void
    {
        $source = $this->productWith('SRC3', 'Sorgente 3');
        $source->addMedia(UploadedFile::fake()->image('x.jpg'))->toMediaCollection('main_image');

        Livewire::test(CreateProduct::class)
            ->callFormComponentAction('main_image', 'pick_existing_main_image', [
                'media_id' => $source->getFirstMedia('main_image')->id,
            ])
            ->assertNotified(__('pim.notification.pick_existing_needs_save'));

        // nothing copied
        $this->assertSame(1, Media::count());
    }
}
