<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Item\FifoAllocator;
use App\Domain\Item\StockService;
use App\Domain\Order\OrderService;
use App\Domain\Order\OrderStatusDeriver;
use App\Domain\Payment\FakePaymentGateway;
use App\Domain\Payment\RefundException;
use App\Domain\Payment\RefundService;
use App\Entity\EntryType;
use App\Entity\OrderStatus;
use App\Entity\PaymentStatus;
use App\Repository\CustomerRepository;
use App\Repository\PaymentRepository;
use App\Repository\SalesOrderRepository;
use App\Repository\StockRepository;
use App\Repository\VariantRepository;

final class OrderLifecycleTest extends DatabaseTestCase
{
    private OrderService $service;
    private SalesOrderRepository $orders;
    private StockRepository $stock;
    private PaymentRepository $payments;
    private int $customerId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stock = new StockRepository($this->db);
        $this->orders = new SalesOrderRepository($this->db);
        $this->payments = new PaymentRepository($this->db);
        $this->service = new OrderService($this->orders, $this->stock, new StockService($this->stock, new FifoAllocator()), new OrderStatusDeriver(), $this->payments, new VariantRepository($this->db));
        $this->customerId = (new CustomerRepository($this->db))->create('Test', 'lifecycle@example.test', null, 'addr');
    }

    private function paidOrder(int $variantId, float $qty): int
    {
        $id = $this->service->createManual($this->customerId);
        $this->service->addLine($id, $variantId, $qty, null);
        $this->service->recordManualPayment($id);

        return $id;
    }

    public function testDespatchSnapshotsCostAndCompletesOrder(): void
    {
        $v = $this->makeVariant('ORD-1', 10.0);
        $this->stock->record($v, EntryType::Receipt, 10, 2.0);
        $id = $this->paidOrder($v, 3);
        $this->assertSame(OrderStatus::AwaitingDespatch, $this->orders->find($id)?->status);

        $line = $this->orders->items($id)[0];
        $this->service->despatchLine($id, $line->id);

        $this->assertSame(OrderStatus::Completed, $this->orders->find($id)?->status);
        $this->assertSame(2.0, $this->orders->findItem($line->id)?->costPrice);
        $this->assertSame(7.0, $this->stock->balance($v));
    }

    public function testShortStockMakesOrderUnallocatedUntilStockArrives(): void
    {
        $v = $this->makeVariant('ORD-2', 10.0);
        $id = $this->paidOrder($v, 5);
        $this->assertSame(OrderStatus::Unallocated, $this->orders->find($id)?->status);

        $this->stock->record($v, EntryType::Receipt, 5, 1.0);
        $this->service->recheckVariant($v);

        $this->assertSame(OrderStatus::AwaitingDespatch, $this->orders->find($id)?->status);
    }

    public function testReturnRestocksAtSnapshotCostWithoutRefunding(): void
    {
        $v = $this->makeVariant('ORD-3', 10.0);
        $this->stock->record($v, EntryType::Receipt, 2, 4.0);
        $id = $this->paidOrder($v, 2);
        $line = $this->orders->items($id)[0];
        $this->service->despatchLine($id, $line->id);
        $this->service->returnLine($id, $line->id);

        $this->assertSame(OrderStatus::Returned, $this->orders->find($id)?->status);
        $this->assertSame(2.0, $this->stock->balance($v));
        $this->assertCount(1, $this->payments->forOrder($id), 'returning must not create a refund row');
    }

    public function testCancellingEveryLineCancelsTheOrder(): void
    {
        $v = $this->makeVariant('ORD-4', 10.0);
        $id = $this->paidOrder($v, 1);
        $this->service->cancelLine($id, $this->orders->items($id)[0]->id);

        $this->assertSame(OrderStatus::Cancelled, $this->orders->find($id)?->status);
    }

    public function testRefundsAreCappedAtTheRemainingBalance(): void
    {
        $v = $this->makeVariant('ORD-5', 50.0);
        $id = $this->service->createManual($this->customerId);
        $this->service->addLine($id, $v, 1, null);
        $paymentId = $this->payments->create($id, 'fake', 'txn-1', 5000, PaymentStatus::Captured);
        $refunds = new RefundService($this->payments, new FakePaymentGateway());

        $refunds->refund($id, $paymentId, 2000);
        $refunds->refund($id, $paymentId, 3000);
        $this->expectException(RefundException::class);
        $refunds->refund($id, $paymentId, 1);
    }
}
