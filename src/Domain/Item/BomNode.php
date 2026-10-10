<?php

declare(strict_types=1);

namespace App\Domain\Item;

final readonly class BomNode
{
    /**
     * @param list<BomComponent> $components
     * @param list<BomResourceLine> $resources
     */
    public function __construct(
        public int $variantId,
        public string $label,
        public float $unitCost,
        public array $components,
        public array $resources,
    ) {
    }

    public function isManufactured(): bool
    {
        return $this->components !== [];
    }
}
