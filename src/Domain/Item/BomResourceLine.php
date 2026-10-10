<?php

declare(strict_types=1);

namespace App\Domain\Item;

final readonly class BomResourceLine
{
    public function __construct(public string $label, public float $hours, public float $ratePerHour)
    {
    }

    public function cost(): float
    {
        return $this->hours * $this->ratePerHour;
    }
}
