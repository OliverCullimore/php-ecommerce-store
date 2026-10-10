<?php

declare(strict_types=1);

namespace App\Domain\Item;

use App\Entity\EntryType;
use App\Repository\StockRepository;

/** Writes to the append-only ledger. Call inside a transaction when consuming. */
final class StockService
{
    public function __construct(private readonly StockRepository $stock, private readonly FifoAllocator $allocator)
    {
    }

    public function balance(int $variantId): float
    {
        return $this->stock->balance($variantId);
    }

    /**
     * FIFO-consumes stock, splitting across lots if needed.
     *
     * @return float total cost of the consumed lots
     * @throws InsufficientStockException
     */
    public function consume(int $variantId, float $quantity, ?int $batchId = null, ?int $salesOrderItemId = null, ?string $notes = null): float
    {
        $allocations = $this->allocator->allocate($this->stock->openLots($variantId), $quantity);
        foreach ($allocations as $a) {
            $this->stock->record($variantId, EntryType::Consumption, $a->quantity, null, $a->sourceStockId, null, $batchId, $salesOrderItemId, $notes);
        }

        return FifoAllocator::totalCost($allocations);
    }

    public function addReceipt(int $variantId, float $quantity, float $unitCost, int $purchaseOrderItemId): int
    {
        return $this->stock->record($variantId, EntryType::Receipt, $quantity, $unitCost, null, $purchaseOrderItemId);
    }

    public function addProductionOutput(int $variantId, float $quantity, float $unitCost, int $batchId): int
    {
        return $this->stock->record($variantId, EntryType::ProductionOutput, $quantity, $unitCost, null, null, $batchId);
    }

    public function addReturn(int $variantId, float $quantity, float $unitCost, int $salesOrderItemId): int
    {
        return $this->stock->record($variantId, EntryType::ReturnedStock, $quantity, $unitCost, null, null, null, $salesOrderItemId);
    }
}
