<?php

declare(strict_types=1);

namespace App\Support;

use Psr\Http\Message\ServerRequestInterface as Request;

/** Typed access to form fields; keeps controllers free of casting noise. */
final class Input
{
    /** @return array<string, mixed> */
    public static function body(Request $request): array
    {
        $b = $request->getParsedBody();

        return is_array($b) ? $b : [];
    }

    /** @param array<string, mixed> $b */
    public static function str(array $b, string $key, string $default = ''): string
    {
        $v = $b[$key] ?? $default;

        return is_scalar($v) ? trim((string) $v) : $default;
    }

    /** @param array<string, mixed> $b */
    public static function nullableStr(array $b, string $key): ?string
    {
        $v = self::str($b, $key);

        return $v === '' ? null : $v;
    }

    /** @param array<string, mixed> $b */
    public static function int(array $b, string $key, int $default = 0): int
    {
        return isset($b[$key]) && is_numeric($b[$key]) ? (int) $b[$key] : $default;
    }

    /** @param array<string, mixed> $b */
    public static function nullableInt(array $b, string $key): ?int
    {
        $v = self::int($b, $key);

        return $v > 0 ? $v : null;
    }

    /** @param array<string, mixed> $b */
    public static function float(array $b, string $key, float $default = 0.0): float
    {
        return isset($b[$key]) && is_numeric($b[$key]) ? (float) $b[$key] : $default;
    }

    /** @param array<string, mixed> $b */
    public static function nullableFloat(array $b, string $key): ?float
    {
        return isset($b[$key]) && is_numeric($b[$key]) ? (float) $b[$key] : null;
    }

    /** @param array<string, mixed> $b */
    public static function bool(array $b, string $key): bool
    {
        return !empty($b[$key]);
    }

    /**
     * @param array<string, mixed> $b
     * @return list<int>
     */
    public static function ints(array $b, string $key): array
    {
        $v = $b[$key] ?? [];

        return is_array($v) ? array_values(array_map('intval', array_filter($v, 'is_numeric'))) : [];
    }
}
