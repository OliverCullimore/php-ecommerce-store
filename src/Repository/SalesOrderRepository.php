<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Channel;
use App\Entity\LineStatus;
use App\Entity\OrderStatus;
use App\Entity\SalesOrder;
use App\Entity\SalesOrderItem;

final class SalesOrderRepository extends Repository
{
    /** @return list<array<string, mixed>> */
    public function list(?OrderStatus $status = null): array
    {
        $qb = $this->qb()->select('o.*', 'c.name AS customer_name', '(SELECT COALESCE(SUM(l.quantity * l.unit_price), 0) FROM sales_order_items l WHERE l.sales_order_id = o.id AND l.line_status <> \'cancelled\') AS total')
            ->from('sales_orders', 'o')->join('o', 'customers', 'c', 'c.id = o.customer_id')->orderBy('o.id', 'DESC')->setMaxResults(300);
        if ($status !== null) {
            $qb->where('o.status = :s')->setParameter('s', $status->value);
        }

        return $this->rows($qb);
    }

    public function find(int $id): ?SalesOrder
    {
        $r = $this->row($this->qb()->select('*')->from('sales_orders')->where('id = :id')->setParameter('id', $id));

        return $r === null ? null : SalesOrder::fromRow($r);
    }

    /** Row-locks the order; use inside a transaction. */
    public function findForUpdate(int $id): ?SalesOrder
    {
        $r = $this->db->fetchAssociative('SELECT * FROM sales_orders WHERE id = :id FOR UPDATE', ['id' => $id]);

        return $r === false ? null : SalesOrder::fromRow($r);
    }

    public function create(int $customerId, Channel $channel, ?string $notes = null): int
    {
        return $this->insertRow('sales_orders', ['customer_id' => $customerId, 'order_date' => date('Y-m-d H:i:s'), 'channel' => $channel->value, 'status' => OrderStatus::Draft->value, 'notes' => $notes]);
    }

    public function setStatus(int $id, OrderStatus $status): void
    {
        $this->updateRow('sales_orders', $id, ['status' => $status->value]);
    }

    public function addItem(int $orderId, int $variantId, float $qty, float $unitPrice): int
    {
        return $this->insertRow('sales_order_items', ['sales_order_id' => $orderId, 'item_variant_id' => $variantId, 'quantity' => $qty, 'unit_price' => $unitPrice, 'line_status' => LineStatus::Pending->value]);
    }

    public function deleteItem(int $itemId, int $orderId): void
    {
        $this->db->delete('sales_order_items', ['id' => $itemId, 'sales_order_id' => $orderId]);
    }

    public function findItem(int $itemId): ?SalesOrderItem
    {
        $r = $this->row($this->qb()->select('*')->from('sales_order_items')->where('id = :id')->setParameter('id', $itemId));

        return $r === null ? null : SalesOrderItem::fromRow($r);
    }

    /** @return list<SalesOrderItem> */
    public function items(int $orderId): array
    {
        return array_map(SalesOrderItem::fromRow(...), $this->rows($this->qb()->select('*')->from('sales_order_items')->where('sales_order_id = :o')->setParameter('o', $orderId)->orderBy('id')));
    }

    /** @return list<array<string, mixed>> lines with display labels and live stock-independent fields */
    public function itemsWithLabels(int $orderId): array
    {
        return $this->rows($this->qb()->select('l.*', "CONCAT(i.name, ' / ', v.name) AS label", 'v.sku', 'v.unit')->from('sales_order_items', 'l')
            ->join('l', 'item_variants', 'v', 'v.id = l.item_variant_id')->join('v', 'items', 'i', 'i.id = v.item_id')->where('l.sales_order_id = :o')->setParameter('o', $orderId)->orderBy('l.id'));
    }

    public function setLine(int $itemId, LineStatus $status, ?float $costPrice = null): void
    {
        $data = ['line_status' => $status->value];
        if ($costPrice !== null) {
            $data['cost_price'] = $costPrice;
        }
        $this->updateRow('sales_order_items', $itemId, $data);
    }

    public function totalPence(int $orderId): int
    {
        $total = array_sum(array_map(static fn (SalesOrderItem $i): int => $i->lineStatus === LineStatus::Cancelled ? 0 : $i->totalPence(), $this->items($orderId)));

        return (int) $total;
    }

    /** @return list<int> order ids in the given status with a pending line for the variant */
    public function orderIdsWithPendingVariant(OrderStatus $status, int $variantId): array
    {
        return array_map('intval', array_column($this->rows($this->qb()->select('DISTINCT o.id')->from('sales_orders', 'o')->join('o', 'sales_order_items', 'l', 'l.sales_order_id = o.id')
            ->where("o.status = :s AND l.item_variant_id = :v AND l.line_status = 'pending'")->setParameter('s', $status->value)->setParameter('v', $variantId)), 'id'));
    }

    public function stampInvoiceIssued(int $orderId): void
    {
        $this->db->executeStatement('UPDATE sales_orders SET invoice_issued_at = NOW() WHERE id = :id AND invoice_issued_at IS NULL', ['id' => $orderId]);
    }

    public function abandonStaleDrafts(\DateTimeImmutable $cutoff): int
    {
        return (int) $this->db->executeStatement(
            "UPDATE sales_orders SET status = 'abandoned' WHERE status = 'draft' AND channel = 'storefront' AND order_date < :cutoff",
            ['cutoff' => $cutoff->format('Y-m-d H:i:s')],
        );
    }

    /** @return list<array<string, mixed>> */
    public function forCustomer(int $customerId): array
    {
        return $this->rows($this->qb()->select('*')->from('sales_orders')->where('customer_id = :c')->setParameter('c', $customerId)->orderBy('id', 'DESC'));
    }
}
