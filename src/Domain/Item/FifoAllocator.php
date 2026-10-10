<?php

declare(strict_types=1);

namespace App\Domain\Item;

final class FifoAllocator
{
    public const EPSILON = 0.00005;

    /**
     * @param list<StockLot> $lots oldest first
     * @return list<Allocation>
     */
    public function allocate(array $lots, float $quantity): array
    {
        if ($quantity <= self::EPSILON) {
            throw new \InvalidArgumentException('Quantity must be positive.');
        }

        $left = $quantity;
        $allocations = [];
        foreach ($lots as $lot) {
            if ($left <= self::EPSILON) {
                break;
            }
            if ($lot->remaining <= self::EPSILON) {
                continue;
            }
            $take = round(min($left, $lot->remaining), 4);
            $allocations[] = new Allocation($lot->id, $take, $lot->unitCost);
            $left = round($left - $take, 4);
        }

        if ($left > self::EPSILON) {
            throw new InsufficientStockException($quantity, round($quantity - $left, 4));
        }

        return $allocations;
    }

    /** @param list<Allocation> $allocations */
    public static function totalCost(array $allocations): float
    {
        return round(array_sum(array_map(static fn (Allocation $a): float => $a->cost(), $allocations)), 4);
    }
}
