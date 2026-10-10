<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Payment\RefundCalculator;
use App\Domain\Payment\RefundException;
use App\Domain\Resource\RateCalculator;
use App\Entity\ResourceType;
use App\Support\Money;
use PHPUnit\Framework\TestCase;

final class RefundAndRateTest extends TestCase
{
    public function testRemainingNetsOffExistingRefunds(): void
    {
        $this->assertSame(1500, RefundCalculator::remaining(5000, [-2000, -1500]));
    }

    public function testCannotRefundMoreThanRemaining(): void
    {
        $this->expectException(RefundException::class);
        RefundCalculator::assertRefundable(1500, 1501);
    }

    public function testCanRefundExactlyRemaining(): void
    {
        RefundCalculator::assertRefundable(1500, 1500);
        $this->assertTrue(true);
    }

    public function testRejectsZeroRefund(): void
    {
        $this->expectException(RefundException::class);
        RefundCalculator::assertRefundable(1500, 0);
    }

    public function testAssetRateIsCostOverLifetime(): void
    {
        $this->assertSame(0.06, RateCalculator::perHour(ResourceType::Asset, 300.0, 5000.0, null));
        $this->assertSame(0.0, RateCalculator::perHour(ResourceType::Asset, null, 5000.0, null));
    }

    public function testLabourRateIsHourlyRate(): void
    {
        $this->assertSame(12.5, RateCalculator::perHour(ResourceType::Labour, null, null, 12.5));
    }

    public function testMoneyRoundsToPence(): void
    {
        $this->assertSame(1999, Money::pence('19.99'));
        $this->assertSame(1999, Money::pence(19.989));
        $this->assertSame('-£5.50', Money::format(-550));
    }
}
