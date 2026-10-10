<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Item\BomSourceInterface;

final class InMemoryBomSource implements BomSourceInterface
{
    /** @var array<int, array{label: string, unitCost: float}> */
    private array $variants = [];
    /** @var array<int, list<array{variantId: int, quantity: float}>> */
    private array $components = [];
    /** @var array<int, list<array{label: string, hours: float, ratePerHour: float}>> */
    private array $resources = [];

    public function variant(int $id, string $label, float $unitCost = 0.0): self
    {
        $this->variants[$id] = ['label' => $label, 'unitCost' => $unitCost];

        return $this;
    }

    public function component(int $parent, int $child, float $qty): self
    {
        $this->components[$parent][] = ['variantId' => $child, 'quantity' => $qty];

        return $this;
    }

    public function resource(int $variant, string $label, float $hours, float $rate): self
    {
        $this->resources[$variant][] = ['label' => $label, 'hours' => $hours, 'ratePerHour' => $rate];

        return $this;
    }

    public function variantInfo(int $variantId): array
    {
        return $this->variants[$variantId];
    }

    public function components(int $variantId): array
    {
        return $this->components[$variantId] ?? [];
    }

    public function resources(int $variantId): array
    {
        return $this->resources[$variantId] ?? [];
    }
}
