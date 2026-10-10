<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\Item\StockLot;
use App\Entity\EntryType;
use Doctrine\DBAL\ArrayParameterType;

final class StockRepository extends Repository
{
    private const ADDITIONS = "'receipt','production_output','return'";

    /**
     * Open FIFO lots, oldest first. Locks the rows so two despatches can't draw on the same lot.
     * Call inside a transaction.
     *
     * @return list<StockLot>
     */
    public function openLots(int $variantId): array
    {
        $sql = 'SELECT s.id, COALESCE(s.cost, 0) AS cost,
                       s.quantity - COALESCE((SELECT SUM(c.quantity) FROM stock c WHERE c.source_stock_id = s.id), 0) AS remaining
                FROM stock s
                WHERE s.item_variant_id = :v AND s.entry_type IN (' . self::ADDITIONS . ')
                HAVING remaining > 0
                ORDER BY s.occurred_at ASC, s.id ASC
                FOR UPDATE';

        return array_map(
            static fn (array $r): StockLot => new StockLot((int) $r['id'], (float) $r['remaining'], (float) $r['cost']),
            $this->db->fetchAllAssociative($sql, ['v' => $variantId]),
        );
    }

    public function balance(int $variantId): float
    {
        return $this->balances([$variantId])[$variantId] ?? 0.0;
    }

    /**
     * @param list<int> $variantIds
     * @return array<int, float>
     */
    public function balances(array $variantIds): array
    {
        if ($variantIds === []) {
            return [];
        }
        $rows = $this->rows($this->qb()
            ->select('item_variant_id', "SUM(CASE WHEN entry_type = 'consumption' THEN -quantity ELSE quantity END) AS balance")
            ->from('stock')->where('item_variant_id IN (:ids)')->setParameter('ids', $variantIds, ArrayParameterType::INTEGER)->groupBy('item_variant_id'));
        $out = array_fill_keys($variantIds, 0.0);
        foreach ($rows as $r) {
            $out[(int) $r['item_variant_id']] = round((float) $r['balance'], 4);
        }

        return $out;
    }

    public function record(
        int $variantId,
        EntryType $type,
        float $quantity,
        ?float $cost = null,
        ?int $sourceStockId = null,
        ?int $purchaseOrderItemId = null,
        ?int $productionBatchId = null,
        ?int $salesOrderItemId = null,
        ?string $notes = null,
    ): int {
        return $this->insertRow('stock', [
            'item_variant_id' => $variantId,
            'entry_type' => $type->value,
            'quantity' => $quantity,
            'cost' => $cost,
            'source_stock_id' => $sourceStockId,
            'purchase_order_item_id' => $purchaseOrderItemId,
            'production_batch_id' => $productionBatchId,
            'sales_order_item_id' => $salesOrderItemId,
            'occurred_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'notes' => $notes,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function ledger(int $variantId, int $limit = 200): array
    {
        return $this->rows($this->qb()->select('*')->from('stock')->where('item_variant_id = :v')->setParameter('v', $variantId)
            ->orderBy('occurred_at', 'DESC')->addOrderBy('id', 'DESC')->setMaxResults($limit));
    }

    /** @return list<array<string, mixed>> every variant with its derived balance */
    public function overview(): array
    {
        return $this->rows($this->qb()
            ->select(
                'v.id',
                'v.sku',
                'v.name',
                'v.unit',
                'v.reorder_threshold',
                'i.name AS item_name',
                "COALESCE(SUM(CASE WHEN s.entry_type = 'consumption' THEN -s.quantity ELSE s.quantity END), 0) AS balance"
            )
            ->from('item_variants', 'v')->join('v', 'items', 'i', 'i.id = v.item_id')->leftJoin('v', 'stock', 's', 's.item_variant_id = v.id')
            ->where("v.status = 'active'")->groupBy('v.id', 'v.sku', 'v.name', 'v.unit', 'v.reorder_threshold', 'i.name')->orderBy('i.name')->addOrderBy('v.name'));
    }

    /** Pending quantity on paid, not-yet-complete orders. */
    public function openDemand(int $variantId): float
    {
        return (float) $this->qb()->select('COALESCE(SUM(l.quantity), 0)')->from('sales_order_items', 'l')->join('l', 'sales_orders', 'o', 'o.id = l.sales_order_id')
            ->where("l.item_variant_id = :v AND l.line_status = 'pending' AND o.status IN ('awaiting_despatch','unallocated','partially_despatched')")
            ->setParameter('v', $variantId)->executeQuery()->fetchOne();
    }
}
