<?php

declare(strict_types=1);

namespace App\Entity;

final readonly class PurchaseOrderItem
{
    public function __construct(
        public int $id,
        public int $purchaseOrderId,
        public ?int $variantId,
        public ?int $resourceId,
        public float $quantity,
        public float $unitCost,
        public ?string $receivedAt,
    ) {
    }

    /** @param array<string, mixed> $r */
    public static function fromRow(array $r): self
    {
        return new self(
            (int) $r['id'],
            (int) $r['purchase_order_id'],
            $r['item_variant_id'] === null ? null : (int) $r['item_variant_id'],
            $r['resource_id'] === null ? null : (int) $r['resource_id'],
            (float) $r['quantity'],
            (float) $r['unit_cost'],
            $r['received_at'] ?? null,
        );
    }
}
