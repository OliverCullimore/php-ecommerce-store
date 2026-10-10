<?php

declare(strict_types=1);

namespace App\Entity;

enum LineStatus: string
{
    case Pending = 'pending';
    case Despatched = 'despatched';
    case Cancelled = 'cancelled';
    case Returned = 'returned';
}
