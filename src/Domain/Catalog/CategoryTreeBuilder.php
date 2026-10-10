<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Entity\Category;

final class CategoryTreeBuilder
{
    /**
     * @param list<Category> $categories
     * @return list<CategoryNode> roots, siblings ordered by sort_order then name
     */
    public function build(array $categories): array
    {
        $byParent = [];
        foreach ($categories as $c) {
            $byParent[$c->parentId ?? 0][] = $c;
        }
        $make = function (int $parent) use (&$make, $byParent): array {
            $siblings = $byParent[$parent] ?? [];
            usort($siblings, static fn (Category $a, Category $b): int => [$a->sortOrder, $a->name, $a->id] <=> [$b->sortOrder, $b->name, $b->id]);

            return array_map(static fn (Category $c): CategoryNode => new CategoryNode($c, $make($c->id)), $siblings);
        };

        return $make(0);
    }

    /**
     * @param list<CategoryNode> $nodes
     * @return list<array{category: Category, depth: int}>
     */
    public function flatten(array $nodes, int $depth = 0): array
    {
        $out = [];
        foreach ($nodes as $n) {
            $out[] = ['category' => $n->category, 'depth' => $depth];
            array_push($out, ...$this->flatten($n->children, $depth + 1));
        }

        return $out;
    }

    /**
     * The category itself plus every descendant.
     *
     * @param list<Category> $categories
     * @return list<int>
     */
    public function selfAndDescendantIds(array $categories, int $id): array
    {
        $children = [];
        foreach ($categories as $c) {
            if ($c->parentId !== null) {
                $children[$c->parentId][] = $c->id;
            }
        }
        $ids = [];
        $stack = [$id];
        while ($stack !== []) {
            $cur = array_pop($stack);
            if (in_array($cur, $ids, true)) {
                continue;
            }
            $ids[] = $cur;
            foreach ($children[$cur] ?? [] as $child) {
                $stack[] = $child;
            }
        }

        return $ids;
    }

    /**
     * @param list<Category> $categories
     * @return list<Category> root first
     */
    public function breadcrumb(array $categories, int $id): array
    {
        $byId = [];
        foreach ($categories as $c) {
            $byId[$c->id] = $c;
        }
        $trail = [];
        $seen = [];
        for ($cur = $byId[$id] ?? null; $cur !== null && !isset($seen[$cur->id]); $cur = $cur->parentId === null ? null : ($byId[$cur->parentId] ?? null)) {
            $seen[$cur->id] = true;
            array_unshift($trail, $cur);
        }

        return $trail;
    }
}
