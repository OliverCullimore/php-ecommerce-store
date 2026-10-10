<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;

final class UserRepository extends Repository
{
    public function findByEmail(string $email): ?User
    {
        $r = $this->row($this->qb()->select('*')->from('users')->where('email = :e')->setParameter('e', strtolower(trim($email))));

        return $r === null ? null : User::fromRow($r);
    }

    public function create(string $email, string $passwordHash): int
    {
        return $this->insertRow('users', ['email' => strtolower(trim($email)), 'password_hash' => $passwordHash]);
    }

    public function touchLogin(int $id): void
    {
        $this->updateRow('users', $id, ['last_login_at' => date('Y-m-d H:i:s')]);
    }
}
