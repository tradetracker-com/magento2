<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Model\ProductData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TradeTracker\Connect\Api\Config\System\FeedInterface;
use TradeTracker\Connect\Model\ProductData\Repository;
use TradeTracker\Connect\Service\ProductData\Collector;
use TradeTracker\Connect\Service\ProductData\Filter;
use TradeTracker\Connect\Service\ProductData\ImageProcessor;

/**
 * The row the feed writes for a collected product: the regular price is the "from" price, the
 * selling price is the price, and the static delivery time follows the stock state.
 */
#[CoversClass(Repository::class)]
class RepositoryTest extends TestCase
{
    public function testADiscountedProductCarriesItsRegularPriceAsFromPrice(): void
    {
        $row = $this->row(['price' => 32.0, 'final_price' => 24.0, 'discount_perc' => '25%', 'is_in_stock' => 1]);

        self::assertSame('32.00 EUR', $row['fromPrice']);
        self::assertSame('24.00 EUR', $row['price']);
        self::assertSame('25%', $row['discount']);
    }

    public function testTheStaticDeliveryTimeFollowsTheStockState(): void
    {
        self::assertSame('1-2 days', $this->row(['is_in_stock' => 1])['deliveryTime']);
        self::assertSame('5-7 days', $this->row(['is_in_stock' => 0])['deliveryTime']);
    }

    public function testTheAvailabilityFollowsTheStockState(): void
    {
        self::assertSame('in stock', $this->row(['is_in_stock' => 1])['availability']);
        self::assertSame('out of stock', $this->row(['is_in_stock' => 0])['availability']);
    }

    /**
     * @param array<string, mixed> $collected what the collector returns for the one product
     * @return array<string, mixed> the feed row
     */
    private function row(array $collected): array
    {
        $config = $this->createMock(FeedInterface::class);
        $config->method('getAttributes')->willReturn(['name' => 'name']);
        $config->method('getExtraFields')->willReturn([]);
        $config->method('getFilters')->willReturn([]);
        $config->method('getImageAttributes')->willReturn(['image' => 'image']);
        $config->method('getConfigProductsBehaviour')->willReturn([]);
        $config->method('getBundleProductsBehaviour')->willReturn([]);
        $config->method('getGroupedProductsBehaviour')->willReturn([]);
        $config->method('getBatchSize')->willReturn(100);
        $config->method('excludeOutOfStock')->willReturn(false);
        $config->method('getStaticFields')->willReturn([
            'currency' => 'EUR',
            'delivery_time' => ['is_in_stock == 1' => '1-2 days', 'is_in_stock != 1' => '5-7 days'],
        ]);

        $filter = $this->createMock(Filter::class);
        $filter->method('execute')->willReturn([7]);

        $collector = $this->createMock(Collector::class);
        $collector->method('execute')->willReturn([7 => $collected + ['product_id' => 7, 'sku' => 'S']]);

        $repository = new Repository($config, $filter, $collector, $this->createMock(ImageProcessor::class));
        $rows = $repository->getProductData(1);

        self::assertArrayHasKey(7, $rows);
        return $rows[7];
    }
}
