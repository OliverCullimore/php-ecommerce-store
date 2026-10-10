<?php

declare(strict_types=1);

namespace App\Domain\Item;

final readonly class Allocation
{
    public function __construct(public int $sourceStockId, public float $quantity, public float $unitCost)
    {
    }

    public function cost(): float
    {
        return $this->quantity * $this->unitCost;
    }
}
