<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Supplier;

final class SupplierRepository extends Repository
{
    /** @return list<Supplier> */
    public function all(): array
    {
        return array_map(Supplier::fromRow(...), $this->rows($this->qb()->select('*')->from('suppliers')->orderBy('name')));
    }

    public function find(int $id): ?Supplier
    {
        $r = $this->row($this->qb()->select('*')->from('suppliers')->where('id = :id')->setParameter('id', $id));

        return $r === null ? null : Supplier::fromRow($r);
    }

    public function create(string $name, ?string $contact): int
    {
        return $this->insertRow('suppliers', ['name' => $name, 'contact_info' => $contact]);
    }

    public function update(int $id, string $name, ?string $contact): void
    {
        $this->updateRow('suppliers', $id, ['name' => $name, 'contact_info' => $contact]);
    }

    /** @return list<array<string, mixed>> */
    public function linksForVariant(int $variantId): array
    {
        return $this->rows($this->qb()->select('l.*', 's.name AS supplier_name')->from('item_variant_suppliers', 'l')->join('l', 'suppliers', 's', 's.id = l.supplier_id')
            ->where('l.item_variant_id = :v')->setParameter('v', $variantId)->orderBy('l.is_default', 'DESC'));
    }

    public function defaultSupplierId(int $variantId): ?int
    {
        $r = $this->row($this->qb()->select('supplier_id')->from('item_variant_suppliers')->where('item_variant_id = :v AND is_default = 1')->setParameter('v', $variantId));

        return $r === null ? null : (int) $r['supplier_id'];
    }

    /** At most one default per variant is enforced here, not in the schema. */
    public function link(int $variantId, int $supplierId, ?string $supplierSku, bool $makeDefault): void
    {
        $this->db->transactional(function () use ($variantId, $supplierId, $supplierSku, $makeDefault): void {
            $existing = $this->row($this->qb()->select('id')->from('item_variant_suppliers')->where('item_variant_id = :v AND supplier_id = :s')->setParameter('v', $variantId)->setParameter('s', $supplierId));
            $hasAny = $this->linksForVariant($variantId) !== [];
            $isDefault = $makeDefault || !$hasAny;
            if ($isDefault) {
                $this->db->update('item_variant_suppliers', ['is_default' => 0], ['item_variant_id' => $variantId]);
            }
            if ($existing === null) {
                $this->db->insert('item_variant_suppliers', ['item_variant_id' => $variantId, 'supplier_id' => $supplierId, 'supplier_sku' => $supplierSku, 'is_default' => (int) $isDefault]);
            } else {
                $this->db->update('item_variant_suppliers', ['supplier_sku' => $supplierSku, 'is_default' => (int) $isDefault], ['id' => $existing['id']]);
            }
        });
    }

    public function unlink(int $linkId): void
    {
        $this->deleteRow('item_variant_suppliers', $linkId);
    }
}
