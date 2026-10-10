<?php

declare(strict_types=1);

namespace App\Entity;

use App\Support\Money;

final readonly class Payment
{
    public function __construct(
        public int $id,
        public int $salesOrderId,
        public string $gateway,
        public ?string $gatewayReference,
        public int $amountPence,
        public string $currency,
        public PaymentStatus $status,
        public ?int $refundedPaymentId,
        public string $createdAt,
    ) {
    }

    /** @param array<string, mixed> $r */
    public static function fromRow(array $r): self
    {
        return new self(
            (int) $r['id'],
            (int) $r['sales_order_id'],
            (string) $r['gateway'],
            $r['gateway_reference'] ?? null,
            Money::pence($r['amount']),
            (string) $r['currency'],
            PaymentStatus::from((string) $r['status']),
            $r['refunded_payment_id'] === null ? null : (int) $r['refunded_payment_id'],
            (string) $r['created_at'],
        );
    }

    public function isRefund(): bool
    {
        return $this->amountPence < 0;
    }
}
