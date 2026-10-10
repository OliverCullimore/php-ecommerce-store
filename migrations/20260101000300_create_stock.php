<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * The ledger references tables that don't exist yet (purchase_order_items,
 * production_batches, sales_order_items). Those FK columns are created plain
 * here and constrained by the migration that creates the target table.
 */
final class CreateStock extends AbstractMigration
{
    public function change(): void
    {
        $this->table('stock')
            ->addColumn('item_variant_id', 'integer', ['signed' => false])
            ->addColumn('entry_type', 'string', ['limit' => 24])
            ->addColumn('quantity', 'decimal', ['precision' => 12, 'scale' => 4])
            ->addColumn('cost', 'decimal', ['precision' => 12, 'scale' => 4, 'null' => true])
            ->addColumn('source_stock_id', 'integer', ['null' => true, 'signed' => false])
            ->addColumn('purchase_order_item_id', 'integer', ['null' => true, 'signed' => false])
            ->addColumn('production_batch_id', 'integer', ['null' => true, 'signed' => false])
            ->addColumn('sales_order_item_id', 'integer', ['null' => true, 'signed' => false])
            ->addColumn('occurred_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('notes', 'text', ['null' => true])
            ->addIndex(['item_variant_id', 'entry_type', 'occurred_at'])
            ->addIndex(['source_stock_id'])
            ->addIndex(['purchase_order_item_id'])
            ->addIndex(['production_batch_id'])
            ->addIndex(['sales_order_item_id'])
            ->addForeignKey('item_variant_id', 'item_variants', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('source_stock_id', 'stock', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();
    }
}
