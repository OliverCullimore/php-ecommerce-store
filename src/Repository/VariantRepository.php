<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ItemVariant;

final class VariantRepository extends Repository
{
    public function find(int $id): ?ItemVariant
    {
        $r = $this->row($this->qb()->select('*')->from('item_variants')->where('id = :id')->setParameter('id', $id));

        return $r === null ? null : ItemVariant::fromRow($r);
    }

    /** @return array<string, mixed>|null variant row plus item_name */
    public function findWithItem(int $id): ?array
    {
        return $this->row($this->qb()->select('v.*', 'i.name AS item_name')->from('item_variants', 'v')->join('v', 'items', 'i', 'i.id = v.item_id')->where('v.id = :id')->setParameter('id', $id));
    }

    public function skuExists(string $sku, ?int $exceptId = null): bool
    {
        $qb = $this->qb()->select('id')->from('item_variants')->where('sku = :s')->setParameter('s', $sku);
        if ($exceptId !== null) {
            $qb->andWhere('id <> :x')->setParameter('x', $exceptId);
        }

        return $this->row($qb) !== null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        return $this->insertRow('item_variants', $data);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $this->updateRow('item_variants', $id, $data);
    }

    /** @return list<array<string, mixed>> id, sku, label, sellable, unit */
    public function options(bool $sellableOnly = false): array
    {
        $qb = $this->qb()->select('v.id', 'v.sku', 'v.unit', 'v.sellable', 'v.sale_price', "CONCAT(i.name, ' / ', v.name) AS label")
            ->from('item_variants', 'v')->join('v', 'items', 'i', 'i.id = v.item_id')->where("v.status = 'active'")->orderBy('i.name')->addOrderBy('v.name');
        if ($sellableOnly) {
            $qb->andWhere('v.sellable = 1');
        }

        return $this->rows($qb);
    }

    /** @return list<array<string, mixed>> attribute_id, attribute_value_id, attribute_name, value */
    public function attributeValues(int $variantId): array
    {
        return $this->rows($this->qb()->select('x.attribute_id', 'x.attribute_value_id', 'a.name AS attribute_name', 'av.value')
            ->from('item_variant_attribute_values', 'x')->join('x', 'attributes', 'a', 'a.id = x.attribute_id')->join('x', 'attribute_values', 'av', 'av.id = x.attribute_value_id')
            ->where('x.item_variant_id = :v')->setParameter('v', $variantId)->orderBy('a.sort_order'));
    }

    /** @param array<int, int> $map attributeId => valueId (0 = unset) */
    public function setAttributeValues(int $variantId, array $map): void
    {
        $this->db->transactional(function () use ($variantId, $map): void {
            $this->db->delete('item_variant_attribute_values', ['item_variant_id' => $variantId]);
            foreach ($map as $attributeId => $valueId) {
                if ($valueId > 0) {
                    $this->db->insert('item_variant_attribute_values', ['item_variant_id' => $variantId, 'attribute_id' => $attributeId, 'attribute_value_id' => $valueId]);
                }
            }
        });
    }
}
