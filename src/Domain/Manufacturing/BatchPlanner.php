<?php

declare(strict_types=1);

namespace App\Domain\Manufacturing;

use App\Domain\Item\LowStockEvaluator;

final class BatchPlanner
{
    /**
     * One BOM level only (no cascading). Duplicate component rows are summed.
     *
     * @param list<array{variantId: int, quantity: float}> $componentLines
     * @return array<int, float> variantId => quantity required
     */
    public static function explode(array $componentLines, float $batchQuantity): array
    {
        $needs = [];
        foreach ($componentLines as $line) {
            $needs[$line['variantId']] = round(($needs[$line['variantId']] ?? 0.0) + $line['quantity'] * $batchQuantity, 4);
        }

        return $needs;
    }

    /**
     * @param array<int, float> $requirements
     * @param array<int, float> $stockByVariant
     * @return list<Shortage>
     */
    public static function shortages(array $requirements, array $stockByVariant): array
    {
        $out = [];
        foreach ($requirements as $variantId => $required) {
            $available = $stockByVariant[$variantId] ?? 0.0;
            if ($required - $available > 0.00005) {
                $out[] = new Shortage($variantId, $required, $available);
            }
        }

        return $out;
    }

    /** Returns 0.0 when the variant doesn't need producing. */
    public static function suggestBatchSize(float $stock, float $reorderThreshold, float $unmetDemand): float
    {
        $demandGap = max($unmetDemand - $stock, 0.0);
        $low = LowStockEvaluator::isLow($stock, $reorderThreshold);
        if (!$low && $demandGap <= 0.0) {
            return 0.0;
        }
        $replenish = $low ? max($reorderThreshold - $stock, 0.0) : 0.0;

        return max(ceil(max($replenish, $demandGap)), 1.0);
    }
}
