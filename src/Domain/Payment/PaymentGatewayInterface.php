<?php

declare(strict_types=1);

namespace App\Domain\Payment;

interface PaymentGatewayInterface
{
    public function name(): string;

    public function initiate(PaymentRequest $request): PaymentInitiation;

    /**
     * Verifies and interprets the gateway's server-to-server callback.
     *
     * @param array<string, mixed> $payload
     * @throws InvalidCallbackException if the signature/shape is wrong
     */
    public function handleCallback(array $payload): PaymentResult;

    /** @param string $gatewayReference the reference stored on the payment being refunded */
    public function refund(string $gatewayReference, int $amountPence, string $currency): RefundResult;
}
