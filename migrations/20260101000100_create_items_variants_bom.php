<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateItemsVariantsBom extends AbstractMigration
{
    public function change(): void
    {
        $this->table('items')
            ->addColumn('name', 'string', ['limit' => 190])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('active', 'boolean', ['default' => true])
            ->addColumn('notes', 'text', ['null' => true])
            ->create();

        $this->table('item_variants')
            ->addColumn('item_id', 'integer', ['signed' => false])
            ->addColumn('sku', 'string', ['limit' => 64])
            ->addColumn('name', 'string', ['limit' => 190])
            ->addColumn('unit', 'string', ['limit' => 16, 'default' => 'unit'])
            ->addColumn('reorder_threshold', 'decimal', ['precision' => 12, 'scale' => 4, 'default' => 0])
            ->addColumn('sellable', 'boolean', ['default' => false])
            ->addColumn('sale_price', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => true])
            ->addColumn('status', 'string', ['limit' => 16, 'default' => 'active'])
            ->addColumn('notes', 'text', ['null' => true])
            ->addIndex(['sku'], ['unique' => true])
            ->addForeignKey('item_id', 'items', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->table('resources')
            ->addColumn('name', 'string', ['limit' => 190])
            ->addColumn('type', 'string', ['limit' => 16])
            ->addColumn('status', 'string', ['limit' => 16, 'default' => 'active'])
            ->addColumn('purchase_cost', 'decimal', ['precision' => 12, 'scale' => 4, 'null' => true])
            ->addColumn('expected_lifetime_hours', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => true])
            ->addColumn('hourly_rate', 'decimal', ['precision' => 10, 'scale' => 4, 'null' => true])
            ->addColumn('notes', 'text', ['null' => true])
            ->create();

        $this->table('variant_bom_lines')
            ->addColumn('variant_id', 'integer', ['signed' => false])
            ->addColumn('line_type', 'string', ['limit' => 16])
            ->addColumn('component_variant_id', 'integer', ['null' => true, 'signed' => false])
            ->addColumn('resource_id', 'integer', ['null' => true, 'signed' => false])
            ->addColumn('quantity', 'decimal', ['precision' => 12, 'scale' => 4, 'null' => true])
            ->addColumn('hours_used', 'decimal', ['precision' => 10, 'scale' => 4, 'null' => true])
            ->addColumn('notes', 'text', ['null' => true])
            ->addForeignKey('variant_id', 'item_variants', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('component_variant_id', 'item_variants', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('resource_id', 'resources', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();
    }
}
