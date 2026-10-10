<?php

declare(strict_types=1);

namespace App\Repository;

final class ProductionRepository extends Repository
{
    public function createBatch(int $variantId, float $quantity, ?string $notes): int
    {
        return $this->insertRow('production_batches', ['item_variant_id' => $variantId, 'quantity_produced' => $quantity, 'produced_at' => date('Y-m-d H:i:s'), 'notes' => $notes]);
    }

    public function logResourceUsage(int $batchId, int $resourceId, float $hours): void
    {
        $this->insertRow('resource_usage', ['production_batch_id' => $batchId, 'resource_id' => $resourceId, 'hours_used' => $hours, 'used_at' => date('Y-m-d H:i:s')]);
    }

    /** @return list<array<string, mixed>> */
    public function recentBatches(int $limit = 20): array
    {
        return $this->rows($this->qb()->select('b.*', "CONCAT(i.name, ' / ', v.name) AS label")->from('production_batches', 'b')
            ->join('b', 'item_variants', 'v', 'v.id = b.item_variant_id')->join('v', 'items', 'i', 'i.id = v.item_id')->orderBy('b.id', 'DESC')->setMaxResults($limit));
    }

    /** @return list<array<string, mixed>> manufactured variants with threshold + label */
    public function manufacturedVariants(): array
    {
        return $this->rows($this->qb()->select('v.id', 'v.sku', 'v.unit', 'v.reorder_threshold', "CONCAT(i.name, ' / ', v.name) AS label")->from('item_variants', 'v')
            ->join('v', 'items', 'i', 'i.id = v.item_id')
            ->where("v.status = 'active' AND EXISTS (SELECT 1 FROM variant_bom_lines l WHERE l.variant_id = v.id AND l.line_type = 'component')")->orderBy('label'));
    }
}
