<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreatePayments extends AbstractMigration
{
    public function change(): void
    {
        $this->table('payments')
            ->addColumn('sales_order_id', 'integer', ['signed' => false])
            ->addColumn('gateway', 'string', ['limit' => 32])
            ->addColumn('gateway_reference', 'string', ['limit' => 190, 'null' => true])
            ->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2])
            ->addColumn('currency', 'string', ['limit' => 3, 'default' => 'GBP'])
            ->addColumn('status', 'string', ['limit' => 16, 'default' => 'pending'])
            ->addColumn('refunded_payment_id', 'integer', ['null' => true, 'signed' => false])
            ->addColumn('raw_response', 'json', ['null' => true])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['gateway_reference'])
            ->addForeignKey('sales_order_id', 'sales_orders', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('refunded_payment_id', 'payments', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();
    }
}
