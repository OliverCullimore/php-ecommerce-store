<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Item\BomCostCalculator;
use App\Domain\Item\BomTreeLoader;
use PHPUnit\Framework\TestCase;

final class BomCostCalculatorTest extends TestCase
{
    public function testSpecWorkedExampleThreeLevelDiningTable(): void
    {
        $src = (new InMemoryBomSource())
            ->variant(1, 'Table Leg', 3.00)
            ->variant(2, 'Wood Stain Light', 10.00)
            ->variant(3, 'Painted Leg Light Oak', 0.0)
            ->variant(4, 'Oak Tabletop', 40.00)
            ->variant(5, 'M6 Screw', 0.05)
            ->variant(6, 'Light Oak Table', 0.0)
            ->component(3, 1, 1)->component(3, 2, 0.05)->resource(3, 'Labour', 0.1, 12.0)
            ->component(6, 3, 4)->component(6, 4, 1)->component(6, 5, 16)->resource(6, 'Assembly', 2.0, 12.0);

        $node = (new BomTreeLoader($src))->load(6);

        // leg: 3 + 0.05*10 + 1.2 = 4.70 ; table: 4*4.70 + 40 + 0.80 + 24 = 83.60
        $this->assertEqualsWithDelta(4.70, (new BomCostCalculator())->cost($node->components[0]->node), 0.00001);
        $this->assertEqualsWithDelta(83.60, (new BomCostCalculator())->cost($node), 0.00001);
    }

    public function testSpecWorkedExamplePhoneStand(): void
    {
        $src = (new InMemoryBomSource())
            ->variant(1, 'PLA Silk Red', 0.02)->variant(2, 'IPA', 0.01)->variant(3, 'Print Sheet', 5.0)->variant(4, 'Phone Stand Red')
            ->component(4, 1, 45)->component(4, 2, 5)->component(4, 3, 0.003)
            ->resource(4, 'Printer #1', 2.5, 300 / 5000)->resource(4, 'Setup', 0.1, 12.0);

        $cost = (new BomCostCalculator())->cost((new BomTreeLoader($src))->load(4));
        $this->assertEqualsWithDelta(2.315, $cost, 0.00001);
    }

    public function testPurchasedVariantCostsItsUnitCost(): void
    {
        $src = (new InMemoryBomSource())->variant(1, 'Screw', 0.07);
        $this->assertSame(0.07, (new BomCostCalculator())->cost((new BomTreeLoader($src))->load(1)));
    }

    public function testLoaderThrowsOnExistingCycle(): void
    {
        $src = (new InMemoryBomSource())->variant(1, 'A')->variant(2, 'B')->component(1, 2, 1)->component(2, 1, 1);
        $this->expectException(\App\Domain\Item\CycleDetectedException::class);
        (new BomTreeLoader($src))->load(1);
    }

    public function testWriteTimeCycleCheck(): void
    {
        $src = (new InMemoryBomSource())->variant(1, 'A')->variant(2, 'B')->variant(3, 'C')->component(1, 2, 1)->component(2, 3, 1);
        $loader = new BomTreeLoader($src);
        $this->assertTrue($loader->wouldCreateCycle(3, 1));
        $this->assertTrue($loader->wouldCreateCycle(1, 1));
        $this->assertFalse($loader->wouldCreateCycle(1, 3));
    }
}
