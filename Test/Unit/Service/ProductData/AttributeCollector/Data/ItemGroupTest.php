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
use Magento\Store\Api\WebsiteRepositoryInterface;
use TradeTracker\Connect\Service\ProductData\AttributeCollector\Data\ItemGroup;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * `item_group_id` groups the variants of one product, and every member of a group has to carry the
 * fields that tell the members apart.
 *
 * Google: "Don't submit a value for the item group ID attribute if your product is not a variant",
 * and "use the parent SKU as the item group ID value for all variants of the same product, but
 * don't submit a parent SKU as a separate product". Meta: every variant sharing an item group ID
 * must have "every variant field (for example, size, color, gender, and pattern)" populated.
 *
 * A configurable has no size and no colour of its own, so a parent in the group is a member without
 * a single distinguishing attribute - exactly what Google reports as "Duplicate variant". This
 * collector therefore answers a value for children only.
 *
 * It shipped the other way twice: first with the parent carrying its own feed id (a group of one,
 * under a different key than its variants), then with the parent carrying the parent SKU (one group,
 * with an undistinguishable member). This test pins the third and correct shape.
 */
class ItemGroupTest extends TestCase
{
    public function testOnlyChildrenAreGivenAGroupId(): void
    {
        $collector = $this->collector([
            ['child_id' => 11, 'parent_id' => 1, 'sku' => 'MH01', 'parent_name' => 'Hoodie'],
            ['child_id' => 12, 'parent_id' => 1, 'sku' => 'MH01', 'parent_name' => 'Hoodie'],
        ]);

        $result = $collector->execute([1, 11, 12], false, 1);

        self::assertSame('MH01', $result[11]['item_group_id'] ?? null);
        self::assertSame('MH01', $result[12]['item_group_id'] ?? null);
        self::assertArrayNotHasKey(
            1,
            $result,
            'the parent is not a variant of anything and has to stay out of the group'
        );
    }

    public function testAProductWithoutAParentIsNotInTheResult(): void
    {
        $collector = $this->collector([]);

        $result = $collector->execute([99], false, 1);

        self::assertSame([], $result, 'a standalone simple is nobody\'s variant');
    }

    /**
     * @param array<int, array<string, mixed>> $rows what the relation query answers
     */
    private function collector(array $rows): ItemGroup
    {
        $select = $this->createMock(Select::class);
        foreach (['from', 'join', 'joinLeft', 'where', 'columns', 'group', 'order', 'limit', 'distinct'] as $method) {
            $select->method($method)->willReturnSelf();
        }

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturn($rows);
        $connection->method('fetchCol')->willReturn([]);
        $connection->method('fetchOne')->willReturn('73');

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $collector = (new ReflectionClass(ItemGroup::class))->newInstanceWithoutConstructor();
        $this->setPrivate($collector, 'resource', $resource);
        $this->setPrivate($collector, 'linkField', 'entity_id');
        $this->setPrivate($collector, 'websiteRepository', $this->createMock(WebsiteRepositoryInterface::class));

        return $collector;
    }

    private function setPrivate(object $object, string $property, $value): void
    {
        $reflection = new ReflectionClass($object);
        while (!$reflection->hasProperty($property)) {
            $parent = $reflection->getParentClass();
            if ($parent === false) {
                return;
            }
            $reflection = $parent;
        }
        $reflection->getProperty($property)->setValue($object, $value);
    }
}
