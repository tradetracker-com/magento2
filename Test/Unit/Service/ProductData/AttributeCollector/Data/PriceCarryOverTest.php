<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Service\ProductData\AttributeCollector\Data;

use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogRule\Model\ResourceModel\RuleFactory;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;
use TradeTracker\Connect\Service\ProductData\AttributeCollector\Data\Price;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Every price this collector works out lives in a property of the class, and the per-type setters
 * return early when a product has no price to resolve: an unsalable configurable has no
 * final_price, so there is nothing to report.
 *
 * Without clearing that state per product, those properties still held the previous product's
 * figures, and the product was published with the price of whichever product the batch happened to
 * process before it. In a feed of 2000 products a single out-of-stock configurable advertised a
 * neighbour's price - and it only showed up in a batch, never when the product was collected on
 * its own, which is how it stayed in eight modules.
 */
class PriceCarryOverTest extends TestCase
{
    /**
     * The price properties and the figure a previous product would have left in each.
     */
    private const CARRIED_OVER = [
        'price' => 39.95,
        'finalPrice' => 39.95,
        'specialPrice' => 29.95,
        'salesPrice' => 29.95,
        'minPrice' => 39.95,
        'maxPrice' => 49.95,
        'totalPrice' => 39.95,
    ];

    public function testAnUnsalableConfigurableReportsNoPriceOfItsOwn(): void
    {
        $collector = $this->collector();
        $this->seedPreviousProduct($collector);

        $result = $collector->execute([1], $this->store());

        self::assertArrayHasKey(1, $result);
        foreach (['price', 'final_price', 'sales_price', 'min_price', 'max_price', 'special_price', 'total_price'] as $field) {
            self::assertNull(
                $result[1][$field],
                sprintf('%s carried over from the product before it in the batch', $field)
            );
        }
    }

    public function testThePriceStateIsClearedBeforeEachProduct(): void
    {
        $collector = $this->collector();
        $this->seedPreviousProduct($collector);

        $collector->execute([1], $this->store());

        foreach (array_keys(self::CARRIED_OVER) as $property) {
            self::assertNull($this->readPrivate($collector, $property), $property . ' was not cleared');
        }
    }

    /**
     * A collector whose product collection holds one unsalable configurable: a product type whose
     * setter returns before assigning anything.
     */
    private function collector(): Price
    {
        $collector = (new ReflectionClass(Price::class))->newInstanceWithoutConstructor();

        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn(1);
        $product->method('getTypeId')->willReturn('configurable');
        // No final_price is how the collector recognises a product that cannot be sold.
        $product->method('getData')->willReturnCallback(
            static fn (string $key) => $key === 'final_price' ? null : null
        );

        $this->setPrivate($collector, 'collectionFactory', $this->collectionFactory($product));
        $this->setPrivate($collector, 'resourceRuleFactory', $this->ruleFactory());
        $this->setPrivate($collector, 'catalogHelper', $this->catalogHelper());
        $this->setPrivate($collector, 'localeDate', $this->createMock(TimezoneInterface::class));
        $this->setPrivate($collector, 'commonPriceModel', $this->commonPriceModel());

        return $collector;
    }

    /**
     * A tax helper that hands back the price it is given, so the tax percentage works out to 1 and
     * a carried-over figure reaches the result row instead of being multiplied away.
     */
    private function catalogHelper(): CatalogHelper
    {
        $helper = $this->createMock(CatalogHelper::class);
        $helper->method('getTaxPrice')->willReturnCallback(
            static fn ($product, $price) => $price
        );

        return $helper;
    }

    /**
     * A catalog-rule resource that reports no rule price, which is the common case.
     */
    private function ruleFactory(): RuleFactory
    {
        $rule = $this->getMockBuilder(\Magento\CatalogRule\Model\ResourceModel\Rule::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getRulePrice'])
            ->getMock();
        $rule->method('getRulePrice')->willReturn(false);

        $factory = $this->createMock(RuleFactory::class);
        $factory->method('create')->willReturn($rule);

        return $factory;
    }

    /**
     * A collection factory whose collection holds exactly the product the test provides.
     */
    private function collectionFactory(Product $product): CollectionFactory
    {
        $select = $this->createMock(\Magento\Framework\DB\Select::class);
        $select->method('joinLeft')->willReturnSelf();

        $collection = $this->getMockBuilder(ProductCollection::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'addFieldToSelect', 'addFieldToFilter', 'addStoreFilter',
                'setStoreId', 'getSelect', 'getTable', 'getIterator',
            ])
            ->getMock();
        foreach (['addFieldToSelect', 'addFieldToFilter', 'addStoreFilter', 'setStoreId'] as $method) {
            $collection->method($method)->willReturnSelf();
        }
        $collection->method('getSelect')->willReturn($select);
        $collection->method('getTable')->willReturnArgument(0);
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$product]));

        $factory = $this->createMock(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        return $factory;
    }

    private function store(): StoreInterface
    {
        $currency = $this->createMock(\Magento\Directory\Model\Currency::class);
        $currency->method('getRate')->willReturn(1.0);

        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(1);
        $store->method('getWebsiteId')->willReturn(1);
        $store->method('getDefaultCurrencyCode')->willReturn('EUR');
        $store->method('getBaseCurrencyCode')->willReturn('EUR');
        $store->method('getBaseCurrency')->willReturn($currency);

        return $store;
    }

    private function commonPriceModel(): object
    {
        return $this->getMockBuilder(\Magento\Catalog\Model\Product\CatalogPrice::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function seedPreviousProduct(Price $collector): void
    {
        foreach (self::CARRIED_OVER as $property => $value) {
            $this->setPrivate($collector, $property, $value);
        }
    }

    private function setPrivate(object $object, string $property, $value): void
    {
        $reflection = new ReflectionClass($object);
        while (!$reflection->hasProperty($property)) {
            $reflection = $reflection->getParentClass();
        }
        $reflectionProperty = $reflection->getProperty($property);
        $reflectionProperty->setValue($object, $value);
    }

    private function readPrivate(object $object, string $property)
    {
        $reflection = new ReflectionClass($object);
        while (!$reflection->hasProperty($property)) {
            $reflection = $reflection->getParentClass();
        }

        return $reflection->getProperty($property)->getValue($object);
    }
}
