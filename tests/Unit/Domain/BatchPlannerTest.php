<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Item\LowStockEvaluator;
use App\Domain\Manufacturing\BatchPlanner;
use App\Domain\Manufacturing\ShortageException;
use PHPUnit\Framework\TestCase;

final class BatchPlannerTest extends TestCase
{
    public function testExplodeSumsDuplicateComponents(): void
    {
        $needs = BatchPlanner::explode([['variantId' => 1, 'quantity' => 4.0], ['variantId' => 2, 'quantity' => 1.0], ['variantId' => 1, 'quantity' => 2.0]], 5);
        $this->assertSame([1 => 30.0, 2 => 5.0], $needs);
    }

    public function testShortagesListEveryShortComponent(): void
    {
        $s = BatchPlanner::shortages([1 => 20.0, 2 => 5.0, 3 => 1.0], [1 => 12.0, 2 => 5.0]);
        $this->assertCount(2, $s);
        $this->assertSame(8.0, $s[0]->missing());
        $this->assertSame(3, $s[1]->variantId);
    }

    public function testShortageMessageNamesComponents(): void
    {
        $e = new ShortageException(BatchPlanner::shortages([1 => 4.0], [1 => 1.0]), [1 => 'Painted Leg']);
        $this->assertStringContainsString('Painted Leg: need 4, have 1', $e->getMessage());
    }

    public function testSuggestionWhenAtThreshold(): void
    {
        $this->assertSame(1.0, BatchPlanner::suggestBatchSize(5, 5, 0));
    }

    public function testSuggestionRestocksToThreshold(): void
    {
        $this->assertSame(8.0, BatchPlanner::suggestBatchSize(2, 10, 0));
    }

    public function testSuggestionCoversUnmetDemand(): void
    {
        $this->assertSame(7.0, BatchPlanner::suggestBatchSize(3, 0, 10));
    }

    public function testNoSuggestionWhenHealthy(): void
    {
        $this->assertSame(0.0, BatchPlanner::suggestBatchSize(20, 10, 5));
    }

    public function testLowStockIgnoresZeroThreshold(): void
    {
        $this->assertFalse(LowStockEvaluator::isLow(0, 0));
        $this->assertTrue(LowStockEvaluator::isLow(2, 2));
    }
}
