<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Resource\RateCalculator;

final readonly class Resource
{
    public function __construct(
        public int $id,
        public string $name,
        public ResourceType $type,
        public ResourceStatus $status,
        public ?float $purchaseCost,
        public ?float $expectedLifetimeHours,
        public ?float $hourlyRate,
        public ?string $notes,
    ) {
    }

    /** @param array<string, mixed> $r */
    public static function fromRow(array $r): self
    {
        $f = static fn (mixed $v): ?float => $v === null ? null : (float) $v;

        return new self(
            (int) $r['id'],
            (string) $r['name'],
            ResourceType::from((string) $r['type']),
            ResourceStatus::from((string) $r['status']),
            $f($r['purchase_cost']),
            $f($r['expected_lifetime_hours']),
            $f($r['hourly_rate']),
            $r['notes'] ?? null,
        );
    }

    public function ratePerHour(): float
    {
        return RateCalculator::perHour($this->type, $this->purchaseCost, $this->expectedLifetimeHours, $this->hourlyRate);
    }
}
