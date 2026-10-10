<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;

final class CategoryRepository extends Repository
{
    /** @return list<Category> */
    public function all(): array
    {
        return array_map(Category::fromRow(...), $this->rows($this->qb()->select('*')->from('categories')->orderBy('sort_order')->addOrderBy('name')));
    }

    public function find(int $id): ?Category
    {
        $r = $this->row($this->qb()->select('*')->from('categories')->where('id = :id')->setParameter('id', $id));

        return $r === null ? null : Category::fromRow($r);
    }

    public function findBySlug(string $slug): ?Category
    {
        $r = $this->row($this->qb()->select('*')->from('categories')->where('slug = :s')->setParameter('s', $slug));

        return $r === null ? null : Category::fromRow($r);
    }

    /** @return array<int, int|null> id => parent id */
    public function parentMap(): array
    {
        $map = [];
        foreach ($this->rows($this->qb()->select('id', 'parent_category_id')->from('categories')) as $r) {
            $map[(int) $r['id']] = $r['parent_category_id'] === null ? null : (int) $r['parent_category_id'];
        }

        return $map;
    }

    public function uniqueSlug(string $base, ?int $exceptId = null): string
    {
        $slug = $base;
        for ($n = 2; ; $n++) {
            $qb = $this->qb()->select('id')->from('categories')->where('slug = :s')->setParameter('s', $slug);
            if ($exceptId !== null) {
                $qb->andWhere('id <> :x')->setParameter('x', $exceptId);
            }
            if ($this->row($qb) === null) {
                return $slug;
            }
            $slug = $base . '-' . $n;
        }
    }

    public function nextSortOrder(?int $parentId): int
    {
        $qb = $this->qb()->select('COALESCE(MAX(sort_order), -1) + 1')->from('categories');
        $parentId === null ? $qb->where('parent_category_id IS NULL') : $qb->where('parent_category_id = :p')->setParameter('p', $parentId);

        return (int) $qb->executeQuery()->fetchOne();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        return $this->insertRow('categories', $data);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $this->updateRow('categories', $id, $data);
    }

    public function hasChildrenOrItems(int $id): bool
    {
        $kids = (int) $this->qb()->select('COUNT(*)')->from('categories')->where('parent_category_id = :i')->setParameter('i', $id)->executeQuery()->fetchOne();
        $items = (int) $this->qb()->select('COUNT(*)')->from('item_category_assignments')->where('category_id = :i')->setParameter('i', $id)->executeQuery()->fetchOne();

        return $kids + $items > 0;
    }

    public function delete(int $id): void
    {
        $this->deleteRow('categories', $id);
    }

    /** Re-numbers only the given siblings, so unrelated categories keep their sort_order. @param list<int> $orderedIds */
    public function reorder(?int $parentId, array $orderedIds): void
    {
        $this->db->transactional(function () use ($parentId, $orderedIds): void {
            foreach (array_values($orderedIds) as $position => $id) {
                $qb = $this->qb()->update('categories')->set('sort_order', ':pos')->where('id = :id')->setParameter('pos', $position)->setParameter('id', $id);
                $parentId === null ? $qb->andWhere('parent_category_id IS NULL') : $qb->andWhere('parent_category_id = :p')->setParameter('p', $parentId);
                $qb->executeStatement();
            }
        });
    }
}
