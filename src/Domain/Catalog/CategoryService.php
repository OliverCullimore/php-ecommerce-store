<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Item\CycleDetectedException;
use App\Repository\CategoryRepository;
use App\Support\CycleChecker;
use App\Support\Slugger;

final class CategoryService
{
    public function __construct(private readonly CategoryRepository $categories)
    {
    }

    public function create(string $name, ?int $parentId): int
    {
        $name = trim($name);
        if ($name === '') {
            throw new \DomainException('Name is required.');
        }

        return $this->categories->create([
            'parent_category_id' => $parentId,
            'name' => $name,
            'slug' => $this->categories->uniqueSlug(Slugger::slug($name)),
            'sort_order' => $this->categories->nextSortOrder($parentId),
            'active' => 1,
        ]);
    }

    public function rename(int $id, string $name, bool $active): void
    {
        $name = trim($name);
        if ($name === '') {
            throw new \DomainException('Name is required.');
        }
        $this->categories->update($id, ['name' => $name, 'slug' => $this->categories->uniqueSlug(Slugger::slug($name), $id), 'active' => (int) $active]);
    }

    public function move(int $id, ?int $newParentId): void
    {
        if ($newParentId !== null) {
            $map = $this->categories->parentMap();
            if (CycleChecker::wouldCreateCycle($id, $newParentId, static fn (int $c): array => isset($map[$c]) ? [$map[$c]] : [])) {
                throw new CycleDetectedException('A category cannot be moved inside itself or one of its own sub-categories.');
            }
        }
        $this->categories->update($id, ['parent_category_id' => $newParentId, 'sort_order' => $this->categories->nextSortOrder($newParentId)]);
    }

    /** @param list<int> $orderedIds sibling ids in their new order */
    public function reorder(?int $parentId, array $orderedIds): void
    {
        $map = $this->categories->parentMap();
        foreach ($orderedIds as $id) {
            if (!array_key_exists($id, $map) || $map[$id] !== $parentId) {
                throw new \DomainException('Reorder only applies to categories sharing one parent.');
            }
        }
        $this->categories->reorder($parentId, $orderedIds);
    }

    public function delete(int $id): void
    {
        if ($this->categories->hasChildrenOrItems($id)) {
            throw new \DomainException('Move or remove the sub-categories and products first.');
        }
        $this->categories->delete($id);
    }
}
