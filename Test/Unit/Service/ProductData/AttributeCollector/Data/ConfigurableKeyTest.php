<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Service\ProductData\AttributeCollector\Data;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\EntityManager\EntityMetadataInterface;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;
use TradeTracker\Connect\Api\Log\RepositoryInterface as LogRepository;
use TradeTracker\Connect\Service\ProductData\AttributeCollector\Data\ConfigurableKey;
use PHPUnit\Framework\TestCase;

/**
 * The configurable key is the "#attribute=value" suffix a child's URL gets so the parent page opens on that
 * child. A child without a value for the super attribute joins no entity_int row; such a row used to be
 * stored under a null store id, which PHP 8.5 reports as deprecated (and developer mode turns into a
 * fatal), and it never produced a usable key anyway.
 */
class ConfigurableKeyTest extends TestCase
{
    public function testChildrenGetTheirKeyPerStore(): void
    {
        $keys = $this->keysFor([
            ['child_id' => '10', 'parent_id' => '1', 'attribute_id' => '93', 'value' => '50', 'store_id' => '0'],
            ['child_id' => '10', 'parent_id' => '1', 'attribute_id' => '144', 'value' => '170', 'store_id' => '0'],
            ['child_id' => '10', 'parent_id' => '1', 'attribute_id' => '93', 'value' => '51', 'store_id' => '2'],
        ]);

        self::assertSame(['10' => ['1' => [0 => '#93=50&144=170', 2 => '#93=51']]], $keys);
    }

    public function testAChildWithoutAValueForTheSuperAttributeIsSkipped(): void
    {
        $keys = $this->keysFor([
            ['child_id' => '10', 'parent_id' => '1', 'attribute_id' => '93', 'value' => '50', 'store_id' => '0'],
            ['child_id' => '11', 'parent_id' => '1', 'attribute_id' => '93', 'value' => null, 'store_id' => null],
        ]);

        self::assertSame(['10' => ['1' => [0 => '#93=50']]], $keys);
        self::assertArrayNotHasKey('11', $keys);
    }

    public function testNoRelationsGiveNoKeys(): void
    {
        self::assertSame([], $this->keysFor([]));
    }

    private function keysFor(array $rows): array
    {
        $select = $this->createMock(Select::class);
        foreach (['from', 'joinLeft', 'where', 'join', 'columns'] as $method) {
            $select->method($method)->willReturnSelf();
        }

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        // the not-visible lookup first (empty), then the relation rows
        $connection->method('fetchCol')->willReturn([]);
        $connection->method('fetchAll')->willReturn($rows);

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $metadata = $this->createMock(EntityMetadataInterface::class);
        $metadata->method('getLinkField')->willReturn('entity_id');
        $metadataPool = $this->createMock(MetadataPool::class);
        $metadataPool->method('getMetadata')->with(ProductInterface::class)->willReturn($metadata);

        $collector = new ConfigurableKey(
            $resource,
            $this->createMock(ModuleManager::class),
            $this->createMock(WebsiteRepositoryInterface::class),
            $this->createMock(StoreRepositoryInterface::class),
            $this->createMock(LogRepository::class),
            $metadataPool
        );

        return $collector->execute([10, 11], false, 1);
    }
}
