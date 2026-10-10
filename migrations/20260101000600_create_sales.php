<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateSales extends AbstractMigration
{
    public function change(): void
    {
        $this->table('customers')
            ->addColumn('name', 'string', ['limit' => 190])
            ->addColumn('email', 'string', ['limit' => 190])
            ->addColumn('phone', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('address', 'text', ['null' => true])
            ->addColumn('password_hash', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('notes', 'text', ['null' => true])
            ->addIndex(['email'], ['unique' => true])
            ->create();

        $this->table('sales_orders')
            ->addColumn('customer_id', 'integer', ['signed' => false])
            ->addColumn('order_date', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('channel', 'string', ['limit' => 16])
            ->addColumn('status', 'string', ['limit' => 24, 'default' => 'draft'])
            ->addColumn('invoice_issued_at', 'datetime', ['null' => true])
            ->addColumn('notes', 'text', ['null' => true])
            ->addIndex(['status'])
            ->addForeignKey('customer_id', 'customers', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->table('sales_order_items')
            ->addColumn('sales_order_id', 'integer', ['signed' => false])
            ->addColumn('item_variant_id', 'integer', ['signed' => false])
            ->addColumn('quantity', 'decimal', ['precision' => 12, 'scale' => 4])
            ->addColumn('unit_price', 'decimal', ['precision' => 10, 'scale' => 2])
            ->addColumn('cost_price', 'decimal', ['precision' => 12, 'scale' => 4, 'null' => true])
            ->addColumn('line_status', 'string', ['limit' => 16, 'default' => 'pending'])
            ->addForeignKey('sales_order_id', 'sales_orders', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('item_variant_id', 'item_variants', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->table('stock')
            ->addForeignKey('sales_order_item_id', 'sales_order_items', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->update();
        $this->table('resource_usage')
            ->addForeignKey('sales_order_item_id', 'sales_order_items', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->update();
    }
}
