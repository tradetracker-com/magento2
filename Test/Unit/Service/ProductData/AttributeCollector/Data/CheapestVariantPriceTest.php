<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Service\ProductData\AttributeCollector\Data;

use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\CatalogPrice;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogRule\Model\ResourceModel\Rule;
use Magento\CatalogRule\Model\ResourceModel\RuleFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\EntityManager\EntityMetadataInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;
use TradeTracker\Connect\Service\ProductData\AttributeCollector\Data\Price;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * A configurable without an explicit price choice takes the final price of its cheapest buyable
 * variant. The index columns of the parent cannot answer that: with a catalog rule on the parent,
 * MP01 indexes as final 28.00 and min 21.00 while every variant costs 28.00. The answer comes from
 * the variants' own index rows, in one query for the whole batch rather than a product load per
 * parent, which cost about 7 ms per configurable.
 */
class CheapestVariantPriceTest extends TestCase
{
    private const PARENT = 1;

    /** @var array<int, array<int, mixed>> */
    private array $joins = [];
    /** @var array<int, array<int, mixed>> */
    private array $wheres = [];
    private ?string $groupedBy = null;

    public function testTheParentTakesTheCheapestVariantsFinalPrice(): void
    {
        $result = $this->collector(variantPrices: [self::PARENT => '28.0000'])->execute([self::PARENT], $this->store());

        self::assertSame(28.0, $result[self::PARENT]['final_price']);
        self::assertSame(35.0, $result[self::PARENT]['price']);
    }

    public function testWithoutABuyableVariantTheParentKeepsItsOwnFinalPrice(): void
    {
        $result = $this->collector(variantPrices: [])->execute([self::PARENT], $this->store());

        self::assertSame(28.0, $result[self::PARENT]['final_price']);
    }

    public function testAnExplicitMinimumStillComesFromTheParentsIndexRow(): void
    {
        $result = $this->collector(variantPrices: [self::PARENT => '28.0000'])
            ->execute([self::PARENT], $this->store(), '', '', 'min');

        self::assertSame(21.0, $result[self::PARENT]['final_price']);
    }

    public function testOneQueryReadsTheVariantsIndexRowsForTheWebsiteAndSkipsWhatIsOutOfStock(): void
    {
        $this->collector(variantPrices: [])->execute([self::PARENT, 2, 3], $this->store());

        $tables = array_map(static fn (array $join) => array_key_first($join[0]), $this->joins);
        self::assertSame(['parent', 'price_index', 'stock'], $tables);
        self::assertStringContainsString('price_index.website_id = 7', $this->joins[1][1]);
        self::assertStringContainsString('customer_group_id = 0', $this->joins[1][1]);
        self::assertStringContainsString('MIN(price_index.final_price)', (string)$this->joins[1][2]['final_price']);
        self::assertStringContainsString('stock.stock_status = 1', $this->joins[2][1]);
        self::assertSame(['parent.entity_id IN (?)', [self::PARENT, 2, 3]], array_slice($this->wheres[0], 0, 2));
        self::assertSame('parent.entity_id', $this->groupedBy);
    }

    /**
     * @param array<int, string> $variantPrices what the batch query answers: parent id => price
     */
    private function collector(array $variantPrices): Price
    {
        $this->joins = [];
        $this->wheres = [];
        $this->groupedBy = null;

        $collector = (new ReflectionClass(Price::class))->newInstanceWithoutConstructor();

        // A configurable with a catalog rule on itself: price 35, final 28, min 21 in its own index row.
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn(self::PARENT);
        $product->method('getTypeId')->willReturn('configurable');
        $index = ['price' => '35.0000', 'final_price' => '28.0000', 'min_price' => '21.0000', 'max_price' => '35.0000'];
        $product->method('getData')->willReturnCallback(static fn (?string $key = null) => $key === null ? $index : ($index[$key] ?? null));
        $product->method('offsetGet')->willReturnCallback(static fn ($key) => $index[$key] ?? null);

        $this->setPrivate($collector, 'collectionFactory', $this->collectionFactory($product, $variantPrices));
        $this->setPrivate($collector, 'resourceRuleFactory', $this->ruleFactory());
        $this->setPrivate($collector, 'catalogHelper', $this->catalogHelper());
        $this->setPrivate($collector, 'localeDate', $this->createMock(TimezoneInterface::class));
        $this->setPrivate($collector, 'commonPriceModel', $this->getMockBuilder(CatalogPrice::class)->disableOriginalConstructor()->getMock());

        return $collector;
    }

    /**
     * @param array<int, string> $variantPrices
     */
    private function collectionFactory(Product $product, array $variantPrices): CollectionFactory
    {
        $select = $this->createMock(Select::class);
        $select->method('joinLeft')->willReturnSelf();

        $variantSelect = $this->createMock(Select::class);
        $variantSelect->method('from')->willReturnSelf();
        $variantSelect->method('join')->willReturnCallback(function (...$arguments) use ($variantSelect) {
            $this->joins[] = $arguments;
            return $variantSelect;
        });
        $variantSelect->method('where')->willReturnCallback(function (...$arguments) use ($variantSelect) {
            $this->wheres[] = $arguments;
            return $variantSelect;
        });
        $variantSelect->method('group')->willReturnCallback(function ($column) use ($variantSelect) {
            $this->groupedBy = $column;
            return $variantSelect;
        });

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($variantSelect);
        $connection->method('fetchPairs')->willReturn($variantPrices);

        $metadata = $this->createMock(EntityMetadataInterface::class);
        $metadata->method('getLinkField')->willReturn('entity_id');

        $collection = $this->getMockBuilder(ProductCollection::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'addFieldToSelect', 'addFieldToFilter', 'addStoreFilter', 'setStoreId',
                'getSelect', 'getTable', 'getIterator', 'getConnection', 'getProductEntityMetadata',
            ])
            ->getMock();
        foreach (['addFieldToSelect', 'addFieldToFilter', 'addStoreFilter', 'setStoreId'] as $method) {
            $collection->method($method)->willReturnSelf();
        }
        $collection->method('getSelect')->willReturn($select);
        $collection->method('getTable')->willReturnArgument(0);
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$product]));
        $collection->method('getConnection')->willReturn($connection);
        $collection->method('getProductEntityMetadata')->willReturn($metadata);

        $factory = $this->createMock(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        return $factory;
    }

    private function catalogHelper(): CatalogHelper
    {
        $helper = $this->createMock(CatalogHelper::class);
        $helper->method('getTaxPrice')->willReturnCallback(static fn ($product, $price) => $price);

        return $helper;
    }

    private function ruleFactory(): RuleFactory
    {
        $rule = $this->getMockBuilder(Rule::class)->disableOriginalConstructor()->onlyMethods(['getRulePrice'])->getMock();
        $rule->method('getRulePrice')->willReturn(false);
        $factory = $this->createMock(RuleFactory::class);
        $factory->method('create')->willReturn($rule);

        return $factory;
    }

    private function store(): StoreInterface
    {
        $currency = $this->createMock(\Magento\Directory\Model\Currency::class);
        $currency->method('getRate')->willReturn(1.0);

        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(1);
        $store->method('getWebsiteId')->willReturn(7);
        $store->method('getDefaultCurrencyCode')->willReturn('EUR');
        $store->method('getBaseCurrencyCode')->willReturn('EUR');
        $store->method('getBaseCurrency')->willReturn($currency);

        return $store;
    }

    private function setPrivate(object $object, string $property, $value): void
    {
        (new ReflectionClass($object))->getProperty($property)->setValue($object, $value);
    }
}
