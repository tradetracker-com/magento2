<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Service\ProductData;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use TradeTracker\Connect\Service\ProductData\Filter;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The visibility filter restricts the feed to the visibilities the merchant selected.
 *
 * Switched on with nothing selected it used to build "value IN ()", which is not valid SQL, and
 * feed generation died with a syntax error rather than producing a feed. That state is reachable
 * from the admin, and from a store-scope row that is empty while the default scope has a value.
 * A restriction to nothing is no restriction, so every visibility passes.
 */
class VisibilityFilterTest extends TestCase
{
    public function testAnEmptySelectionPlacesNoRestriction(): void
    {
        $bound = $this->visibilitiesQueriedFor(['filter_by_visibility' => true, 'visibility' => []]);

        self::assertSame([1, 2, 3, 4], $bound, 'an empty selection has to pass every visibility');
    }

    public function testAnEmptyStringSelectionPlacesNoRestriction(): void
    {
        $bound = $this->visibilitiesQueriedFor(['filter_by_visibility' => true, 'visibility' => '']);

        self::assertSame([1, 2, 3, 4], $bound);
    }

    public function testTheSelectedVisibilitiesAreTheOnesQueried(): void
    {
        $bound = $this->visibilitiesQueriedFor(['filter_by_visibility' => true, 'visibility' => '2,4']);

        self::assertSame([2, 4], $bound);
    }

    public function testTheFilterSwitchedOffPassesEveryVisibility(): void
    {
        $bound = $this->visibilitiesQueriedFor(['filter_by_visibility' => false, 'visibility' => '2']);

        self::assertSame([1, 2, 3, 4], $bound);
    }

    /**
     * The visibilities the query was built with, read off the `base.value IN (?)` condition.
     *
     * @param array $filter
     * @return int[]
     */
    private function visibilitiesQueriedFor(array $filter): array
    {
        $bound = [];

        $select = $this->createMock(Select::class);
        foreach (['distinct', 'from', 'joinLeft'] as $method) {
            $select->method($method)->willReturnSelf();
        }
        $select->method('where')->willReturnCallback(
            static function (string $condition, $value = null) use ($select, &$bound) {
                if (str_contains($condition, 'base.value IN') && is_array($value)) {
                    $bound = $value;
                }

                return $select;
            }
        );

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchCol')->willReturn([]);

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $instance = (new ReflectionClass(Filter::class))->newInstanceWithoutConstructor();
        $this->setPrivate($instance, 'resourceConnection', $resource);
        $this->setPrivate($instance, 'visibilityAttributeId', 99);
        $this->setPrivate($instance, 'entityId', 'entity_id');

        $method = (new ReflectionClass(Filter::class))->getMethod('filterVisibility');
        $method->invoke($instance, $filter, 1);

        return $bound;
    }

    private function setPrivate(object $object, string $property, $value): void
    {
        $reflection = new ReflectionClass($object);
        while (!$reflection->hasProperty($property)) {
            $reflection = $reflection->getParentClass();
        }
        $reflection->getProperty($property)->setValue($object, $value);
    }
}
