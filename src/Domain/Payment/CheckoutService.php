<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Domain\Order\OrderService;
use App\Entity\Channel;
use App\Entity\Customer;
use App\Entity\OrderStatus;
use App\Entity\PaymentStatus;
use App\Repository\CatalogueRepository;
use App\Repository\PaymentRepository;
use App\Repository\SalesOrderRepository;

final class CheckoutService
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway,
        private readonly SalesOrderRepository $orders,
        private readonly PaymentRepository $payments,
        private readonly CatalogueRepository $catalogue,
        private readonly OrderService $orderService,
        private readonly string $baseUrl,
    ) {
    }

    /**
     * Creates the `draft` order just before the customer is sent to pay, so the order id can travel with the payment.
     *
     * @param array<int, float> $cart variantId => quantity
     */
    public function createDraftFromCart(Customer $customer, array $cart): int
    {
        if ($cart === []) {
            throw new \DomainException('Your basket is empty.');
        }

        return $this->orders->transactional(function () use ($customer, $cart): int {
            $orderId = $this->orders->create($customer->id, Channel::Storefront);
            foreach ($cart as $variantId => $qty) {
                $variant = $this->catalogue->sellableVariant($variantId) ?? throw new \DomainException('An item in your basket is no longer available.');
                if ($qty <= 0) {
                    continue;
                }
                $this->orders->addItem($orderId, $variantId, $qty, (float) $variant['sale_price']);
            }

            return $orderId;
        });
    }

    public function initiatePayment(int $orderId, string $customerEmail): PaymentInitiation
    {
        $order = $this->orders->find($orderId) ?? throw new \DomainException('Order not found.');
        if ($order->status !== OrderStatus::Draft) {
            throw new \DomainException('This order can no longer be paid.');
        }
        $amount = $this->orders->totalPence($orderId);
        if ($amount <= 0) {
            throw new \DomainException('Nothing to pay.');
        }
        $reference = sprintf('FSM%d-%s', $orderId, bin2hex(random_bytes(4)));
        $this->payments->create($orderId, $this->gateway->name(), $reference, $amount, PaymentStatus::Pending);

        return $this->gateway->initiate(new PaymentRequest(
            $orderId,
            $amount,
            'GBP',
            $reference,
            rtrim($this->baseUrl, '/') . '/checkout/return/' . $orderId,
            rtrim($this->baseUrl, '/') . '/webhook/payment',
            $customerEmail,
        ));
    }

    /**
     * Idempotent: a repeated callback for an already-settled payment changes nothing.
     *
     * @param array<string, mixed> $payload
     * @return int order id
     */
    public function handleCallback(array $payload): int
    {
        $result = $this->gateway->handleCallback($payload);
        $payment = $this->payments->findByReference($result->requestReference) ?? throw new InvalidCallbackException('Unknown payment reference.');

        if ($payment->status !== PaymentStatus::Pending) {
            return $payment->salesOrderId;
        }

        $this->orders->transactional(function () use ($payment, $result): void {
            $this->payments->settle($payment->id, $result->success ? PaymentStatus::Captured : PaymentStatus::Failed, $result->success ? $result->gatewayReference : $payment->gatewayReference, $result->raw);
            if ($result->success) {
                $this->orderService->refresh($payment->salesOrderId, true);
            } else {
                $this->orderService->failPayment($payment->salesOrderId);
            }
        });

        return $payment->salesOrderId;
    }
}
