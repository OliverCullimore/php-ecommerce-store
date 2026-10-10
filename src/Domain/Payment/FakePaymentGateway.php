<?php

declare(strict_types=1);

namespace App\Domain\Payment;

/** Test double and local-dev gateway. Callback payload: reference, result (success|declined). */
final class FakePaymentGateway implements PaymentGatewayInterface
{
    /** @var list<array{reference: string, amountPence: int}> */
    public array $refunds = [];
    public bool $refundsSucceed = true;

    public function name(): string
    {
        return 'fake';
    }

    public function initiate(PaymentRequest $request): PaymentInitiation
    {
        return new PaymentInitiation($request->callbackUrl, ['reference' => $request->reference, 'result' => 'success', 'return' => $request->returnUrl], $request->reference);
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $reference = (string) ($payload['reference'] ?? '');
        if ($reference === '') {
            throw new InvalidCallbackException('Missing reference.');
        }
        $ok = ($payload['result'] ?? '') === 'success';

        return new PaymentResult($reference, $reference, $ok, $ok ? 'OK' : 'Declined', $payload);
    }

    public function refund(string $gatewayReference, int $amountPence, string $currency): RefundResult
    {
        $this->refunds[] = ['reference' => $gatewayReference, 'amountPence' => $amountPence];

        return $this->refundsSucceed
            ? new RefundResult(true, 'fake-refund-' . count($this->refunds))
            : new RefundResult(false, '', 'Fake gateway refused the refund.');
    }
}
