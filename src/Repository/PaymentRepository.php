<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Payment;
use App\Entity\PaymentStatus;
use App\Support\Money;

final class PaymentRepository extends Repository
{
    /** @param array<string, mixed>|null $raw */
    public function create(int $orderId, string $gateway, ?string $reference, int $amountPence, PaymentStatus $status, ?int $refundedPaymentId = null, ?array $raw = null): int
    {
        return $this->insertRow('payments', [
            'sales_order_id' => $orderId,
            'gateway' => $gateway,
            'gateway_reference' => $reference,
            'amount' => Money::pounds($amountPence),
            'currency' => 'GBP',
            'status' => $status->value,
            'refunded_payment_id' => $refundedPaymentId,
            'raw_response' => $raw === null ? null : json_encode($raw, JSON_THROW_ON_ERROR),
        ]);
    }

    public function find(int $id): ?Payment
    {
        $r = $this->row($this->qb()->select('*')->from('payments')->where('id = :id')->setParameter('id', $id));

        return $r === null ? null : Payment::fromRow($r);
    }

    /** Row-locks a payment; use inside a transaction to serialise refunds. */
    public function findForUpdate(int $id): ?Payment
    {
        $r = $this->db->fetchAssociative('SELECT * FROM payments WHERE id = :id FOR UPDATE', ['id' => $id]);

        return $r === false ? null : Payment::fromRow($r);
    }

    public function findByReference(string $reference): ?Payment
    {
        $r = $this->row($this->qb()->select('*')->from('payments')->where('(gateway_reference = :r OR gateway_reference LIKE :p) AND amount > 0')->setParameter('r', $reference)->setParameter('p', $reference . '|%'));

        return $r === null ? null : Payment::fromRow($r);
    }

    /** @return list<Payment> */
    public function forOrder(int $orderId): array
    {
        return array_map(Payment::fromRow(...), $this->rows($this->qb()->select('*')->from('payments')->where('sales_order_id = :o')->setParameter('o', $orderId)->orderBy('id')));
    }

    /** @param array<string, mixed> $raw */
    public function settle(int $id, PaymentStatus $status, ?string $reference, array $raw): void
    {
        $this->updateRow('payments', $id, ['status' => $status->value, 'gateway_reference' => $reference, 'raw_response' => json_encode($raw, JSON_THROW_ON_ERROR)]);
    }

    /** @return list<int> negative pence values already refunded against this payment */
    public function refundAmounts(int $paymentId): array
    {
        $rows = $this->rows($this->qb()->select('amount')->from('payments')->where('refunded_payment_id = :p')->setParameter('p', $paymentId));

        return array_map(static fn (array $r): int => Money::pence($r['amount']), $rows);
    }

    public function hasCapturedPayment(int $orderId): bool
    {
        return (int) $this->qb()->select('COUNT(*)')->from('payments')->where("sales_order_id = :o AND amount > 0 AND status = 'captured'")->setParameter('o', $orderId)->executeQuery()->fetchOne() > 0;
    }
}
