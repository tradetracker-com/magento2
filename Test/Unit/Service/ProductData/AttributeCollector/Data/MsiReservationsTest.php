<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Service\ProductData\AttributeCollector\Data;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;
use TradeTracker\Connect\Api\Log\RepositoryInterface as LogRepository;
use TradeTracker\Connect\Service\ProductData\AttributeCollector\Data\Stock;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * MSI keeps reserved quantities out of what is still sellable, so salable_qty is the stock figure
 * minus whatever orders already laid claim to.
 *
 * The reservations arrive as one map for the whole batch. Writing a single product's figure back
 * over that map left every later product reading an offset on an int, which answers null, so only
 * the first product in a feed run had its reservations subtracted and the rest published a
 * salable_qty that was too high by exactly the reserved amount - a sold-out product offered as
 * available. The bug shipped in five modules for a year because every copy inherited it.
 */
class MsiReservationsTest extends TestCase
{
    public function testEveryProductHasItsOwnReservationsSubtracted(): void
    {
        $stock = $this->collector(
            stockRows: [
                ['product_id' => 1, 'quantity' => 10, 'is_salable' => 1],
                ['product_id' => 2, 'quantity' => 10, 'is_salable' => 1],
                ['product_id' => 3, 'quantity' => 10, 'is_salable' => 1],
            ],
            reservations: [1 => 4, 2 => 6, 3 => 1]
        );

        $result = $this->getMsiStock($stock);

        self::assertSame(6, $result[1]['salable_qty']);
        self::assertSame(4, $result[2]['salable_qty'], 'the second product kept the first its figure');
        self::assertSame(9, $result[3]['salable_qty']);
    }

    public function testAProductWithoutReservationsKeepsItsFullQuantity(): void
    {
        $stock = $this->collector(
            stockRows: [
                ['product_id' => 1, 'quantity' => 5, 'is_salable' => 1],
                ['product_id' => 2, 'quantity' => 8, 'is_salable' => 1],
            ],
            reservations: [1 => 2]
        );

        $result = $this->getMsiStock($stock);

        self::assertSame(3, $result[1]['salable_qty']);
        self::assertSame(8, $result[2]['salable_qty']);
    }

    /**
     * A reservation is stored as a negative number; the collector flips it and never goes below nil.
     */
    public function testANegativeReservationDoesNotAddStock(): void
    {
        $stock = $this->collector(
            stockRows: [['product_id' => 1, 'quantity' => 5, 'is_salable' => 1]],
            reservations: [1 => -3]
        );

        self::assertSame(5, $this->getMsiStock($stock)[1]['salable_qty']);
    }

    /**
     * Without the flag the result is exactly what it always was, so every existing caller is
     * unaffected by the channel reporting being available.
     */
    public function testTheChannelBreakdownIsAbsentUnlessItIsAskedFor(): void
    {
        $stock = $this->collector(
            stockRows: [['product_id' => 1, 'quantity' => 5, 'is_salable' => 1]],
            reservations: [1 => 2],
            channels: [1, 2]
        );

        self::assertArrayNotHasKey('msi', $this->getMsiStock($stock)[1]);
    }

    public function testEveryChannelIsReportedAndTheStoresOwnIsMarked(): void
    {
        $stock = $this->collector(
            stockRows: [['product_id' => 1, 'quantity' => 5, 'is_salable' => 1]],
            reservations: [1 => 2],
            channels: [1, 7]
        );

        $msi = $this->getMsiStock($stock, true)[1]['msi'];

        self::assertSame([1, 7], array_keys($msi));
        self::assertTrue($msi[1]['own_channel'], 'stock 1 is the channel this store sells through');
        self::assertFalse($msi[7]['own_channel']);
        self::assertSame(3, $msi[1]['salable_qty'], 'reservations count per channel too');
    }

    /**
     * @param array<int, array<string, mixed>> $stockRows
     * @param array<int, int> $reservations
     * @param array<int, int> $channels Stock ids a website sells from.
     */
    private function collector(array $stockRows, array $reservations, array $channels = []): Stock
    {
        $select = $this->createMock(Select::class);
        foreach (['from', 'where', 'joinLeft', 'join', 'group', 'columns', 'limit'] as $method) {
            $select->method($method)->willReturnSelf();
        }

        // The columns getNoMsiStock() reads off cataloginventory_stock_item.
        $noMsiRows = array_map(
            static fn (array $row) => [
                'product_id' => $row['product_id'],
                'qty' => $row['quantity'],
                'stock_status' => 1,
                'manage_stock' => 1,
                'qty_increments' => 1,
                'min_sale_qty' => 1,
            ],
            $stockRows
        );

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('isTableExists')->willReturn(true);
        $connection->method('fetchOne')->willReturn('1');
        $connection->method('fetchPairs')->willReturn($reservations);
        $connection->method('fetchCol')->willReturn($channels);
        // collectMsi() runs first and getNoMsiStock() second; every channel after that reads the
        // same stock rows again, so answer by call order rather than with a fixed list.
        $call = 0;
        $connection->method('fetchAll')->willReturnCallback(
            static function () use (&$call, $stockRows, $noMsiRows) {
                $call++;

                return $call === 2 ? $noMsiRows : $stockRows;
            }
        );

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $stock = (new ReflectionClass(Stock::class))->newInstanceWithoutConstructor();
        $this->setPrivate($stock, 'resource', $resource);
        $this->setPrivate($stock, 'entityIds', array_column($stockRows, 'product_id'));
        $this->setPrivate($stock, 'store', $this->store());
        $this->setPrivate($stock, 'linkField', 'entity_id');
        $this->setPrivate($stock, 'websiteRepository', $this->websiteRepository());
        $this->setPrivate($stock, 'logRepository', $this->createMock(LogRepository::class));

        return $stock;
    }

    private function store(): StoreInterface
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $store->method('getWebsiteId')->willReturn(1);

        return $store;
    }

    private function websiteRepository(): WebsiteRepositoryInterface
    {
        $website = $this->createMock(WebsiteInterface::class);
        $website->method('getCode')->willReturn('base');

        $repository = $this->createMock(WebsiteRepositoryInterface::class);
        $repository->method('getById')->willReturn($website);

        return $repository;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getMsiStock(Stock $stock, bool $allChannels = false): array
    {
        $method = new \ReflectionMethod(Stock::class, 'getMsiStock');

        return $method->invoke($stock, $allChannels);
    }

    private function setPrivate(object $target, string $property, mixed $value): void
    {
        $class = new ReflectionClass($target);
        while (!$class->hasProperty($property) && $class->getParentClass()) {
            $class = $class->getParentClass();
        }
        $class->getProperty($property)->setValue($target, $value);
    }
}
