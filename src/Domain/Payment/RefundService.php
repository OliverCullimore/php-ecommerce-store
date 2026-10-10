<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Entity\PaymentStatus;
use App\Repository\PaymentRepository;

/** Refunds are manual and per payment: never triggered by cancelling or returning a line. */
final class RefundService
{
    public function __construct(private readonly PaymentRepository $payments, private readonly PaymentGatewayInterface $gateway)
    {
    }

    public function refund(int $orderId, int $paymentId, int $amountPence): int
    {
        return $this->payments->transactional(function () use ($orderId, $paymentId, $amountPence): int {
            $payment = $this->payments->findForUpdate($paymentId);
            if ($payment === null || $payment->salesOrderId !== $orderId) {
                throw new RefundException('Payment not found on this order.');
            }
            if ($payment->isRefund() || $payment->status !== PaymentStatus::Captured) {
                throw new RefundException('Only captured payments can be refunded.');
            }
            RefundCalculator::assertRefundable(
                RefundCalculator::remaining($payment->amountPence, $this->payments->refundAmounts($payment->id)),
                $amountPence,
            );

            if ($payment->gateway === 'manual') {
                $reference = 'manual-refund-' . bin2hex(random_bytes(3));
            } else {
                $result = $this->gateway->refund((string) $payment->gatewayReference, $amountPence, $payment->currency);
                if (!$result->success) {
                    throw new RefundException('Gateway refused the refund: ' . $result->message);
                }
                $reference = $result->reference;
            }

            return $this->payments->create($orderId, $payment->gateway, $reference, -$amountPence, PaymentStatus::Captured, $payment->id);
        });
    }
}
