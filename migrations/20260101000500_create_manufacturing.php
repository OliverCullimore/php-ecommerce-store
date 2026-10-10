<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateManufacturing extends AbstractMigration
{
    public function change(): void
    {
        $this->table('production_batches')
            ->addColumn('item_variant_id', 'integer')
            ->addColumn('quantity_produced', 'decimal', ['precision' => 12, 'scale' => 4])
            ->addColumn('produced_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('notes', 'text', ['null' => true])
            ->addForeignKey('item_variant_id', 'item_variants', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        // sales_order_item_id is constrained once sales_order_items exists.
        $this->table('resource_usage')
            ->addColumn('sales_order_item_id', 'integer', ['null' => true])
            ->addColumn('production_batch_id', 'integer', ['null' => true])
            ->addColumn('resource_id', 'integer')
            ->addColumn('hours_used', 'decimal', ['precision' => 10, 'scale' => 4])
            ->addColumn('used_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('note', 'text', ['null' => true])
            ->addForeignKey('production_batch_id', 'production_batches', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('resource_id', 'resources', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->table('stock')
            ->addForeignKey('production_batch_id', 'production_batches', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->update();
    }
}
