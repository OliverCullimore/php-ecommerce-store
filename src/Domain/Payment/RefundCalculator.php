<?php

declare(strict_types=1);

namespace App\Domain\Payment;

final class RefundCalculator
{
    /** @param list<int> $refundAmounts negative pence values already issued against the payment */
    public static function remaining(int $originalPence, array $refundAmounts): int
    {
        return $originalPence + array_sum($refundAmounts);
    }

    public static function assertRefundable(int $remainingPence, int $requestedPence): void
    {
        if ($requestedPence <= 0) {
            throw new RefundException('Refund amount must be greater than zero.');
        }
        if ($requestedPence > $remainingPence) {
            throw new RefundException(sprintf('Refund exceeds the remaining refundable balance of %.2f.', $remainingPence / 100));
        }
    }
}
