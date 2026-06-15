<?php

declare(strict_types=1);

namespace Kata\Warehouse;

class WarehouseDeskApp
{
    /** @var array<string, Product> */
    private array $products = [];
    /** @var array<string, Order> */
    private array $orders = [];
    /** @var array<string, Reservation> */
    private array $reservations = [];
    private array $eventLog = [];
    private float $cashBalance = 0.0;
    private int $nextOrderNumber = 0;
    private int $nextReservationNumber = 2001;
    private ?\Closure $clockFn = null;

    public function seedData(): void
    {
        $this->products = [
            'PEN-BLACK' => new Product(1.5,  40),
            'PEN-BLUE'  => new Product(1.6,  25),
            'NOTE-A5'   => new Product(4.0,  15),
            'STAPLER'   => new Product(12.0,  4),
        ];
        $this->cashBalance = 300.0;
        $this->nextOrderNumber = 1001;
        $this->nextReservationNumber = 2001;
        $this->reservations = [];
    }

    public function getEventLog(): array
    {
        return $this->eventLog;
    }

    public function setClock(\Closure $fn): void
    {
        $this->clockFn = $fn;
    }

    public function runDemoDay(): void
    {
        $commands = [
            'RECV;NOTE-A5;5;2.20',
            'SELL;alice;PEN-BLACK;10',
            'SELL;bob;STAPLER;5',
            'CANCEL;O1002',
            'COUNT;STAPLER',
            'SELL;carol;STAPLER;2',
            'SELL;dan;NOTE-A5;14',
            'COUNT;NOTE-A5',
            'DUMP',
        ];
        foreach ($commands as $command) {
            $this->processLine($command);
        }
        $this->printEndOfDayReport();
    }

    public function processLine(string $line): void
    {
        $this->expireReservations();
        $parts = explode(';', $line);
        match ($parts[0]) {
            'RESERVE' => $this->handleReserve($parts),
            'CONFIRM' => $this->handleConfirm($parts[1]),
            'RELEASE' => $this->handleRelease($parts[1]),
            'RECV'    => $this->handleRecv($parts),
            'SELL'    => $this->handleSell($parts),
            'CANCEL'  => $this->handleCancel($parts[1]),
            'COUNT'   => $this->handleCount($parts[1]),
            'DUMP'    => $this->handleDump(),
            default   => $this->log("unknown command: $line"),
        };
    }

    private function handleReserve(array $parts): void
    {
        $customer = $parts[1];
        $sku      = $parts[2];
        $qty      = $this->parseInt($parts[3]);
        $minutes  = $this->parseInt($parts[4]);
        $product  = $this->products[$sku] ?? null;
        if ($product === null || $product->available() < $qty) {
            $this->log("reservation failed for $customer sku=$sku qty=$qty insufficient stock");
            return;
        }
        $id = $this->nextReservationId();
        $this->reservations[$id] = new Reservation($customer, $sku, $qty, $this->now() + $minutes * 60);
        $product->reserve($qty);
        $this->log("reserved $id for $customer sku=$sku qty=$qty expires={$minutes}min");
    }

    private function handleConfirm(string $id): void
    {
        $res = $this->reservations[$id] ?? null;
        if ($res === null) {
            $this->log("cannot confirm $id because it does not exist");
            return;
        }
        if (!$res->isActive()) {
            $this->log("cannot confirm $id because it is {$res->status->value}");
            return;
        }
        $product = $this->products[$res->sku];
        $orderId = $this->nextOrderId();
        $this->orders[$orderId] = new Order($res->sku, $res->qty, OrderStatus::Shipped);
        $product->ship($res->qty);
        $product->releaseReserved($res->qty);
        $orderTotal = $product->price * $res->qty;
        $this->cashBalance += $orderTotal;
        $res->status = ReservationStatus::Confirmed;
        $amount   = $this->javaDouble($orderTotal);
        $customer = $res->customer;
        $this->log("confirmed reservation $id order $orderId shipped to $customer amount=$amount");
    }

    private function handleRelease(string $id): void
    {
        $res = $this->reservations[$id] ?? null;
        if ($res === null) {
            $this->log("cannot release $id because it does not exist");
            return;
        }
        if (!$res->isActive()) {
            $this->log("cannot release $id because it is {$res->status->value}");
            return;
        }
        $this->products[$res->sku]->releaseReserved($res->qty);
        $res->status = ReservationStatus::Released;
        $this->log("released reservation $id");
    }

    private function handleRecv(array $parts): void
    {
        $sku      = $parts[1];
        $qty      = $this->parseInt($parts[2]);
        $unitCost = $this->parseDouble($parts[3]);
        $this->products[$sku] ??= new Product(0.0, 0);
        $this->products[$sku]->receive($qty);
        $this->cashBalance -= $qty * $unitCost;
        $this->log("received $qty of $sku at $unitCost");
    }

    private function handleSell(array $parts): void
    {
        $customer = $parts[1];
        $sku      = $parts[2];
        $qty      = $this->parseInt($parts[3]);
        $orderId  = $this->nextOrderId();
        $product  = $this->products[$sku] ?? null;
        if ($product === null || $product->available() < $qty) {
            $this->orders[$orderId] = new Order($sku, $qty, OrderStatus::Backorder);
            $this->log("order $orderId backordered for $customer sku=$sku qty=$qty");
            return;
        }
        $product->ship($qty);
        $orderTotal = $product->price * $qty;
        $this->cashBalance += $orderTotal;
        $this->orders[$orderId] = new Order($sku, $qty, OrderStatus::Shipped);
        $amount = $this->javaDouble($orderTotal);
        $this->log("order $orderId shipped to $customer amount=$amount");
    }

    private function handleCancel(string $orderId): void
    {
        $order = $this->orders[$orderId] ?? null;
        if ($order === null) {
            $this->log("cannot cancel $orderId because it does not exist");
            return;
        }
        if ($order->status === OrderStatus::Backorder) {
            $order->status = OrderStatus::Cancelled;
            $this->log("cancelled backorder $orderId");
            return;
        }
        if ($order->status === OrderStatus::Shipped) {
            $product = $this->products[$order->sku];
            $product->restock($order->qty);
            $this->cashBalance -= $product->price * $order->qty;
            $order->status = OrderStatus::CancelledAfterShip;
            $this->log("cancelled shipped order $orderId with restock");
            return;
        }
        $this->log("order $orderId could not be cancelled from state {$order->status->value}");
    }

    private function handleCount(string $sku): void
    {
        $product   = $this->products[$sku] ?? null;
        $onHand    = $product?->onHand    ?? 0;
        $reserved  = $product?->reserved  ?? 0;
        $available = $onHand - $reserved;
        $this->log("count $sku onHand=$onHand reserved=$reserved available=$available");
    }

    private function handleDump(): void
    {
        $stocks              = array_map(fn(Product $p)     => $p->onHand,       $this->products);
        $reserved            = array_map(fn(Product $p)     => $p->reserved,     $this->products);
        $reservationStatuses = array_map(fn(Reservation $r) => $r->status->value, $this->reservations);
        $orderStatuses       = array_map(fn(Order $o)       => $o->status->value, $this->orders);
        echo "---- dump ----\n";
        echo 'stock='        . $this->javaMap($stocks)              . "\n";
        echo 'reserved='     . $this->javaMap($reserved)            . "\n";
        echo 'reservations=' . $this->javaMap($reservationStatuses) . "\n";
        echo 'orders='       . $this->javaMap($orderStatuses)       . "\n";
        echo 'cashBalance='  . $this->javaDouble($this->cashBalance) . "\n";
    }

    private function expireReservations(): void
    {
        $now = $this->now();
        foreach ($this->reservations as $id => $reservation) {
            if ($reservation->isExpiredAt($now)) {
                $this->products[$reservation->sku]->releaseReserved($reservation->qty);
                $reservation->status = ReservationStatus::Expired;
                $this->log("reservation $id expired");
            }
        }
    }

    private function nextOrderId(): string       { return 'O' . $this->nextOrderNumber++; }
    private function nextReservationId(): string { return 'R' . $this->nextReservationNumber++; }
    private function now(): int                  { return $this->clockFn ? ($this->clockFn)() : time(); }

    private function log(string $event): void
    {
        $this->eventLog[] = $event;
    }

    private function parseInt(string $value): int
    {
        return (int) trim($value);
    }

    private function parseDouble(string $value): float
    {
        return (float) trim($value);
    }

    // Matches Java's double-to-string: whole numbers print as "15.0", not "15"
    private function javaDouble(float $n): string
    {
        return (floor($n) === $n) ? number_format($n, 1, '.', '') : (string) $n;
    }

    // Matches Java's HashMap.toString(): {key=value, key=value}
    private function javaMap(array $map): string
    {
        $entries = [];
        foreach ($map as $k => $v) {
            $entries[] = "$k=$v";
        }
        return '{' . implode(', ', $entries) . '}';
    }

    public function printEndOfDayReport(): void
    {
        $shipped = $backorder = $cancelled = 0;
        foreach ($this->orders as $order) {
            if ($order->status === OrderStatus::Shipped)       { $shipped++; }
            elseif ($order->status === OrderStatus::Backorder) { $backorder++; }
            elseif ($order->status->isCancelled())             { $cancelled++; }
        }
        $lowStock = array_keys(array_filter($this->products, fn(Product $p) => $p->onHand < 5));
        echo "\n";
        echo "==== end of day ====\n";
        echo 'orders shipped: '     . $shipped   . "\n";
        echo 'orders backordered: ' . $backorder . "\n";
        echo 'orders cancelled: '   . $cancelled . "\n";
        echo 'cash balance: '       . number_format($this->cashBalance, 2) . "\n";
        echo 'low stock skus: ['    . implode(', ', $lowStock)             . "]\n";
        echo "\n";
        echo "events:\n";
        foreach ($this->eventLog as $event) {
            echo " - $event\n";
        }
    }
}
