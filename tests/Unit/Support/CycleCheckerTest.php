<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\CycleChecker;
use PHPUnit\Framework\TestCase;

final class CycleCheckerTest extends TestCase
{
    /** @var array<int, list<int>> */
    private array $edges = [1 => [2], 2 => [3], 3 => [], 4 => []];

    private function next(): callable
    {
        return fn (int $id): array => $this->edges[$id] ?? [];
    }

    public function testSelfReferenceIsACycle(): void
    {
        $this->assertTrue(CycleChecker::wouldCreateCycle(1, 1, $this->next()));
    }

    public function testTransitiveCycleIsDetected(): void
    {
        $this->assertTrue(CycleChecker::wouldCreateCycle(3, 1, $this->next()));
    }

    public function testUnrelatedEdgeIsFine(): void
    {
        $this->assertFalse(CycleChecker::wouldCreateCycle(1, 4, $this->next()));
    }

    public function testDiamondIsNotACycle(): void
    {
        $this->edges = [1 => [2, 3], 2 => [4], 3 => [4], 4 => []];
        $this->assertFalse(CycleChecker::wouldCreateCycle(1, 4, $this->next()));
    }

    public function testCategoryReparentingUsesTheSameCheck(): void
    {
        // category 3 -> parent 2 -> parent 1. Moving 1 under 3 would loop.
        $parent = [3 => [2], 2 => [1], 1 => []];
        $up = fn (int $id): array => $parent[$id] ?? [];
        $this->assertTrue(CycleChecker::wouldCreateCycle(1, 3, $up));
        $this->assertFalse(CycleChecker::wouldCreateCycle(3, 1, $up));
    }
}
