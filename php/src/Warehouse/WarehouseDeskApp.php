<?php

declare(strict_types=1);

namespace Kata\Warehouse;

class WarehouseDeskApp
{
    private array $stockBySku = [];
    private array $reservedBySku = [];
    private array $priceBySku = [];
    private array $orderStatus = [];
    private array $orderSku = [];
    private array $orderQty = [];
    private array $eventLog = [];
    private float $cashBalance = 0.0;
    private int $nextOrderNumber = 0;

    public function seedData(): void
    {
        $this->stockBySku = [
            'PEN-BLACK' => 40,
            'PEN-BLUE'  => 25,
            'NOTE-A5'   => 15,
            'STAPLER'   => 4,
        ];

        $this->reservedBySku = [
            'PEN-BLACK' => 0,
            'PEN-BLUE'  => 0,
            'NOTE-A5'   => 0,
            'STAPLER'   => 0,
        ];

        $this->priceBySku = [
            'PEN-BLACK' => 1.5,
            'PEN-BLUE'  => 1.6,
            'NOTE-A5'   => 4.0,
            'STAPLER'   => 12.0,
        ];

        $this->cashBalance = 300.0;
        $this->nextOrderNumber = 1001;
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
        $parts = explode(';', $line);
        $type = $parts[0];

        if ($type === 'RECV') {
            $sku = $parts[1];
            $qty = $this->parseInt($parts[2]);
            $unitCost = $this->parseDouble($parts[3]);
            $current = $this->stockBySku[$sku] ?? 0;
            $this->stockBySku[$sku] = $current + $qty;
            $this->cashBalance -= $qty * $unitCost;
            $this->eventLog[] = 'received ' . $qty . ' of ' . $sku . ' at ' . $unitCost;
            return;
        }

        if ($type === 'SELL') {
            $customer = $parts[1];
            $sku = $parts[2];
            $qty = $this->parseInt($parts[3]);
            $orderId = 'O' . $this->nextOrderNumber;
            $this->nextOrderNumber++;
            $this->orderSku[$orderId] = $sku;
            $this->orderQty[$orderId] = $qty;

            $onHand = $this->stockBySku[$sku] ?? 0;
            $reserved = $this->reservedBySku[$sku] ?? 0;
            $available = $onHand - $reserved;
            if ($available < $qty) {
                $this->orderStatus[$orderId] = 'BACKORDER';
                $this->eventLog[] = 'order ' . $orderId . ' backordered for ' . $customer . ' sku=' . $sku . ' qty=' . $qty;
            } else {
                $this->stockBySku[$sku] = $onHand - $qty;
                $unitPrice = $this->priceBySku[$sku] ?? 0.0;
                $orderTotal = $unitPrice * $qty;
                $this->cashBalance += $orderTotal;
                $this->orderStatus[$orderId] = 'SHIPPED';
                $this->eventLog[] = 'order ' . $orderId . ' shipped to ' . $customer . ' amount=' . $orderTotal;
            }
            return;
        }

        if ($type === 'CANCEL') {
            $orderId = $parts[1];
            $status = $this->orderStatus[$orderId] ?? null;
            if ($status === null) {
                $this->eventLog[] = 'cannot cancel ' . $orderId . ' because it does not exist';
                return;
            }

            if ($status === 'BACKORDER') {
                $this->orderStatus[$orderId] = 'CANCELLED';
                $this->eventLog[] = 'cancelled backorder ' . $orderId;
                return;
            }

            if ($status === 'SHIPPED') {
                $sku = $this->orderSku[$orderId];
                $qty = $this->orderQty[$orderId] ?? 0;
                $current = $this->stockBySku[$sku] ?? 0;
                $this->stockBySku[$sku] = $current + $qty;
                $unitPrice = $this->priceBySku[$sku] ?? 0.0;
                $this->cashBalance -= $unitPrice * $qty;
                $this->orderStatus[$orderId] = 'CANCELLED_AFTER_SHIP';
                $this->eventLog[] = 'cancelled shipped order ' . $orderId . ' with restock';
                return;
            }

            $this->eventLog[] = 'order ' . $orderId . ' could not be cancelled from state ' . $status;
            return;
        }

        if ($type === 'COUNT') {
            $sku = $parts[1];
            $onHand = $this->stockBySku[$sku] ?? 0;
            $reserved = $this->reservedBySku[$sku] ?? 0;
            $available = $onHand - $reserved;
            $this->eventLog[] = 'count ' . $sku . ' onHand=' . $onHand . ' reserved=' . $reserved . ' available=' . $available;
            return;
        }

        if ($type === 'DUMP') {
            echo "---- dump ----\n";
            echo 'stock=' . json_encode($this->stockBySku) . "\n";
            echo 'reserved=' . json_encode($this->reservedBySku) . "\n";
            echo 'orders=' . json_encode($this->orderStatus) . "\n";
            echo 'cashBalance=' . $this->cashBalance . "\n";
            return;
        }

        $this->eventLog[] = 'unknown command: ' . $line;
    }

    private function parseInt(string $value): int
    {
        return (int) trim($value);
    }

    private function parseDouble(string $value): float
    {
        return (float) trim($value);
    }

    public function printEndOfDayReport(): void
    {
        $shipped = 0;
        $backorder = 0;
        $cancelled = 0;
        foreach ($this->orderStatus as $status) {
            if ($status === 'SHIPPED') {
                $shipped++;
            } elseif ($status === 'BACKORDER') {
                $backorder++;
            } elseif (str_starts_with($status, 'CANCELLED')) {
                $cancelled++;
            }
        }

        $lowStock = [];
        foreach ($this->stockBySku as $sku => $qty) {
            if ($qty < 5) {
                $lowStock[] = $sku;
            }
        }

        echo "\n";
        echo "==== end of day ====\n";
        echo 'orders shipped: ' . $shipped . "\n";
        echo 'orders backordered: ' . $backorder . "\n";
        echo 'orders cancelled: ' . $cancelled . "\n";
        echo 'cash balance: ' . number_format($this->cashBalance, 2) . "\n";
        echo 'low stock skus: [' . implode(', ', $lowStock) . "]\n";
        echo "\n";
        echo "events:\n";
        foreach ($this->eventLog as $event) {
            echo ' - ' . $event . "\n";
        }
    }
}
