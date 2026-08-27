<?php

namespace Modules\Products\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Products\Enums\ProductType;
use Modules\Products\Exceptions\CannotChangeProductType;
use Modules\Products\Models\Product;
use Tests\TestCase;

class ProductVariantsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LanguageSeeder::class);
        $this->actingAs(User::factory()->create());
    }

    public function test_parent_and_variants_relationships(): void
    {
        $parent = Product::factory()->variable()->create();
        $a = Product::factory()->variantOf($parent)->create();
        $b = Product::factory()->variantOf($parent)->create();

        $this->assertEqualsCanonicalizing(
            [$a->id, $b->id],
            $parent->variants->pluck('id')->all(),
        );
        $this->assertTrue($a->parent->is($parent));
        $this->assertNull($parent->fresh()->stock);
        $this->assertSame(ProductType::Variant, $a->type);
    }

    public function test_deleting_a_variable_cascades_to_its_variants_and_their_data(): void
    {
        $parent = Product::factory()->variable()->create();
        $variant = Product::factory()->variantOf($parent)->create();
        $variant->translations()->create(['locale' => 'it', 'name' => 'Rossa M']);

        $parent->delete();

        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_translations', 0);
    }

    public function test_type_change_is_blocked_while_variants_exist(): void
    {
        $parent = Product::factory()->variable()->create();
        Product::factory()->variantOf($parent)->create();

        $this->expectException(CannotChangeProductType::class);

        $parent->update(['type' => ProductType::Simple->value]);
    }

    public function test_type_change_is_allowed_once_variants_are_gone(): void
    {
        $parent = Product::factory()->variable()->create();
        $variant = Product::factory()->variantOf($parent)->create();
        $variant->delete();

        $parent->update(['type' => ProductType::Simple->value, 'stock' => 0]);

        $this->assertTrue($parent->fresh()->isSimple());
    }

    public function test_a_variant_requires_a_variable_parent(): void
    {
        $plain = Product::factory()->create(); // simple

        $this->expectException(CannotChangeProductType::class);

        Product::factory()->make([
            'type' => ProductType::Variant->value,
            'parent_id' => $plain->id,
        ])->save();
    }

    public function test_a_non_variant_cannot_carry_a_parent(): void
    {
        $parent = Product::factory()->variable()->create();

        $this->expectException(CannotChangeProductType::class);

        Product::factory()->create(['parent_id' => $parent->id]); // type simple + parent
    }
}
