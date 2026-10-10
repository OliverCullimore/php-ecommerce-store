<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Item\FifoAllocator;
use App\Domain\Item\InsufficientStockException;
use App\Domain\Item\StockLot;
use PHPUnit\Framework\TestCase;

final class FifoAllocatorTest extends TestCase
{
    public function testDrawsFromOldestLotFirst(): void
    {
        $a = (new FifoAllocator())->allocate([new StockLot(1, 10, 2.0), new StockLot(2, 10, 3.0)], 4);
        $this->assertCount(1, $a);
        $this->assertSame(1, $a[0]->sourceStockId);
        $this->assertSame(4.0, $a[0]->quantity);
    }

    public function testSplitsAcrossLotsWhenOneIsNotEnough(): void
    {
        $a = (new FifoAllocator())->allocate([new StockLot(1, 3, 2.0), new StockLot(2, 10, 3.0)], 5);
        $this->assertCount(2, $a);
        $this->assertSame(3.0, $a[0]->quantity);
        $this->assertSame(2.0, $a[1]->quantity);
        $this->assertSame(12.0, FifoAllocator::totalCost($a));
    }

    public function testSkipsExhaustedLots(): void
    {
        $a = (new FifoAllocator())->allocate([new StockLot(1, 0, 1.0), new StockLot(2, 5, 1.5)], 2);
        $this->assertSame(2, $a[0]->sourceStockId);
    }

    public function testExactlyConsumesAllStock(): void
    {
        $a = (new FifoAllocator())->allocate([new StockLot(1, 2.5, 1.0), new StockLot(2, 2.5, 1.0)], 5);
        $this->assertCount(2, $a);
    }

    public function testFractionalQuantities(): void
    {
        $a = (new FifoAllocator())->allocate([new StockLot(1, 1000.0, 0.02)], 45.5);
        $this->assertEqualsWithDelta(0.91, FifoAllocator::totalCost($a), 0.00001);
    }

    public function testThrowsWithShortfallWhenStockInsufficient(): void
    {
        try {
            (new FifoAllocator())->allocate([new StockLot(1, 3, 1.0)], 5);
            $this->assertTrue(false, 'should have thrown');
        } catch (InsufficientStockException $e) {
            $this->assertSame(2.0, $e->shortfall());
            $this->assertSame(3.0, $e->available);
        }
    }

    public function testRejectsNonPositiveQuantity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new FifoAllocator())->allocate([], 0);
    }
}
