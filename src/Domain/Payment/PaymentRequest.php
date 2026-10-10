<?php

declare(strict_types=1);

namespace App\Domain\Payment;

final readonly class PaymentRequest
{
    public function __construct(
        public int $orderId,
        public int $amountPence,
        public string $currency,
        public string $reference,
        public string $returnUrl,
        public string $callbackUrl,
        public string $customerEmail,
    ) {
    }
}
