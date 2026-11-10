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
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogRule\Model\ResourceModel\Rule;
use Magento\CatalogRule\Model\ResourceModel\RuleFactory;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use TradeTracker\Connect\Service\ProductData\AttributeCollector\Data\Price;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * A catalog rule price comes out of the database adapter as a numeric string, and the collector
 * compares it with the final price and keeps the lower of the two.
 *
 * Both halves of that matter. A catalog rule that is cheaper than the product's own final price is
 * the price the customer pays, so it is the price the channel has to be told; and the string the
 * adapter hands over has to become a float on the way, or the assignment is a TypeError that takes
 * the whole feed run down. That is not hypothetical: it happened on 2.4.9 the day these properties
 * were given their types, and no amount of catalog data on 2.4.7 (which had no matching rule)
 * showed it.
 */
class RulePriceTest extends TestCase
{
    public function testACheaperCatalogRulePriceBecomesTheFinalPrice(): void
    {
        $price = $this->collectorWithRulePrice('12.3400');
        $this->setPrivate($price, 'finalPrice', 20.0);

        self::assertSame(12.34, $this->getRulePrice($price));
        self::assertSame(12.34, $this->readPrivate($price, 'finalPrice'));
    }

    public function testADearerCatalogRulePriceLeavesTheFinalPriceAlone(): void
    {
        $price = $this->collectorWithRulePrice('25.0000');
        $this->setPrivate($price, 'finalPrice', 20.0);

        self::assertSame(20.0, $this->readPrivate($price, 'finalPrice'));
        self::assertSame(25.0, $this->getRulePrice($price));
    }

    public function testAProductWithoutAFinalPriceTakesTheRulePrice(): void
    {
        $price = $this->collectorWithRulePrice('12.3400');
        $this->setPrivate($price, 'finalPrice', null);

        $this->getRulePrice($price);

        self::assertSame(12.34, $this->readPrivate($price, 'finalPrice'));
    }

    public function testNoMatchingRuleLeavesTheFinalPriceAlone(): void
    {
        // The resource answers false when no rule applies to the product.
        $price = $this->collectorWithRulePrice(false);
        $this->setPrivate($price, 'finalPrice', 20.0);

        self::assertSame(0.0, $this->getRulePrice($price));
        self::assertSame(20.0, $this->readPrivate($price, 'finalPrice'));
    }

    /**
     * @param string|false $rulePrice what the catalog rule resource answers
     */
    private function collectorWithRulePrice($rulePrice): Price
    {
        $resource = $this->createMock(Rule::class);
        $resource->method('getRulePrice')->willReturn($rulePrice);

        $factory = $this->createMock(RuleFactory::class);
        $factory->method('create')->willReturn($resource);

        $localeDate = $this->createMock(TimezoneInterface::class);
        $localeDate->method('scopeDate')->willReturn(new \DateTime('2026-10-29'));

        return new Price(
            $this->createMock(CatalogPrice::class),
            $factory,
            $this->createMock(CatalogHelper::class),
            $localeDate,
            $this->createMock(CollectionFactory::class)
        );
    }

    private function getRulePrice(Price $price): float
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn(42);

        return (new ReflectionClass(Price::class))
            ->getMethod('getRulePrice')
            ->invoke($price, $product);
    }

    /**
     * @param mixed $value
     */
    private function setPrivate(Price $price, string $property, $value): void
    {
        (new ReflectionClass(Price::class))->getProperty($property)->setValue($price, $value);
    }

    /**
     * @return mixed
     */
    private function readPrivate(Price $price, string $property)
    {
        return (new ReflectionClass(Price::class))->getProperty($property)->getValue($price);
    }
}
