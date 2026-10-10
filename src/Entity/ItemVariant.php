<?php

declare(strict_types=1);

namespace App\Entity;

final readonly class ItemVariant
{
    public function __construct(
        public int $id,
        public int $itemId,
        public string $sku,
        public string $name,
        public string $unit,
        public float $reorderThreshold,
        public bool $sellable,
        public ?float $salePrice,
        public VariantStatus $status,
        public ?string $notes,
    ) {
    }

    /** @param array<string, mixed> $r */
    public static function fromRow(array $r): self
    {
        return new self(
            (int) $r['id'],
            (int) $r['item_id'],
            (string) $r['sku'],
            (string) $r['name'],
            (string) $r['unit'],
            (float) $r['reorder_threshold'],
            (bool) $r['sellable'],
            $r['sale_price'] === null ? null : (float) $r['sale_price'],
            VariantStatus::from((string) $r['status']),
            $r['notes'] ?? null,
        );
    }
}
