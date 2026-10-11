<?php

declare(strict_types=1);

namespace App\Entity;

enum ResourceType: string
{
    case Asset = 'asset';
    case Service = 'service';
    case Labour = 'labour';
}
