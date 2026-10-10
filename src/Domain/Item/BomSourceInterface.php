<?php

declare(strict_types=1);

namespace App\Domain\Item;

interface BomSourceInterface
{
    /** @return array{label: string, unitCost: float} unitCost = latest purchase/production cost for this variant */
    public function variantInfo(int $variantId): array;

    /** @return list<array{variantId: int, quantity: float}> */
    public function components(int $variantId): array;

    /** @return list<array{label: string, hours: float, ratePerHour: float}> */
    public function resources(int $variantId): array;
}
