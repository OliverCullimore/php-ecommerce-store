<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\Item\BomSourceInterface;
use App\Entity\Resource;

final class BomRepository extends Repository implements BomSourceInterface
{
    public function variantInfo(int $variantId): array
    {
        $r = $this->row($this->qb()
            ->select(
                "CONCAT(i.name, ' / ', v.name) AS label",
                "(SELECT s.cost FROM stock s WHERE s.item_variant_id = v.id AND s.entry_type IN ('receipt','production_output','return') AND s.cost IS NOT NULL ORDER BY s.occurred_at DESC, s.id DESC LIMIT 1) AS unit_cost",
            )
            ->from('item_variants', 'v')->join('v', 'items', 'i', 'i.id = v.item_id')->where('v.id = :id')->setParameter('id', $variantId));
        if ($r === null) {
            throw new \DomainException(sprintf('Variant #%d not found.', $variantId));
        }

        return ['label' => (string) $r['label'], 'unitCost' => (float) ($r['unit_cost'] ?? 0)];
    }

    public function components(int $variantId): array
    {
        $rows = $this->rows($this->qb()->select('component_variant_id', 'quantity')->from('variant_bom_lines')
            ->where("variant_id = :v AND line_type = 'component'")->setParameter('v', $variantId)->orderBy('id'));

        return array_map(static fn (array $r): array => ['variantId' => (int) $r['component_variant_id'], 'quantity' => (float) $r['quantity']], $rows);
    }

    public function resources(int $variantId): array
    {
        $rows = $this->rows($this->qb()->select('r.*', 'l.hours_used')->from('variant_bom_lines', 'l')->join('l', 'resources', 'r', 'r.id = l.resource_id')
            ->where("l.variant_id = :v AND l.line_type = 'resource'")->setParameter('v', $variantId)->orderBy('l.id'));

        return array_map(static function (array $r): array {
            $res = Resource::fromRow($r);

            return ['label' => $res->name, 'hours' => (float) $r['hours_used'], 'ratePerHour' => $res->ratePerHour()];
        }, $rows);
    }

    /** @return list<array<string, mixed>> */
    public function componentLines(int $variantId): array
    {
        return $this->rows($this->qb()->select('l.id', 'l.quantity', 'l.component_variant_id', "CONCAT(i.name, ' / ', v.name) AS label", 'v.unit')
            ->from('variant_bom_lines', 'l')->join('l', 'item_variants', 'v', 'v.id = l.component_variant_id')->join('v', 'items', 'i', 'i.id = v.item_id')
            ->where("l.variant_id = :v AND l.line_type = 'component'")->setParameter('v', $variantId)->orderBy('l.id'));
    }

    /** @return list<array<string, mixed>> */
    public function resourceLines(int $variantId): array
    {
        return $this->rows($this->qb()->select('l.id', 'l.hours_used', 'l.resource_id', 'r.name', 'r.type')
            ->from('variant_bom_lines', 'l')->join('l', 'resources', 'r', 'r.id = l.resource_id')
            ->where("l.variant_id = :v AND l.line_type = 'resource'")->setParameter('v', $variantId)->orderBy('l.id'));
    }

    public function addComponent(int $variantId, int $componentId, float $quantity, ?string $notes = null): int
    {
        return $this->insertRow('variant_bom_lines', ['variant_id' => $variantId, 'line_type' => 'component', 'component_variant_id' => $componentId, 'quantity' => $quantity, 'notes' => $notes]);
    }

    public function addResource(int $variantId, int $resourceId, float $hours, ?string $notes = null): int
    {
        return $this->insertRow('variant_bom_lines', ['variant_id' => $variantId, 'line_type' => 'resource', 'resource_id' => $resourceId, 'hours_used' => $hours, 'notes' => $notes]);
    }

    public function deleteLine(int $lineId, int $variantId): void
    {
        $this->db->delete('variant_bom_lines', ['id' => $lineId, 'variant_id' => $variantId]);
    }

    public function isManufactured(int $variantId): bool
    {
        return $this->components($variantId) !== [];
    }

    /** @return list<int> */
    public function manufacturedVariantIds(): array
    {
        return array_map('intval', array_column($this->rows($this->qb()->select('DISTINCT variant_id')->from('variant_bom_lines')->where("line_type = 'component'")), 'variant_id'));
    }
}
