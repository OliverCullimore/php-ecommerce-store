<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Item\FifoAllocator;
use App\Domain\Item\InsufficientStockException;
use App\Domain\Item\StockService;
use App\Entity\EntryType;
use App\Repository\StockRepository;

final class StockLedgerTest extends DatabaseTestCase
{
    private StockRepository $repo;
    private StockService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new StockRepository($this->db);
        $this->service = new StockService($this->repo, new FifoAllocator());
    }

    public function testBalanceIsAdditionsMinusConsumption(): void
    {
        $v = $this->makeVariant('LEDGER-1');
        $this->repo->record($v, EntryType::Receipt, 10, 2.0);
        $this->repo->record($v, EntryType::Receipt, 5, 3.0);
        $this->service->consume($v, 4);

        $this->assertSame(11.0, $this->repo->balance($v));
    }

    public function testConsumptionSplitsAcrossLotsOldestFirst(): void
    {
        $v = $this->makeVariant('LEDGER-2');
        $first = $this->repo->record($v, EntryType::Receipt, 3, 2.0);
        $second = $this->repo->record($v, EntryType::Receipt, 10, 3.0);

        $cost = $this->service->consume($v, 5);

        $this->assertSame(12.0, $cost);
        $lots = $this->repo->openLots($v);
        $this->assertCount(1, $lots);
        $this->assertSame($second, $lots[0]->id);
        $this->assertSame(8.0, $lots[0]->remaining);
        $sources = array_column(array_filter($this->repo->ledger($v), static fn (array $r): bool => $r['entry_type'] === 'consumption'), 'source_stock_id');
        sort($sources);
        $this->assertEquals([$first, $second], $sources);
    }

    public function testCannotConsumeMoreThanOnHand(): void
    {
        $v = $this->makeVariant('LEDGER-3');
        $this->repo->record($v, EntryType::Receipt, 2, 1.0);
        $this->expectException(InsufficientStockException::class);
        $this->service->consume($v, 3);
    }

    public function testReturnedStockBecomesAFifoLotAtItsRecordedCost(): void
    {
        $v = $this->makeVariant('LEDGER-4');
        $this->repo->record($v, EntryType::Receipt, 1, 5.0);
        $this->service->consume($v, 1);
        $this->repo->record($v, EntryType::ReturnedStock, 1, 5.0);

        $this->assertSame(1.0, $this->repo->balance($v));
        $this->assertSame(5.0, $this->service->consume($v, 1));
    }
}
