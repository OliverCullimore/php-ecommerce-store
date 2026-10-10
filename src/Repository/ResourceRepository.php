<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Resource;

final class ResourceRepository extends Repository
{
    /** @return list<Resource> */
    public function all(): array
    {
        return array_map(Resource::fromRow(...), $this->rows($this->qb()->select('*')->from('resources')->orderBy('type')->addOrderBy('name')));
    }

    public function find(int $id): ?Resource
    {
        $r = $this->row($this->qb()->select('*')->from('resources')->where('id = :id')->setParameter('id', $id));

        return $r === null ? null : Resource::fromRow($r);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        return $this->insertRow('resources', $data);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $this->updateRow('resources', $id, $data);
    }
}
