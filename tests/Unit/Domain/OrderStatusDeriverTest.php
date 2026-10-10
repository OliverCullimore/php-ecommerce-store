<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Order\OrderStatusDeriver;
use App\Entity\LineStatus as L;
use App\Entity\OrderStatus as S;
use PHPUnit\Framework\TestCase;

final class OrderStatusDeriverTest extends TestCase
{
    private function d(array $lines, bool $stocked = true): S
    {
        return (new OrderStatusDeriver())->derive($lines, $stocked);
    }

    public function testAllPendingStocked(): void
    {
        $this->assertSame(S::AwaitingDespatch, $this->d([L::Pending, L::Pending]));
    }

    public function testPendingWithoutStockIsUnallocated(): void
    {
        $this->assertSame(S::Unallocated, $this->d([L::Pending], false));
    }

    public function testSomeDespatchedSomePending(): void
    {
        $this->assertSame(S::PartiallyDespatched, $this->d([L::Despatched, L::Pending]));
    }

    public function testAllDespatchedIsCompleted(): void
    {
        $this->assertSame(S::Completed, $this->d([L::Despatched, L::Despatched]));
    }

    public function testCancelledLinesAreIgnoredForCompletion(): void
    {
        $this->assertSame(S::Completed, $this->d([L::Despatched, L::Cancelled, L::Cancelled]));
    }

    public function testCancelledLinesDoNotHideRemainingPending(): void
    {
        $this->assertSame(S::AwaitingDespatch, $this->d([L::Cancelled, L::Pending]));
        $this->assertSame(S::PartiallyDespatched, $this->d([L::Cancelled, L::Pending, L::Despatched]));
    }

    public function testEveryLineCancelled(): void
    {
        $this->assertSame(S::Cancelled, $this->d([L::Cancelled, L::Cancelled]));
    }

    public function testSomeReturned(): void
    {
        $this->assertSame(S::PartiallyReturned, $this->d([L::Despatched, L::Returned]));
        $this->assertSame(S::PartiallyReturned, $this->d([L::Despatched, L::Returned, L::Cancelled]));
    }

    public function testAllReturned(): void
    {
        $this->assertSame(S::Returned, $this->d([L::Returned, L::Returned]));
    }

    public function testReturnWhileOtherLinesStillPendingStaysPartiallyDespatched(): void
    {
        $this->assertSame(S::PartiallyDespatched, $this->d([L::Returned, L::Pending]));
    }

    public function testEmptyOrderIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->d([]);
    }
}
