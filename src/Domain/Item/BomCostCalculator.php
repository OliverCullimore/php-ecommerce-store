<?php

declare(strict_types=1);

namespace App\Domain\Item;

final class BomCostCalculator
{
    public function cost(BomNode $node): float
    {
        $total = 0.0;
        if ($node->isManufactured()) {
            foreach ($node->components as $line) {
                $total += $line->quantity * $this->cost($line->node);
            }
        } else {
            $total += $node->unitCost;
        }
        foreach ($node->resources as $resource) {
            $total += $resource->cost();
        }

        return round($total, 4);
    }
}
