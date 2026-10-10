<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateCatalogue extends AbstractMigration
{
    public function change(): void
    {
        $this->table('categories')
            ->addColumn('parent_category_id', 'integer', ['null' => true, 'signed' => false])
            ->addColumn('name', 'string', ['limit' => 190])
            ->addColumn('slug', 'string', ['limit' => 190])
            ->addColumn('sort_order', 'integer', ['default' => 0])
            ->addColumn('active', 'boolean', ['default' => true])
            ->addColumn('notes', 'text', ['null' => true])
            ->addIndex(['slug'], ['unique' => true])
            ->addForeignKey('parent_category_id', 'categories', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->table('item_category_assignments')
            ->addColumn('item_id', 'integer', ['signed' => false])
            ->addColumn('category_id', 'integer', ['signed' => false])
            ->addIndex(['item_id', 'category_id'], ['unique' => true])
            ->addForeignKey('item_id', 'items', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('category_id', 'categories', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        $this->table('attributes')
            ->addColumn('name', 'string', ['limit' => 120])
            ->addColumn('display_on_storefront', 'boolean', ['default' => false])
            ->addColumn('sort_order', 'integer', ['default' => 0])
            ->addColumn('notes', 'text', ['null' => true])
            ->create();

        $this->table('attribute_values')
            ->addColumn('attribute_id', 'integer', ['signed' => false])
            ->addColumn('value', 'string', ['limit' => 120])
            ->addColumn('sort_order', 'integer', ['default' => 0])
            ->addColumn('notes', 'text', ['null' => true])
            ->addForeignKey('attribute_id', 'attributes', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        $this->table('item_variant_attribute_values')
            ->addColumn('item_variant_id', 'integer', ['signed' => false])
            ->addColumn('attribute_id', 'integer', ['signed' => false])
            ->addColumn('attribute_value_id', 'integer', ['signed' => false])
            ->addIndex(['item_variant_id', 'attribute_id'], ['unique' => true])
            ->addForeignKey('item_variant_id', 'item_variants', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('attribute_id', 'attributes', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('attribute_value_id', 'attribute_values', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
