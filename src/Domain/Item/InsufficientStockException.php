<?php

declare(strict_types=1);

namespace App\Domain\Item;

final class InsufficientStockException extends \DomainException
{
    public function __construct(public readonly float $requested, public readonly float $available)
    {
        parent::__construct(sprintf('Insufficient stock: need %s, only %s available.', self::fmt($requested), self::fmt($available)));
    }

    public function shortfall(): float
    {
        return round($this->requested - $this->available, 4);
    }

    private static function fmt(float $n): string
    {
        return rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.');
    }
}
