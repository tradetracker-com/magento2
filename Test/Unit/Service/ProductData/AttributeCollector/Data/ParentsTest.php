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
use TradeTracker\Connect\Service\ProductData\AttributeCollector\Data\Parents;
use PHPUnit\Framework\TestCase;

/**
 * catalog_product_relation links a child's entity_id to its parent's row_id. On Open Source the
 * two columns hold the same number; on Commerce they part ways the moment a scheduled update has
 * been applied to the parent. The collector reasons in entity ids, so the parent's entity id has
 * to come from catalog_product_entity, not from the relation row.
 */
class ParentsTest extends TestCase
{
    /** @var array<int, array<int, mixed>> */
    private array $fromCalls = [];
    /** @var array<int, array<int, mixed>> */
    private array $joinCalls = [];
    /** @var array<int, array<int, mixed>> */
    private array $whereClauses = [];

    public function testTheParentIdIsReadFromTheEntityTable(): void
    {
        $this->parents([], 'row_id')->execute([7589], false, 1);

        $relationFrom = null;
        foreach ($this->fromCalls as $call) {
            if (($call[0]['cpr'] ?? null) === 'catalog_product_relation') {
                $relationFrom = $call;
            }
        }
        self::assertNotNull($relationFrom, 'the relation table is not read');
        self::assertSame(['child_id' => 'cpr.child_id'], $relationFrom[1], 'cpr.parent_id is a row id');

        $entityJoin = null;
        foreach ($this->joinCalls as $call) {
            if (($call[0]['cpe'] ?? null) === 'catalog_product_entity') {
                $entityJoin = $call;
            }
        }
        self::assertNotNull($entityJoin, 'the parent entity is not joined');
        self::assertSame('cpe.row_id = cpr.parent_id', $entityJoin[1]);
        self::assertSame('cpe.entity_id', $entityJoin[2]['parent_id'] ?? null);
    }

    public function testChildrenMapToTheirParentsEntityIdAndType(): void
    {
        $result = $this->parents([
            ['child_id' => '7589', 'parent_id' => '1173', 'type_id' => 'configurable'],
            ['child_id' => '7589', 'parent_id' => '8847', 'type_id' => 'bundle'],
            ['child_id' => '7590', 'parent_id' => '1173', 'type_id' => 'configurable'],
        ])->execute([7589, 7590], false, 1);

        self::assertSame(
            [
                7589 => [1173 => 'configurable', 8847 => 'bundle'],
                7590 => [1173 => 'configurable'],
            ],
            $result
        );
    }

    public function testAFeedRunAsksForItsOwnChildrenOnly(): void
    {
        $this->parents([])->execute([7589, 7590], false, 1);

        $clauses = array_map(fn (array $arguments) => array_slice($arguments, 0, 2), $this->whereClauses);
        self::assertContains(['cpr.child_id IN (?)', [7589, 7590]], $clauses);
    }

    /**
     * @param array<int, array<string, mixed>> $rows what the relation query answers
     */
    private function parents(array $rows, string $linkField = 'entity_id'): Parents
    {
        $this->fromCalls = [];
        $this->joinCalls = [];
        $this->whereClauses = [];

        $select = $this->createMock(Select::class);
        foreach (['joinLeft', 'columns', 'group', 'order', 'limit', 'distinct'] as $method) {
            $select->method($method)->willReturnSelf();
        }
        $select->method('from')->willReturnCallback(function (...$arguments) use ($select) {
            $this->fromCalls[] = $arguments;
            return $select;
        });
        $select->method('join')->willReturnCallback(function (...$arguments) use ($select) {
            $this->joinCalls[] = $arguments;
            return $select;
        });
        $select->method('where')->willReturnCallback(function (...$arguments) use ($select) {
            $this->whereClauses[] = $arguments;
            return $select;
        });

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturn($rows);
        $connection->method('fetchCol')->willReturn([]);
        $connection->method('fetchOne')->willReturn('73');

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $metadata = $this->createMock(EntityMetadataInterface::class);
        $metadata->method('getLinkField')->willReturn($linkField);
        $metadataPool = $this->createMock(MetadataPool::class);
        $metadataPool->method('getMetadata')->with(ProductInterface::class)->willReturn($metadata);

        return new Parents(
            $resource,
            $this->createMock(ModuleManager::class),
            $this->createMock(WebsiteRepositoryInterface::class),
            $this->createMock(StoreRepositoryInterface::class),
            $this->createMock(LogRepository::class),
            $metadataPool
        );
    }
}
