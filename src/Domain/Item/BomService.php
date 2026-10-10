<?php

declare(strict_types=1);

namespace App\Domain\Item;

use App\Repository\BomRepository;

final class BomService
{
    private BomTreeLoader $loader;

    public function __construct(private readonly BomRepository $bom, private readonly BomCostCalculator $calculator)
    {
        $this->loader = new BomTreeLoader($bom);
    }

    public function addComponent(int $variantId, int $componentId, float $quantity, ?string $notes = null): void
    {
        if ($quantity <= 0) {
            throw new \DomainException('Quantity must be greater than zero.');
        }
        if ($this->loader->wouldCreateCycle($variantId, $componentId)) {
            throw new CycleDetectedException('That component would make the variant part of its own recipe, directly or through a sub-assembly.');
        }
        $this->bom->addComponent($variantId, $componentId, $quantity, $notes);
    }

    public function addResource(int $variantId, int $resourceId, float $hours, ?string $notes = null): void
    {
        if ($hours <= 0) {
            throw new \DomainException('Hours must be greater than zero.');
        }
        $this->bom->addResource($variantId, $resourceId, $hours, $notes);
    }

    public function tree(int $variantId): BomNode
    {
        return $this->loader->load($variantId);
    }

    public function estimatedCost(int $variantId): float
    {
        return $this->calculator->cost($this->tree($variantId));
    }
}
