<?php

declare(strict_types=1);

namespace App\Entity;

final readonly class PurchaseOrder
{
    public function __construct(public int $id, public int $supplierId, public string $orderDate, public PurchaseOrderStatus $status, public ?string $notes)
    {
    }

    /** @param array<string, mixed> $r */
    public static function fromRow(array $r): self
    {
        return new self((int) $r['id'], (int) $r['supplier_id'], (string) $r['order_date'], PurchaseOrderStatus::from((string) $r['status']), $r['notes'] ?? null);
    }
}
