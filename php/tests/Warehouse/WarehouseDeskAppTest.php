<?php

declare(strict_types=1);

namespace Kata\Warehouse;

use PHPUnit\Framework\TestCase;

class WarehouseDeskAppTest extends TestCase
{
    private WarehouseDeskApp $app;

    protected function setUp(): void
    {
        $this->app = new WarehouseDeskApp();
        $this->app->seedData();
    }

    // --- RECV ---

    public function testRecvAddsStock(): void
    {
        $this->app->processLine('RECV;PEN-BLACK;10;2.50');
        $this->app->processLine('COUNT;PEN-BLACK');
        $this->assertContains('count PEN-BLACK onHand=50 reserved=0 available=50', $this->app->getEventLog());
    }

    public function testRecvDebitsCash(): void
    {
        $this->app->processLine('RECV;PEN-BLACK;10;2.50');
        $this->assertContains('received 10 of PEN-BLACK at 2.5', $this->app->getEventLog());
    }

    // --- SELL ---

    public function testSellShipsWhenStockAvailable(): void
    {
        $this->app->processLine('SELL;alice;PEN-BLACK;10');
        $this->assertContains('order O1001 shipped to alice amount=15.0', $this->app->getEventLog());
    }

    public function testSellReducesStockOnShip(): void
    {
        $this->app->processLine('SELL;alice;PEN-BLACK;10');
        $this->app->processLine('COUNT;PEN-BLACK');
        $this->assertContains('count PEN-BLACK onHand=30 reserved=0 available=30', $this->app->getEventLog());
    }

    public function testSellBackordersWhenInsufficientStock(): void
    {
        $this->app->processLine('SELL;bob;STAPLER;5'); // only 4 in stock
        $this->assertContains('order O1001 backordered for bob sku=STAPLER qty=5', $this->app->getEventLog());
    }

    // --- CANCEL ---

    public function testCancelBackorder(): void
    {
        $this->app->processLine('SELL;bob;STAPLER;5');
        $this->app->processLine('CANCEL;O1001');
        $this->assertContains('cancelled backorder O1001', $this->app->getEventLog());
    }

    public function testCancelShippedOrderRestocksAndDebitsRevenue(): void
    {
        $this->app->processLine('SELL;alice;PEN-BLACK;10');
        $this->app->processLine('CANCEL;O1001');
        $this->assertContains('cancelled shipped order O1001 with restock', $this->app->getEventLog());
        $this->app->processLine('COUNT;PEN-BLACK');
        $this->assertContains('count PEN-BLACK onHand=40 reserved=0 available=40', $this->app->getEventLog());
    }

    public function testCancelNonExistentOrder(): void
    {
        $this->app->processLine('CANCEL;O9999');
        $this->assertContains('cannot cancel O9999 because it does not exist', $this->app->getEventLog());
    }

    // --- COUNT ---

    public function testCountShowsStockInfo(): void
    {
        $this->app->processLine('COUNT;PEN-BLUE');
        $this->assertContains('count PEN-BLUE onHand=25 reserved=0 available=25', $this->app->getEventLog());
    }

    // --- Unknown command ---

    public function testUnknownCommandIsLogged(): void
    {
        $this->app->processLine('BOGUS;foo');
        $this->assertContains('unknown command: BOGUS;foo', $this->app->getEventLog());
    }

    // --- RESERVE ---

    public function testReserveCreatesReservationAndReducesAvailable(): void
    {
        $this->app->processLine('RESERVE;alice;PEN-BLACK;10;5');
        $this->assertContains('reserved R2001 for alice sku=PEN-BLACK qty=10 expires=5min', $this->app->getEventLog());
        $this->app->processLine('COUNT;PEN-BLACK');
        $this->assertContains('count PEN-BLACK onHand=40 reserved=10 available=30', $this->app->getEventLog());
    }

    public function testReserveFailsWhenInsufficientStock(): void
    {
        $this->app->processLine('RESERVE;bob;STAPLER;5;2'); // only 4 in stock
        $this->assertContains('reservation failed for bob sku=STAPLER qty=5 insufficient stock', $this->app->getEventLog());
    }

    public function testReserveBlocksSubsequentSell(): void
    {
        $this->app->processLine('RESERVE;alice;STAPLER;4;5'); // reserves all 4
        $this->app->processLine('SELL;bob;STAPLER;1');
        $this->assertContains('order O1001 backordered for bob sku=STAPLER qty=1', $this->app->getEventLog());
    }

    public function testMultipleReservationsAreTrackedIndependently(): void
    {
        $this->app->processLine('RESERVE;alice;PEN-BLACK;5;5');
        $this->app->processLine('RESERVE;bob;PEN-BLACK;5;5');
        $this->assertContains('reserved R2001 for alice sku=PEN-BLACK qty=5 expires=5min', $this->app->getEventLog());
        $this->assertContains('reserved R2002 for bob sku=PEN-BLACK qty=5 expires=5min', $this->app->getEventLog());
        $this->app->processLine('COUNT;PEN-BLACK');
        $this->assertContains('count PEN-BLACK onHand=40 reserved=10 available=30', $this->app->getEventLog());
    }

    // --- CONFIRM ---

    public function testConfirmConvertsReservationToShippedOrder(): void
    {
        $this->app->processLine('RESERVE;alice;PEN-BLACK;10;5');
        $this->app->processLine('CONFIRM;R2001');
        $this->assertContains('confirmed reservation R2001 order O1001 shipped to alice amount=15.0', $this->app->getEventLog());
        $this->app->processLine('COUNT;PEN-BLACK');
        $this->assertContains('count PEN-BLACK onHand=30 reserved=0 available=30', $this->app->getEventLog());
    }

    public function testConfirmNonExistentReservation(): void
    {
        $this->app->processLine('CONFIRM;R9999');
        $this->assertContains('cannot confirm R9999 because it does not exist', $this->app->getEventLog());
    }

    public function testConfirmAlreadyConfirmedReservation(): void
    {
        $this->app->processLine('RESERVE;alice;PEN-BLACK;5;5');
        $this->app->processLine('CONFIRM;R2001');
        $this->app->processLine('CONFIRM;R2001');
        $this->assertContains('cannot confirm R2001 because it is CONFIRMED', $this->app->getEventLog());
    }

    public function testConfirmReleasedReservation(): void
    {
        $this->app->processLine('RESERVE;alice;PEN-BLACK;5;5');
        $this->app->processLine('RELEASE;R2001');
        $this->app->processLine('CONFIRM;R2001');
        $this->assertContains('cannot confirm R2001 because it is RELEASED', $this->app->getEventLog());
    }

    // --- RELEASE ---

    public function testReleaseFreesReservedStock(): void
    {
        $this->app->processLine('RESERVE;alice;PEN-BLACK;10;5');
        $this->app->processLine('RELEASE;R2001');
        $this->assertContains('released reservation R2001', $this->app->getEventLog());
        $this->app->processLine('COUNT;PEN-BLACK');
        $this->assertContains('count PEN-BLACK onHand=40 reserved=0 available=40', $this->app->getEventLog());
    }

    public function testReleaseNonExistentReservation(): void
    {
        $this->app->processLine('RELEASE;R9999');
        $this->assertContains('cannot release R9999 because it does not exist', $this->app->getEventLog());
    }

    public function testReleaseAlreadyReleasedReservation(): void
    {
        $this->app->processLine('RESERVE;alice;PEN-BLACK;5;5');
        $this->app->processLine('RELEASE;R2001');
        $this->app->processLine('RELEASE;R2001');
        $this->assertContains('cannot release R2001 because it is RELEASED', $this->app->getEventLog());
    }

    // --- Expiry ---

    public function testReservationExpiresAutomaticallyAndFreesStock(): void
    {
        $time = 1000;
        $this->app->setClock(function () use (&$time): int { return $time; });
        $this->app->processLine('RESERVE;alice;PEN-BLACK;10;1'); // expiresAt = 1060
        $time = 1060;
        $this->app->processLine('COUNT;PEN-BLACK');
        $this->assertContains('reservation R2001 expired', $this->app->getEventLog());
        $this->assertContains('count PEN-BLACK onHand=40 reserved=0 available=40', $this->app->getEventLog());
    }

    public function testExpiredReservationCannotBeConfirmed(): void
    {
        $time = 1000;
        $this->app->setClock(function () use (&$time): int { return $time; });
        $this->app->processLine('RESERVE;alice;PEN-BLACK;10;1'); // expiresAt = 1060
        $time = 1060;
        $this->app->processLine('CONFIRM;R2001');
        $this->assertContains('reservation R2001 expired', $this->app->getEventLog());
        $this->assertContains('cannot confirm R2001 because it is EXPIRED', $this->app->getEventLog());
    }

    public function testExpiredReservationCannotBeReleased(): void
    {
        $time = 1000;
        $this->app->setClock(function () use (&$time): int { return $time; });
        $this->app->processLine('RESERVE;alice;PEN-BLACK;10;1'); // expiresAt = 1060
        $time = 1060;
        $this->app->processLine('RELEASE;R2001');
        $this->assertContains('reservation R2001 expired', $this->app->getEventLog());
        $this->assertContains('cannot release R2001 because it is EXPIRED', $this->app->getEventLog());
    }

    public function testActiveReservationDoesNotExpireBeforeDeadline(): void
    {
        $time = 1000;
        $this->app->setClock(function () use (&$time): int { return $time; });
        $this->app->processLine('RESERVE;alice;PEN-BLACK;10;5'); // expiresAt = 1300
        $time = 1299;
        $this->app->processLine('COUNT;PEN-BLACK');
        $this->assertNotContains('reservation R2001 expired', $this->app->getEventLog());
        $this->assertContains('count PEN-BLACK onHand=40 reserved=10 available=30', $this->app->getEventLog());
    }

    public function testReserveAfterExpiredReservationSucceeds(): void
    {
        $time = 1000;
        $this->app->setClock(function () use (&$time): int { return $time; });
        $this->app->processLine('RESERVE;alice;NOTE-A5;15;1'); // reserves all 15, expiresAt = 1060
        $time = 1060;
        $this->app->processLine('RESERVE;bob;NOTE-A5;15;5');
        $this->assertContains('reservation R2001 expired', $this->app->getEventLog());
        $this->assertContains('reserved R2002 for bob sku=NOTE-A5 qty=15 expires=5min', $this->app->getEventLog());
    }
}
