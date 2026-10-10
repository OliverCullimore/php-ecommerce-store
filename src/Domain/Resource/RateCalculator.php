<?php

declare(strict_types=1);

namespace App\Domain\Resource;

use App\Entity\ResourceType;

final class RateCalculator
{
    public static function perHour(ResourceType $type, ?float $purchaseCost, ?float $lifetimeHours, ?float $hourlyRate): float
    {
        if ($type === ResourceType::Labour) {
            return (float) $hourlyRate;
        }
        if ($purchaseCost === null || $lifetimeHours === null || $lifetimeHours <= 0) {
            return 0.0;
        }

        return $purchaseCost / $lifetimeHours;
    }
}
