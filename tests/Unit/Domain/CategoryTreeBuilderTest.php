<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Catalog\CategoryTreeBuilder;
use App\Entity\Category;
use PHPUnit\Framework\TestCase;

final class CategoryTreeBuilderTest extends TestCase
{
    /** @return list<Category> */
    private function cats(): array
    {
        return [
            new Category(1, null, 'Home', 'home', 1, true, null),
            new Category(2, null, 'Toys', 'toys', 0, true, null),
            new Category(3, 1, 'Decor', 'decor', 0, true, null),
            new Category(4, 3, 'Vases', 'vases', 0, true, null),
        ];
    }

    public function testRootsAreOrderedBySortOrderThenName(): void
    {
        $roots = (new CategoryTreeBuilder())->build($this->cats());
        $this->assertSame(2, $roots[0]->category->id);
        $this->assertSame(1, $roots[1]->category->id);
    }

    public function testFlattenCarriesDepth(): void
    {
        $flat = (new CategoryTreeBuilder())->flatten((new CategoryTreeBuilder())->build($this->cats()));
        $this->assertSame([2, 1, 3, 4], array_map(static fn (array $r): int => $r['category']->id, $flat));
        $this->assertSame(2, $flat[3]['depth']);
    }

    public function testBrowsingACategoryIncludesDescendants(): void
    {
        $ids = (new CategoryTreeBuilder())->selfAndDescendantIds($this->cats(), 1);
        sort($ids);
        $this->assertSame([1, 3, 4], $ids);
    }

    public function testBreadcrumbRunsRootFirst(): void
    {
        $trail = (new CategoryTreeBuilder())->breadcrumb($this->cats(), 4);
        $this->assertSame([1, 3, 4], array_map(static fn (Category $c): int => $c->id, $trail));
    }
}
