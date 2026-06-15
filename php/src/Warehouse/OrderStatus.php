<?php

declare(strict_types=1);

namespace Kata\Warehouse;

enum OrderStatus: string
{
    case Shipped            = 'SHIPPED';
    case Backorder          = 'BACKORDER';
    case Cancelled          = 'CANCELLED';
    case CancelledAfterShip = 'CANCELLED_AFTER_SHIP';

    public function isCancelled(): bool
    {
        return $this === self::Cancelled || $this === self::CancelledAfterShip;
    }
}
