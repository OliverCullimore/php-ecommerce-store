<?php

declare(strict_types=1);

namespace App\Entity;

enum VariantStatus: string
{
    case Active = 'active';
    case Discontinued = 'discontinued';
}
