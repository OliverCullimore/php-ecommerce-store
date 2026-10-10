<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\ArrayParameterType;

final class CatalogueRepository extends Repository
{
    /**
     * One row per item (not per colour). Only items with at least one sellable, active variant.
     *
     * @param list<int>|null $categoryIds null = all categories
     * @param array<int, int> $filters attributeId => valueId
     * @return list<array<string, mixed>>
     */
    public function items(?array $categoryIds, array $filters = []): array
    {
        $qb = $this->qb()->select('i.id', 'i.name', 'i.description', '(SELECT MIN(v.sale_price) FROM item_variants v WHERE v.item_id = i.id AND v.sellable = 1 AND v.status = \'active\') AS price_from')
            ->from('items', 'i')->where('i.active = 1')
            ->andWhere("EXISTS (SELECT 1 FROM item_variants v WHERE v.item_id = i.id AND v.sellable = 1 AND v.status = 'active')")->orderBy('i.name');

        if ($categoryIds !== null) {
            $qb->andWhere('EXISTS (SELECT 1 FROM item_category_assignments a WHERE a.item_id = i.id AND a.category_id IN (:cats))')
                ->setParameter('cats', $categoryIds === [] ? [0] : $categoryIds, ArrayParameterType::INTEGER);
        }
        $n = 0;
        foreach ($filters as $attributeId => $valueId) {
            $n++;
            $qb->andWhere("EXISTS (SELECT 1 FROM item_variants fv JOIN item_variant_attribute_values fx ON fx.item_variant_id = fv.id WHERE fv.item_id = i.id AND fv.sellable = 1 AND fv.status = 'active' AND fx.attribute_id = :fa$n AND fx.attribute_value_id = :fv$n)")
                ->setParameter("fa$n", $attributeId)->setParameter("fv$n", $valueId);
        }

        return $this->rows($qb);
    }

    /** @return array<string, mixed>|null */
    public function item(int $itemId): ?array
    {
        return $this->row($this->qb()->select('*')->from('items')->where('id = :i AND active = 1')->setParameter('i', $itemId));
    }

    /** @return list<array<string, mixed>> sellable variants with an 'options' map of attribute name => value */
    public function sellableVariants(int $itemId): array
    {
        $variants = $this->rows($this->qb()->select('*')->from('item_variants')->where("item_id = :i AND sellable = 1 AND status = 'active'")->setParameter('i', $itemId)->orderBy('name'));
        foreach ($variants as &$v) {
            $opts = $this->rows($this->qb()->select('a.name', 'av.value')->from('item_variant_attribute_values', 'x')->join('x', 'attributes', 'a', 'a.id = x.attribute_id')
                ->join('x', 'attribute_values', 'av', 'av.id = x.attribute_value_id')->where('x.item_variant_id = :v')->setParameter('v', $v['id'])->orderBy('a.sort_order'));
            $v['options'] = array_column($opts, 'value', 'name');
        }

        return $variants;
    }

    /** @return array<string, mixed>|null sellable variant plus item name, for the cart */
    public function sellableVariant(int $variantId): ?array
    {
        return $this->row($this->qb()->select('v.*', 'i.name AS item_name')->from('item_variants', 'v')->join('v', 'items', 'i', 'i.id = v.item_id')
            ->where("v.id = :v AND v.sellable = 1 AND v.status = 'active' AND i.active = 1 AND v.sale_price IS NOT NULL")->setParameter('v', $variantId));
    }
}
