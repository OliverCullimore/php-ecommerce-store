<?php

declare(strict_types=1);

namespace App\Domain\Item;

final class LowStockEvaluator
{
    public static function isLow(float $stock, float $reorderThreshold): bool
    {
        return $reorderThreshold > 0 && $stock <= $reorderThreshold;
    }
}
