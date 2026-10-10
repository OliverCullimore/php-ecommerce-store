<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Customer;

final class CustomerRepository extends Repository
{
    /** @return list<Customer> */
    public function all(): array
    {
        return array_map(Customer::fromRow(...), $this->rows($this->qb()->select('*')->from('customers')->orderBy('name')));
    }

    public function find(int $id): ?Customer
    {
        $r = $this->row($this->qb()->select('*')->from('customers')->where('id = :id')->setParameter('id', $id));

        return $r === null ? null : Customer::fromRow($r);
    }

    public function findByEmail(string $email): ?Customer
    {
        $r = $this->row($this->qb()->select('*')->from('customers')->where('email = :e')->setParameter('e', strtolower(trim($email))));

        return $r === null ? null : Customer::fromRow($r);
    }

    public function create(string $name, string $email, ?string $phone, ?string $address, ?string $passwordHash = null): int
    {
        return $this->insertRow('customers', ['name' => $name, 'email' => strtolower(trim($email)), 'phone' => $phone, 'address' => $address, 'password_hash' => $passwordHash]);
    }

    public function updateDetails(int $id, string $name, ?string $phone, ?string $address): void
    {
        $this->updateRow('customers', $id, ['name' => $name, 'phone' => $phone, 'address' => $address]);
    }

    public function setPasswordHash(int $id, string $hash): void
    {
        $this->updateRow('customers', $id, ['password_hash' => $hash]);
    }
}
