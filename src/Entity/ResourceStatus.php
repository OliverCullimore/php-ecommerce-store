<?php

declare(strict_types=1);

namespace App\Entity;

enum ResourceStatus: string
{
    case OnOrder = 'on_order';
    case Active = 'active';
    case Retired = 'retired';
}
