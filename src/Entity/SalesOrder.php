<?php

declare(strict_types=1);

namespace App\Entity;

final readonly class SalesOrder
{
    public function __construct(
        public int $id,
        public int $customerId,
        public string $orderDate,
        public Channel $channel,
        public OrderStatus $status,
        public ?string $invoiceIssuedAt,
        public ?string $notes,
    ) {
    }

    /** @param array<string, mixed> $r */
    public static function fromRow(array $r): self
    {
        return new self(
            (int) $r['id'],
            (int) $r['customer_id'],
            (string) $r['order_date'],
            Channel::from((string) $r['channel']),
            OrderStatus::from((string) $r['status']),
            $r['invoice_issued_at'] ?? null,
            $r['notes'] ?? null,
        );
    }

    /** Statuses in which payment has been taken, so status is derived from lines. */
    public function isPaid(): bool
    {
        return !in_array($this->status, [OrderStatus::Draft, OrderStatus::PaymentPending, OrderStatus::Abandoned], true);
    }
}
