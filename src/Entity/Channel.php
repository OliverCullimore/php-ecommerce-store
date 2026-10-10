<?php

declare(strict_types=1);

namespace App\Entity;

enum Channel: string
{
    case Storefront = 'storefront';
    case Manual = 'manual';
}
