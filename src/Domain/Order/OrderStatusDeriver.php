<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Entity\LineStatus;
use App\Entity\OrderStatus;

/**
 * Derives the status of a PAID order from its lines. Pre-payment states
 * (draft, payment_pending, abandoned) are stored, never derived.
 */
final class OrderStatusDeriver
{
    /** @param list<LineStatus> $lines */
    public function derive(array $lines, bool $pendingLinesAllStocked): OrderStatus
    {
        if ($lines === []) {
            throw new \InvalidArgumentException('An order needs at least one line.');
        }

        $count = array_count_values(array_map(static fn (LineStatus $s): string => $s->value, $lines));
        $pending = $count[LineStatus::Pending->value] ?? 0;
        $despatched = $count[LineStatus::Despatched->value] ?? 0;
        $returned = $count[LineStatus::Returned->value] ?? 0;

        if ($pending + $despatched + $returned === 0) {
            return OrderStatus::Cancelled;
        }

        if ($pending > 0) {
            if ($despatched + $returned > 0) {
                return OrderStatus::PartiallyDespatched;
            }

            return $pendingLinesAllStocked ? OrderStatus::AwaitingDespatch : OrderStatus::Unallocated;
        }

        if ($returned === 0) {
            return OrderStatus::Completed;
        }

        return $despatched === 0 ? OrderStatus::Returned : OrderStatus::PartiallyReturned;
    }
}
