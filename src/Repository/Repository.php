<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

abstract class Repository
{
    public function __construct(protected readonly Connection $db)
    {
    }

    protected function qb(): QueryBuilder
    {
        return $this->db->createQueryBuilder();
    }

    /** @return list<array<string, mixed>> */
    protected function rows(QueryBuilder $qb): array
    {
        return $qb->executeQuery()->fetchAllAssociative();
    }

    /** @return array<string, mixed>|null */
    protected function row(QueryBuilder $qb): ?array
    {
        $r = $qb->setMaxResults(1)->executeQuery()->fetchAssociative();

        return $r === false ? null : $r;
    }

    /** @param array<string, mixed> $data */
    protected function insertRow(string $table, array $data): int
    {
        $this->db->insert($table, $data);

        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    protected function updateRow(string $table, int $id, array $data): void
    {
        $this->db->update($table, $data, ['id' => $id]);
    }

    protected function deleteRow(string $table, int $id): void
    {
        $this->db->delete($table, ['id' => $id]);
    }

    /** Inserts/updates should be wrapped by services via this. */
    public function transactional(callable $fn): mixed
    {
        return $this->db->transactional($fn);
    }
}
