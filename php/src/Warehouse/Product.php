<?php

declare(strict_types=1);

namespace Kata\Warehouse;

class Product
{
    public int $onHand;
    public int $reserved = 0;

    public function __construct(
        public readonly float $price,
        int $onHand,
    ) {
        $this->onHand = $onHand;
    }

    public function available(): int            { return $this->onHand - $this->reserved; }
    public function receive(int $qty): void     { $this->onHand += $qty; }
    public function ship(int $qty): void        { $this->onHand -= $qty; }
    public function restock(int $qty): void     { $this->onHand += $qty; }
    public function reserve(int $qty): void     { $this->reserved += $qty; }
    public function releaseReserved(int $qty): void
    {
        $this->reserved = max(0, $this->reserved - $qty);
    }
}
