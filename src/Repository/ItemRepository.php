<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Item;
use App\Entity\ItemVariant;

final class ItemRepository extends Repository
{
    /** @return list<array<string, mixed>> items with variant counts */
    public function list(): array
    {
        return $this->rows($this->qb()
            ->select('i.*', '(SELECT COUNT(*) FROM item_variants v WHERE v.item_id = i.id) AS variant_count')
            ->from('items', 'i')->orderBy('i.name'));
    }

    public function find(int $id): ?Item
    {
        $r = $this->row($this->qb()->select('*')->from('items')->where('id = :id')->setParameter('id', $id));

        return $r === null ? null : Item::fromRow($r);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        return $this->insertRow('items', $data);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $this->updateRow('items', $id, $data);
    }

    /** @return list<ItemVariant> */
    public function variantsOf(int $itemId): array
    {
        return array_map(ItemVariant::fromRow(...), $this->rows($this->qb()->select('*')->from('item_variants')->where('item_id = :i')->setParameter('i', $itemId)->orderBy('name')));
    }

    /** @return list<int> */
    public function categoryIds(int $itemId): array
    {
        return array_map('intval', array_column($this->rows($this->qb()->select('category_id')->from('item_category_assignments')->where('item_id = :i')->setParameter('i', $itemId)), 'category_id'));
    }

    /** @param list<int> $categoryIds */
    public function setCategories(int $itemId, array $categoryIds): void
    {
        $this->db->transactional(function () use ($itemId, $categoryIds): void {
            $this->db->delete('item_category_assignments', ['item_id' => $itemId]);
            foreach (array_unique($categoryIds) as $cid) {
                $this->db->insert('item_category_assignments', ['item_id' => $itemId, 'category_id' => $cid]);
            }
        });
    }
}
