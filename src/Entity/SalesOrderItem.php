<?php

declare(strict_types=1);

namespace App\Entity;

use App\Support\Money;

final readonly class SalesOrderItem
{
    public function __construct(
        public int $id,
        public int $salesOrderId,
        public int $variantId,
        public float $quantity,
        public float $unitPrice,
        public ?float $costPrice,
        public LineStatus $lineStatus,
    ) {
    }

    /** @param array<string, mixed> $r */
    public static function fromRow(array $r): self
    {
        return new self(
            (int) $r['id'],
            (int) $r['sales_order_id'],
            (int) $r['item_variant_id'],
            (float) $r['quantity'],
            (float) $r['unit_price'],
            $r['cost_price'] === null ? null : (float) $r['cost_price'],
            LineStatus::from((string) $r['line_status']),
        );
    }

    public function totalPence(): int
    {
        return Money::pence($this->quantity * $this->unitPrice);
    }
}
