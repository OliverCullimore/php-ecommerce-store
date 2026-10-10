<?php

declare(strict_types=1);

namespace App\Support;

final class Money
{
    public static function pence(float|int|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public static function pounds(int $pence): string
    {
        return number_format($pence / 100, 2, '.', '');
    }

    public static function format(int $pence): string
    {
        return ($pence < 0 ? '-£' : '£') . number_format(abs($pence) / 100, 2);
    }
}
