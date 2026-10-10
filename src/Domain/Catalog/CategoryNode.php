<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Entity\Category;

final class CategoryNode
{
    /** @param list<CategoryNode> $children */
    public function __construct(public readonly Category $category, public array $children = [])
    {
    }
}
