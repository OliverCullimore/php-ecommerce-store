<?php

declare(strict_types=1);

namespace App\Domain\Payment;

final readonly class PaymentResult
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public string $requestReference,
        public string $gatewayReference,
        public bool $success,
        public string $message,
        public array $raw,
    ) {
    }
}
