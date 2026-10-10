<?php

declare(strict_types=1);

namespace App\Domain\Item;

final readonly class StockLot
{
    public function __construct(public int $id, public float $remaining, public float $unitCost)
    {
    }
}
