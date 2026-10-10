<?php

declare(strict_types=1);

namespace Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

/** Runs each test inside a transaction that is rolled back. Skips when no test database is configured. */
abstract class DatabaseTestCase extends TestCase
{
    protected Connection $db;

    protected function setUp(): void
    {
        $host = getenv('DB_HOST');
        if ($host === false || $host === '') {
            $this->markTestSkipped('DB_HOST not set; run `ddev exec vendor/bin/phinx migrate -e testing` first.');
        }
        $this->db = DriverManager::getConnection([
            'driver' => 'pdo_mysql',
            'host' => $host,
            'port' => (int) (getenv('DB_PORT') ?: 3306),
            'dbname' => getenv('DB_TEST_NAME') ?: 'db_test',
            'user' => getenv('DB_USER') ?: 'db',
            'password' => getenv('DB_PASS') ?: 'db',
            'charset' => 'utf8mb4',
        ]);
        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (isset($this->db) && $this->db->isTransactionActive()) {
            $this->db->rollBack();
        }
    }

    protected function makeVariant(string $sku, ?float $price = 10.0): int
    {
        $this->db->insert('items', ['name' => 'Item ' . $sku, 'active' => 1]);
        $itemId = (int) $this->db->lastInsertId();
        $this->db->insert('item_variants', ['item_id' => $itemId, 'sku' => $sku, 'name' => $sku, 'unit' => 'each', 'sellable' => $price === null ? 0 : 1, 'sale_price' => $price, 'status' => 'active']);

        return (int) $this->db->lastInsertId();
    }
}
