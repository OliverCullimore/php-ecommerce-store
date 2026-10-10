<?php

declare(strict_types=1);

namespace App\Repository;

final class AttributeRepository extends Repository
{
    /** @return list<array<string, mixed>> each attribute with a 'values' list */
    public function allWithValues(): array
    {
        $attrs = $this->rows($this->qb()->select('*')->from('attributes')->orderBy('sort_order')->addOrderBy('name'));
        $values = $this->rows($this->qb()->select('*')->from('attribute_values')->orderBy('sort_order')->addOrderBy('value'));
        foreach ($attrs as &$a) {
            $a['values'] = array_values(array_filter($values, static fn (array $v): bool => (int) $v['attribute_id'] === (int) $a['id']));
        }

        return $attrs;
    }

    /** @param array<string, mixed> $data */
    public function createAttribute(array $data): int
    {
        return $this->insertRow('attributes', $data);
    }

    /** @param array<string, mixed> $data */
    public function updateAttribute(int $id, array $data): void
    {
        $this->updateRow('attributes', $id, $data);
    }

    public function deleteAttribute(int $id): void
    {
        $this->deleteRow('attributes', $id);
    }

    public function addValue(int $attributeId, string $value): int
    {
        return $this->insertRow('attribute_values', ['attribute_id' => $attributeId, 'value' => $value]);
    }

    public function deleteValue(int $id): void
    {
        $this->deleteRow('attribute_values', $id);
    }

    /**
     * Storefront filters: attributes flagged for display, with only the values that sellable variants of the given items actually use.
     *
     * @param list<int> $itemIds
     * @return list<array<string, mixed>>
     */
    public function storefrontFilters(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }
        $rows = $this->rows($this->qb()->select('DISTINCT a.id AS attribute_id', 'a.name AS attribute_name', 'av.id AS value_id', 'av.value')
            ->from('item_variant_attribute_values', 'x')
            ->join('x', 'item_variants', 'v', "v.id = x.item_variant_id AND v.sellable = 1 AND v.status = 'active'")
            ->join('x', 'attributes', 'a', 'a.id = x.attribute_id AND a.display_on_storefront = 1')
            ->join('x', 'attribute_values', 'av', 'av.id = x.attribute_value_id')
            ->where('v.item_id IN (:ids)')->setParameter('ids', $itemIds, \Doctrine\DBAL\ArrayParameterType::INTEGER)
            ->orderBy('a.sort_order')->addOrderBy('av.sort_order')->addOrderBy('av.value'));
        $out = [];
        foreach ($rows as $r) {
            $out[$r['attribute_id']]['id'] = (int) $r['attribute_id'];
            $out[$r['attribute_id']]['name'] = $r['attribute_name'];
            $out[$r['attribute_id']]['values'][] = ['id' => (int) $r['value_id'], 'value' => $r['value']];
        }

        return array_values($out);
    }
}
