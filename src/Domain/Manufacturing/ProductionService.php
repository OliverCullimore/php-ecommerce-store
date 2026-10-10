<?php

declare(strict_types=1);

namespace App\Domain\Manufacturing;

use App\Domain\Item\StockService;
use App\Domain\Order\OrderService;
use App\Repository\BomRepository;
use App\Repository\ProductionRepository;
use App\Repository\ResourceRepository;
use App\Repository\StockRepository;

final class ProductionService
{
    public function __construct(
        private readonly BomRepository $bom,
        private readonly StockRepository $stockRepo,
        private readonly StockService $stock,
        private readonly ProductionRepository $production,
        private readonly ResourceRepository $resources,
        private readonly OrderService $orders,
    ) {
    }

    /**
     * Consumes one BOM level, writes the output row and resource usage, then re-checks waiting orders.
     * No auto-cascading: a short sub-assembly fails with a clear message.
     *
     * @param array<int, float> $actualHours resourceId => hours; defaults to BOM hours x quantity
     * @return int batch id
     * @throws ShortageException
     */
    public function record(int $variantId, float $quantity, ?string $notes = null, array $actualHours = []): int
    {
        if ($quantity <= 0) {
            throw new \DomainException('Quantity must be greater than zero.');
        }
        $lines = $this->bom->components($variantId);
        if ($lines === []) {
            throw new \DomainException('This variant has no components, so it is purchased rather than manufactured.');
        }

        $batchId = $this->production->transactional(function () use ($variantId, $quantity, $notes, $actualHours, $lines): int {
            $needs = BatchPlanner::explode($lines, $quantity);
            $shortages = BatchPlanner::shortages($needs, $this->stockRepo->balances(array_keys($needs)));
            if ($shortages !== []) {
                $labels = [];
                foreach ($shortages as $s) {
                    $labels[$s->variantId] = $this->bom->variantInfo($s->variantId)['label'];
                }
                throw new ShortageException($shortages, $labels);
            }

            $batchId = $this->production->createBatch($variantId, $quantity, $notes);
            $cost = 0.0;
            foreach ($needs as $componentId => $required) {
                $cost += $this->stock->consume($componentId, $required, $batchId, null, 'Component for batch #' . $batchId);
            }
            foreach ($this->bom->resourceLines($variantId) as $line) {
                $resource = $this->resources->find((int) $line['resource_id']);
                $hours = $actualHours[(int) $line['resource_id']] ?? (float) $line['hours_used'] * $quantity;
                if ($resource === null || $hours <= 0) {
                    continue;
                }
                $this->production->logResourceUsage($batchId, $resource->id, $hours);
                $cost += $hours * $resource->ratePerHour();
            }
            $this->stock->addProductionOutput($variantId, $quantity, round($cost / $quantity, 4), $batchId);

            return $batchId;
        });

        $this->orders->recheckVariant($variantId);

        return $batchId;
    }
}
