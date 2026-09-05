<?php

namespace Modules\ImportGestionali\Tests\Feature;

use Modules\ImportGestionali\Support\VariantImportPlan;
use Tests\TestCase;

/**
 * Pass 1 of the variant-aware import: pure classification, no database.
 */
class VariantImportPlanTest extends TestCase
{
    /**
     * @param  array<int, array<string, string>>  $rows
     */
    private function plan(array $rows): VariantImportPlan
    {
        return VariantImportPlan::build($rows);
    }

    public function test_classifies_simple_container_and_variant_regardless_of_row_order(): void
    {
        // The variant rows sit ABOVE the row that defines their parent, and one
        // links with a different letter case — neither must matter.
        $plan = $this->plan([
            2 => ['sku' => 'RING-18', 'parent_sku' => 'RING'],
            3 => ['sku' => 'PLAIN', 'parent_sku' => ''],
            4 => ['sku' => 'RING', 'parent_sku' => ''],
            5 => ['sku' => 'RING-20', 'parent_sku' => 'ring'],
        ]);

        $this->assertSame(VariantImportPlan::VARIANT, $plan->classification[2]);
        $this->assertSame(VariantImportPlan::SIMPLE, $plan->classification[3]);
        $this->assertSame(VariantImportPlan::CONTAINER, $plan->classification[4]);
        $this->assertSame(VariantImportPlan::VARIANT, $plan->classification[5]);

        $this->assertSame([3, 4], $plan->topLevelLines);
        $this->assertSame([2, 5], $plan->variantLines);
        $this->assertSame('ring', $plan->parentKeyByLine[5]);
        $this->assertSame([], $plan->impliedParents());
    }

    public function test_a_parent_with_no_row_of_its_own_is_reported_as_implied(): void
    {
        $plan = $this->plan([
            2 => ['sku' => 'BR-S', 'parent_sku' => 'BR'],
            3 => ['sku' => 'BR-M', 'parent_sku' => 'BR'],
        ]);

        $this->assertSame(['br' => 'BR'], $plan->impliedParents());
        $this->assertSame([], $plan->topLevelLines);
        $this->assertSame([2, 3], $plan->variantLines);
    }

    public function test_a_second_top_level_row_for_the_same_sku_is_flagged_as_a_duplicate(): void
    {
        $plan = $this->plan([
            2 => ['sku' => 'DUP', 'parent_sku' => ''],
            3 => ['sku' => 'OTHER', 'parent_sku' => ''],
            4 => ['sku' => 'DUP', 'parent_sku' => ''],
        ]);

        $this->assertSame([4 => 2], $plan->duplicateTopLevel);
    }
}
