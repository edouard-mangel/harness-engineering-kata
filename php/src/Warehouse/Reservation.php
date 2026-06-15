<?php

declare(strict_types=1);

namespace Kata\Warehouse;

class Reservation
{
    public ReservationStatus $status = ReservationStatus::Active;

    public function __construct(
        public readonly string $customer,
        public readonly string $sku,
        public readonly int $qty,
        public readonly int $expiresAt,
    ) {}

    public function isActive(): bool
    {
        return $this->status === ReservationStatus::Active;
    }

    public function isExpiredAt(int $now): bool
    {
        return $this->isActive() && $now >= $this->expiresAt;
    }
}
