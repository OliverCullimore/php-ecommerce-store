<?php

declare(strict_types=1);

namespace App\Domain\Item;

final readonly class BomComponent
{
    public function __construct(public float $quantity, public BomNode $node)
    {
    }
}
