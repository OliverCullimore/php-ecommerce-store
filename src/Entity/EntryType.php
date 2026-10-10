<?php

declare(strict_types=1);

namespace App\Entity;

enum EntryType: string
{
    case Receipt = 'receipt';
    case ProductionOutput = 'production_output';
    case Consumption = 'consumption';
    case ReturnedStock = 'return';
}
