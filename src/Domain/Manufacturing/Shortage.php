<?php

declare(strict_types=1);

namespace App\Domain\Manufacturing;

final readonly class Shortage
{
    public function __construct(public int $variantId, public float $required, public float $available)
    {
    }

    public function missing(): float
    {
        return round($this->required - $this->available, 4);
    }
}
