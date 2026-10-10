<?php

declare(strict_types=1);

namespace App\Entity;

enum BomLineType: string
{
    case Component = 'component';
    case Resource = 'resource';
}
