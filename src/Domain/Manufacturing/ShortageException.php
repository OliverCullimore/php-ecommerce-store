<?php

declare(strict_types=1);

namespace App\Domain\Manufacturing;

final class ShortageException extends \DomainException
{
    /**
     * @param list<Shortage> $shortages
     * @param array<int, string> $labels
     */
    public function __construct(public readonly array $shortages, array $labels = [])
    {
        $parts = array_map(
            static fn (Shortage $s): string => sprintf('%s: need %s, have %s', $labels[$s->variantId] ?? '#' . $s->variantId, $s->required, $s->available),
            $shortages,
        );
        parent::__construct('Cannot produce, short of ' . implode('; ', $parts) . '. Produce or buy the missing components first.');
    }
}
