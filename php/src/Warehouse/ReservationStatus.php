<?php

declare(strict_types=1);

namespace Kata\Warehouse;

enum ReservationStatus: string
{
    case Active    = 'ACTIVE';
    case Confirmed = 'CONFIRMED';
    case Released  = 'RELEASED';
    case Expired   = 'EXPIRED';
}
