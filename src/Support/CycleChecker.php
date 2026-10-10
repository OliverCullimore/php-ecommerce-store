<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Shared by the BOM (edge: variant -> component) and the category tree
 * (edge: category -> parent). Adding the edge from -> to creates a cycle
 * exactly when `to` can already reach `from`.
 */
final class CycleChecker
{
    /** @param callable(int): iterable<int> $next */
    public static function reaches(int $from, int $target, callable $next): bool
    {
        $seen = [];
        $stack = [$from];
        while ($stack !== []) {
            $id = array_pop($stack);
            if ($id === $target) {
                return true;
            }
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            foreach ($next($id) as $neighbour) {
                $stack[] = $neighbour;
            }
        }

        return false;
    }

    /** @param callable(int): iterable<int> $next */
    public static function wouldCreateCycle(int $from, int $to, callable $next): bool
    {
        return self::reaches($to, $from, $next);
    }
}
