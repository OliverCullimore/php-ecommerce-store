<?php

declare(strict_types=1);

namespace App\Domain\Manufacturing;

use App\Repository\BomRepository;
use App\Repository\ProductionRepository;
use App\Repository\StockRepository;

/** Feeds the admin manufacturing screen: what needs making, how many, and what's short at each level. */
final class ManufacturingPlanner
{
    public function __construct(
        private readonly ProductionRepository $production,
        private readonly StockRepository $stock,
        private readonly BomRepository $bom,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function candidates(): array
    {
        $out = [];
        foreach ($this->production->manufacturedVariants() as $v) {
            $id = (int) $v['id'];
            $stock = $this->stock->balance($id);
            $demand = $this->stock->openDemand($id);
            $suggested = BatchPlanner::suggestBatchSize($stock, (float) $v['reorder_threshold'], $demand);
            if ($suggested <= 0) {
                continue;
            }
            $needs = BatchPlanner::explode($this->bom->components($id), $suggested);
            $shortages = BatchPlanner::shortages($needs, $this->stock->balances(array_keys($needs)));
            $out[] = [
                'id' => $id,
                'label' => $v['label'],
                'sku' => $v['sku'],
                'unit' => $v['unit'],
                'stock' => $stock,
                'threshold' => (float) $v['reorder_threshold'],
                'demand' => $demand,
                'suggested' => $suggested,
                'shortages' => array_map(fn (Shortage $s): array => ['label' => $this->bom->variantInfo($s->variantId)['label'], 'missing' => $s->missing(), 'variantId' => $s->variantId], $shortages),
            ];
        }

        return $out;
    }
}
