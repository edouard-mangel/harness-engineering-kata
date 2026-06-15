<?php

declare(strict_types=1);

namespace Kata\Warehouse;

class Order
{
    public OrderStatus $status;

    public function __construct(
        public readonly string $sku,
        public readonly int $qty,
        OrderStatus $status,
    ) {
        $this->status = $status;
    }
}
