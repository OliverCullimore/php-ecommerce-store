<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreatePurchasing extends AbstractMigration
{
    public function change(): void
    {
        $this->table('suppliers')
            ->addColumn('name', 'string', ['limit' => 190])
            ->addColumn('contact_info', 'text', ['null' => true])
            ->create();

        $this->table('item_variant_suppliers')
            ->addColumn('item_variant_id', 'integer')
            ->addColumn('supplier_id', 'integer')
            ->addColumn('is_default', 'boolean', ['default' => false])
            ->addColumn('supplier_sku', 'string', ['limit' => 120, 'null' => true])
            ->addColumn('notes', 'text', ['null' => true])
            ->addIndex(['item_variant_id', 'supplier_id'], ['unique' => true])
            ->addForeignKey('item_variant_id', 'item_variants', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('supplier_id', 'suppliers', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->table('purchase_orders')
            ->addColumn('supplier_id', 'integer')
            ->addColumn('order_date', 'date')
            ->addColumn('status', 'string', ['limit' => 16, 'default' => 'draft'])
            ->addColumn('notes', 'text', ['null' => true])
            ->addForeignKey('supplier_id', 'suppliers', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->table('purchase_order_items')
            ->addColumn('purchase_order_id', 'integer')
            ->addColumn('item_variant_id', 'integer', ['null' => true])
            ->addColumn('resource_id', 'integer', ['null' => true])
            ->addColumn('quantity', 'decimal', ['precision' => 12, 'scale' => 4])
            ->addColumn('unit_cost', 'decimal', ['precision' => 12, 'scale' => 4])
            ->addColumn('received_at', 'datetime', ['null' => true])
            ->addForeignKey('purchase_order_id', 'purchase_orders', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('item_variant_id', 'item_variants', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('resource_id', 'resources', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->table('stock')
            ->addForeignKey('purchase_order_item_id', 'purchase_order_items', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->update();
    }
}
