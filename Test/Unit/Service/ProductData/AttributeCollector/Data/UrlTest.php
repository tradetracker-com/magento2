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
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use TradeTracker\Connect\Service\ProductData\AttributeCollector\Data\Url;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * A feed is generated per store view, and a shop with "Add Store Code to Urls" on tells its store
 * views apart by that code. The domain comes from web/unsecure/base_url of the store (stores on
 * their own domain), but that base URL never carries the code; only Magento's link URL
 * does. Every link of a non-default store view opened the default store, with and without a URL
 * rewrite, on every feed module that shares this collector.
 */
class UrlTest extends TestCase
{
    public function testTheStoreCodeIsInTheLinkWhenTheShopUsesIt(): void
    {
        $links = $this->collect(useStoreInUrl: true);

        self::assertSame('https://shop.test/hyva_nl/aim-analog-watch.html', $links[36]);
        self::assertSame('https://shop.test/hyva_nl/catalog/product/view/id/37', $links[37], 'the fallback link too');
    }

    public function testNoStoreCodeWhenTheShopDoesNotUseIt(): void
    {
        $links = $this->collect(useStoreInUrl: false);

        self::assertSame('https://shop.test/aim-analog-watch.html', $links[36]);
        self::assertSame('https://shop.test/catalog/product/view/id/37', $links[37]);
    }

    /**
     * @return array<int, string>
     */
    private function collect(bool $useStoreInUrl): array
    {
        $select = $this->createMock(Select::class);
        foreach (['from', 'join', 'where'] as $method) {
            $select->method($method)->willReturnSelf();
        }
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturn([
            ['entity_id' => 36, 'request_path' => 'aim-analog-watch.html'],
        ]);
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(2);
        $store->method('getCode')->willReturn('hyva_nl');
        $store->method('isUseStoreInUrl')->willReturn($useStoreInUrl);
        $store->method('getBaseUrl')->with(UrlInterface::URL_TYPE_WEB)->willReturn('https://shop.test/');

        $collector = (new ReflectionClass(Url::class))->newInstanceWithoutConstructor();
        foreach (['resource' => $resource, 'linkField' => 'entity_id'] as $property => $value) {
            $reflection = new \ReflectionProperty(
                $property === 'resource' || $property === 'linkField'
                    ? get_parent_class($collector)
                    : $collector,
                $property
            );
            $reflection->setValue($collector, $value);
        }

        return $collector->execute([36, 37], 'product', $store, [36, 37]);
    }
}
