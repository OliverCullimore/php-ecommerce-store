<?php

declare(strict_types=1);

namespace App\Entity;

enum OrderStatus: string
{
    case Draft = 'draft';
    case PaymentPending = 'payment_pending';
    case Abandoned = 'abandoned';
    case AwaitingDespatch = 'awaiting_despatch';
    case Unallocated = 'unallocated';
    case PartiallyDespatched = 'partially_despatched';
    case Completed = 'completed';
    case PartiallyReturned = 'partially_returned';
    case Returned = 'returned';
    case Cancelled = 'cancelled';
}
