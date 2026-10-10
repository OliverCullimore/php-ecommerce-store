<?php

declare(strict_types=1);

namespace App\Domain\Item;

use App\Domain\Order\OrderService;
use App\Entity\PurchaseOrderStatus;
use App\Entity\ResourceStatus;
use App\Repository\PurchaseOrderRepository;
use App\Repository\ResourceRepository;

final class PurchasingService
{
    public function __construct(
        private readonly PurchaseOrderRepository $pos,
        private readonly ResourceRepository $resources,
        private readonly StockService $stock,
        private readonly OrderService $orders,
    ) {
    }

    public function markOrdered(int $poId): void
    {
        $po = $this->pos->find($poId) ?? throw new \DomainException('Purchase order not found.');
        if ($po->status !== PurchaseOrderStatus::Draft) {
            throw new \DomainException('Only draft purchase orders can be placed.');
        }
        if ($this->pos->itemsWithLabels($poId) === []) {
            throw new \DomainException('Add at least one line first.');
        }
        $this->pos->setStatus($poId, PurchaseOrderStatus::Ordered);
    }

    public function cancel(int $poId): void
    {
        $po = $this->pos->find($poId) ?? throw new \DomainException('Purchase order not found.');
        if (!in_array($po->status, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Ordered], true)) {
            throw new \DomainException('This purchase order can no longer be cancelled.');
        }
        foreach ($this->pos->itemsWithLabels($poId) as $line) {
            if ($line['received_at'] !== null) {
                throw new \DomainException('Part of this order has already been received.');
            }
        }
        $this->pos->setStatus($poId, PurchaseOrderStatus::Cancelled);
    }

    /** Variant lines write a `receipt` ledger row; resource lines activate the resource, stamping cost from the line. */
    public function receiveItem(int $itemId): void
    {
        $variantId = $this->pos->transactional(function () use ($itemId): ?int {
            $item = $this->pos->findItem($itemId) ?? throw new \DomainException('Line not found.');
            $po = $this->pos->find($item->purchaseOrderId) ?? throw new \DomainException('Purchase order not found.');
            if ($po->status !== PurchaseOrderStatus::Ordered) {
                throw new \DomainException('Place the order before receiving it.');
            }
            if ($item->receivedAt !== null) {
                throw new \DomainException('Line already received.');
            }

            if ($item->variantId !== null) {
                $this->stock->addReceipt($item->variantId, $item->quantity, $item->unitCost, $item->id);
            } elseif ($item->resourceId !== null) {
                $this->resources->update($item->resourceId, ['status' => ResourceStatus::Active->value, 'purchase_cost' => round($item->unitCost * $item->quantity, 4)]);
            }
            $this->pos->markItemReceived($item->id);
            if ($this->pos->allItemsReceived($po->id)) {
                $this->pos->setStatus($po->id, PurchaseOrderStatus::Received);
            }

            return $item->variantId;
        });

        if ($variantId !== null) {
            $this->orders->recheckVariant($variantId);
        }
    }
}
