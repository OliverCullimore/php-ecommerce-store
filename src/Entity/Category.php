<?php

declare(strict_types=1);

namespace App\Entity;

final readonly class Category
{
    public function __construct(public int $id, public ?int $parentId, public string $name, public string $slug, public int $sortOrder, public bool $active, public ?string $notes)
    {
    }

    /** @param array<string, mixed> $r */
    public static function fromRow(array $r): self
    {
        return new self(
            (int) $r['id'],
            $r['parent_category_id'] === null ? null : (int) $r['parent_category_id'],
            (string) $r['name'],
            (string) $r['slug'],
            (int) $r['sort_order'],
            (bool) $r['active'],
            $r['notes'] ?? null,
        );
    }
}
