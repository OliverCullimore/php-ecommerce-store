<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderItem;
use App\Entity\PurchaseOrderStatus;

final class PurchaseOrderRepository extends Repository
{
    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        return $this->rows($this->qb()
            ->select('p.*', 's.name AS supplier_name', '(SELECT COALESCE(SUM(i.quantity * i.unit_cost), 0) FROM purchase_order_items i WHERE i.purchase_order_id = p.id) AS total')
            ->from('purchase_orders', 'p')->join('p', 'suppliers', 's', 's.id = p.supplier_id')->orderBy('p.id', 'DESC'));
    }

    public function find(int $id): ?PurchaseOrder
    {
        $r = $this->row($this->qb()->select('*')->from('purchase_orders')->where('id = :id')->setParameter('id', $id));

        return $r === null ? null : PurchaseOrder::fromRow($r);
    }

    public function create(int $supplierId, ?string $notes): int
    {
        return $this->insertRow('purchase_orders', ['supplier_id' => $supplierId, 'order_date' => date('Y-m-d'), 'status' => PurchaseOrderStatus::Draft->value, 'notes' => $notes]);
    }

    public function setStatus(int $id, PurchaseOrderStatus $status): void
    {
        $this->updateRow('purchase_orders', $id, ['status' => $status->value]);
    }

    public function addVariantItem(int $poId, int $variantId, float $qty, float $unitCost): int
    {
        return $this->insertRow('purchase_order_items', ['purchase_order_id' => $poId, 'item_variant_id' => $variantId, 'quantity' => $qty, 'unit_cost' => $unitCost]);
    }

    public function addResourceItem(int $poId, int $resourceId, float $unitCost): int
    {
        return $this->insertRow('purchase_order_items', ['purchase_order_id' => $poId, 'resource_id' => $resourceId, 'quantity' => 1, 'unit_cost' => $unitCost]);
    }

    public function deleteItem(int $itemId): void
    {
        $this->deleteRow('purchase_order_items', $itemId);
    }

    public function findItem(int $itemId): ?PurchaseOrderItem
    {
        $r = $this->row($this->qb()->select('*')->from('purchase_order_items')->where('id = :id')->setParameter('id', $itemId));

        return $r === null ? null : PurchaseOrderItem::fromRow($r);
    }

    /** @return list<array<string, mixed>> */
    public function itemsWithLabels(int $poId): array
    {
        return $this->rows($this->qb()
            ->select('i.*', "COALESCE(CONCAT(it.name, ' / ', v.name), r.name) AS label")
            ->from('purchase_order_items', 'i')->leftJoin('i', 'item_variants', 'v', 'v.id = i.item_variant_id')->leftJoin('v', 'items', 'it', 'it.id = v.item_id')
            ->leftJoin('i', 'resources', 'r', 'r.id = i.resource_id')->where('i.purchase_order_id = :p')->setParameter('p', $poId)->orderBy('i.id'));
    }

    public function markItemReceived(int $itemId): void
    {
        $this->updateRow('purchase_order_items', $itemId, ['received_at' => date('Y-m-d H:i:s')]);
    }

    public function allItemsReceived(int $poId): bool
    {
        return (int) $this->qb()->select('COUNT(*)')->from('purchase_order_items')->where('purchase_order_id = :p AND received_at IS NULL')->setParameter('p', $poId)->executeQuery()->fetchOne() === 0;
    }
}
