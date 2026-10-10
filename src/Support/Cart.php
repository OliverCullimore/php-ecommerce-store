<?php

declare(strict_types=1);

namespace App\Support;

/** Session cart: variantId => quantity. Prices are looked up at render/checkout time, never trusted from the client. */
final class Cart
{
    /** @return array<int, float> */
    public static function all(): array
    {
        /** @var array<int, float> $cart */
        $cart = $_SESSION['cart'] ?? [];

        return $cart;
    }

    public static function add(int $variantId, float $qty): void
    {
        $_SESSION['cart'][$variantId] = round((self::all()[$variantId] ?? 0.0) + $qty, 4);
    }

    public static function set(int $variantId, float $qty): void
    {
        if ($qty <= 0) {
            unset($_SESSION['cart'][$variantId]);

            return;
        }
        $_SESSION['cart'][$variantId] = $qty;
    }

    public static function clear(): void
    {
        unset($_SESSION['cart']);
    }

    public static function count(): int
    {
        return (int) array_sum(self::all());
    }
}
