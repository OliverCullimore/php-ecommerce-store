<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Domain\Item\InsufficientStockException;
use App\Domain\Item\StockService;
use App\Entity\Channel;
use App\Entity\LineStatus;
use App\Entity\OrderStatus;
use App\Entity\PaymentStatus;
use App\Entity\SalesOrder;
use App\Entity\SalesOrderItem;
use App\Repository\PaymentRepository;
use App\Repository\SalesOrderRepository;
use App\Repository\StockRepository;
use App\Repository\VariantRepository;

final class OrderService
{
    public function __construct(
        private readonly SalesOrderRepository $orders,
        private readonly StockRepository $stockRepo,
        private readonly StockService $stock,
        private readonly OrderStatusDeriver $deriver,
        private readonly PaymentRepository $payments,
        private readonly VariantRepository $variants,
    ) {
    }

    public function createManual(int $customerId, ?string $notes = null): int
    {
        return $this->orders->create($customerId, Channel::Manual, $notes);
    }

    public function addLine(int $orderId, int $variantId, float $quantity, ?float $unitPrice): void
    {
        $order = $this->mustFind($orderId);
        if ($order->status !== OrderStatus::Draft) {
            throw new \DomainException('Lines can only be added to a draft order.');
        }
        $variant = $this->variants->find($variantId) ?? throw new \DomainException('Variant not found.');
        $price = $unitPrice ?? $variant->salePrice ?? throw new \DomainException('This variant has no sale price; enter one.');
        if ($quantity <= 0 || $price < 0) {
            throw new \DomainException('Quantity must be positive and price cannot be negative.');
        }
        $this->orders->addItem($orderId, $variantId, $quantity, $price);
    }

    public function removeLine(int $orderId, int $itemId): void
    {
        if ($this->mustFind($orderId)->status !== OrderStatus::Draft) {
            throw new \DomainException('Lines can only be removed from a draft order.');
        }
        $this->orders->deleteItem($itemId, $orderId);
    }

    /** Admin-entered orders are paid outside a gateway; this records a captured `manual` payment for the full total. */
    public function recordManualPayment(int $orderId): void
    {
        $this->orders->transactional(function () use ($orderId): void {
            $order = $this->orders->findForUpdate($orderId) ?? throw new \DomainException('Order not found.');
            if ($order->status !== OrderStatus::Draft && $order->status !== OrderStatus::PaymentPending) {
                throw new \DomainException('This order has already been paid.');
            }
            $total = $this->orders->totalPence($orderId);
            if ($total <= 0) {
                throw new \DomainException('Add at least one priced line before marking paid.');
            }
            $this->payments->create($orderId, 'manual', 'manual-' . $orderId . '-' . bin2hex(random_bytes(3)), $total, PaymentStatus::Captured);
            $this->refresh($orderId, true);
        });
    }

    public function failPayment(int $orderId): void
    {
        $order = $this->mustFind($orderId);
        if ($order->status === OrderStatus::Draft) {
            $this->orders->setStatus($orderId, OrderStatus::PaymentPending);
        }
    }

    /** Re-derives a paid order's status from its lines and current stock. $force marks a not-yet-paid order as paid. */
    public function refresh(int $orderId, bool $force = false): OrderStatus
    {
        $order = $this->mustFind($orderId);
        if (!$force && !$order->isPaid()) {
            return $order->status;
        }
        $items = $this->orders->items($orderId);
        $status = $this->deriver->derive(
            array_map(static fn (SalesOrderItem $i): LineStatus => $i->lineStatus, $items),
            $this->pendingLinesStocked($items),
        );
        $this->orders->setStatus($orderId, $status);

        return $status;
    }

    public function despatchLine(int $orderId, int $itemId): void
    {
        $affectedVariant = $this->orders->transactional(function () use ($orderId, $itemId): int {
            $order = $this->orders->findForUpdate($orderId) ?? throw new \DomainException('Order not found.');
            $item = $this->lineOf($orderId, $itemId);
            if (!$order->isPaid()) {
                throw new \DomainException('Only paid orders can be despatched.');
            }
            if ($item->lineStatus !== LineStatus::Pending) {
                throw new \DomainException('Only pending lines can be despatched.');
            }
            try {
                $totalCost = $this->stock->consume($item->variantId, $item->quantity, null, $item->id, 'Despatch, order #' . $orderId);
            } catch (InsufficientStockException $e) {
                throw new \DomainException('Not enough stock to despatch this line: ' . $e->getMessage(), 0, $e);
            }
            $this->orders->setLine($item->id, LineStatus::Despatched, round($totalCost / $item->quantity, 4));
            $this->refresh($orderId);

            return $item->variantId;
        });
        $this->recheckVariant($affectedVariant, $orderId);
    }

    /** No stock effect: nothing was consumed. */
    public function cancelLine(int $orderId, int $itemId): void
    {
        $this->orders->transactional(function () use ($orderId, $itemId): void {
            $this->orders->findForUpdate($orderId) ?? throw new \DomainException('Order not found.');
            $item = $this->lineOf($orderId, $itemId);
            if ($item->lineStatus !== LineStatus::Pending) {
                throw new \DomainException('Only pending lines can be cancelled; return a despatched line instead.');
            }
            $this->orders->setLine($item->id, LineStatus::Cancelled);
            $this->refresh($orderId);
        });
    }

    /** Writes a `return` ledger row at the cost snapshotted on the line. Never refunds. */
    public function returnLine(int $orderId, int $itemId): void
    {
        $variantId = $this->orders->transactional(function () use ($orderId, $itemId): int {
            $this->orders->findForUpdate($orderId) ?? throw new \DomainException('Order not found.');
            $item = $this->lineOf($orderId, $itemId);
            if ($item->lineStatus !== LineStatus::Despatched) {
                throw new \DomainException('Only despatched lines can be returned.');
            }
            $this->stock->addReturn($item->variantId, $item->quantity, $item->costPrice ?? 0.0, $item->id);
            $this->orders->setLine($item->id, LineStatus::Returned);
            $this->refresh($orderId);

            return $item->variantId;
        });
        $this->recheckVariant($variantId, $orderId);
    }

    /**
     * Stock for a variant changed (receipt, production, despatch, return): refresh paid orders waiting on it so
     * unallocated <-> awaiting_despatch stays truthful. There is no reservation in v1, so each order is checked against total stock.
     */
    public function recheckVariant(int $variantId, ?int $exceptOrderId = null): void
    {
        foreach ([OrderStatus::Unallocated, OrderStatus::AwaitingDespatch] as $status) {
            foreach ($this->orders->orderIdsWithPendingVariant($status, $variantId) as $id) {
                if ($id !== $exceptOrderId) {
                    $this->refresh($id);
                }
            }
        }
    }

    public function sweepAbandoned(\DateTimeImmutable $now, int $hours = 2): int
    {
        return $this->orders->abandonStaleDrafts($now->modify("-{$hours} hours"));
    }

    /** @param list<SalesOrderItem> $items */
    private function pendingLinesStocked(array $items): bool
    {
        $need = [];
        foreach ($items as $i) {
            if ($i->lineStatus === LineStatus::Pending) {
                $need[$i->variantId] = ($need[$i->variantId] ?? 0.0) + $i->quantity;
            }
        }
        $balances = $this->stockRepo->balances(array_keys($need));
        foreach ($need as $variantId => $qty) {
            if ($qty - ($balances[$variantId] ?? 0.0) > 0.00005) {
                return false;
            }
        }

        return true;
    }

    private function mustFind(int $orderId): SalesOrder
    {
        return $this->orders->find($orderId) ?? throw new \DomainException('Order not found.');
    }

    private function lineOf(int $orderId, int $itemId): SalesOrderItem
    {
        $item = $this->orders->findItem($itemId);
        if ($item === null || $item->salesOrderId !== $orderId) {
            throw new \DomainException('Line not found on this order.');
        }

        return $item;
    }
}
