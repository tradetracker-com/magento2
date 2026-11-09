<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Service\ProductData\AttributeCollector\Data;

use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\EntityManager\EntityMetadataInterface;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Store\Model\Store;
use TradeTracker\Connect\Service\ProductData\AttributeCollector\Data\Category;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The category collector turns Magento's "1/2/5/9" paths into the breadcrumb a channel receives,
 * and resolves the custom category attribute (google_product_category and friends) along it.
 *
 * Everything below the queries is pure: given category names, which categories are active and
 * which are excluded, it decides what a product's category path is. That decision is what a
 * channel maps the product on, so a dropped level or a leaked disabled category is a real defect.
 *
 * The queries are the collector's own; this drives the logic behind them with the state those
 * queries would have produced, which is the only way to reach it without a database.
 */
class CategoryPathTest extends TestCase
{
    private const ROOT = 2;

    /**
     * Two branches below root 2: Men (5) > Tops (9) > Tees (11), and Gear (6) > Bags (12).
     */
    private const NAMES = [
        5 => [0 => ['name' => 'Men', 'custom' => '']],
        9 => [0 => ['name' => 'Tops', 'custom' => 'Apparel > Tops']],
        11 => [0 => ['name' => 'Tees', 'custom' => '']],
        6 => [0 => ['name' => 'Gear', 'custom' => '']],
        12 => [0 => ['name' => 'Bags', 'custom' => '']],
    ];

    public function testAPathBecomesABreadcrumbBelowTheRoot(): void
    {
        $result = $this->mergeNames([42 => ['1/2/5/9/11']]);

        self::assertSame('Men > Tops > Tees', $result[42][0]['path']);
        self::assertSame(11, $result[42][0]['category_id'], 'the deepest category identifies the path');
    }

    public function testAProductInTwoBranchesGetsTwoPaths(): void
    {
        $result = $this->mergeNames([42 => ['1/2/5/9', '1/2/6/12']]);

        self::assertSame(
            ['Men > Tops', 'Gear > Bags'],
            array_column($result[42], 'path')
        );
    }

    public function testAPathOutsideThisStoresRootIsDropped(): void
    {
        // Root 2 does not appear in the path: another website's tree, not ours to export.
        $result = $this->mergeNames([42 => ['1/3/7/8']]);

        self::assertArrayNotHasKey(42, $result);
    }

    public function testTheRootItselfIsNotACategoryPath(): void
    {
        $result = $this->mergeNames([42 => ['1/2']]);

        self::assertArrayNotHasKey(42, $result);
    }

    public function testACategoryWithoutANameIsSkippedInsideThePath(): void
    {
        // 99 is not in the name map: unknown to this store, so it adds no level.
        $result = $this->mergeNames([42 => ['1/2/5/99/9']]);

        self::assertSame('Men > Tops', $result[42][0]['path']);
    }

    public function testAPathWhoseDeepestCategoryIsDisabledIsDropped(): void
    {
        $result = $this->mergeNames([42 => ['1/2/5/9']], ['nonActiveCategories' => [0 => [9 => true]]]);

        self::assertArrayNotHasKey(42, $result, 'a disabled category must not reach the feed');
    }

    public function testAPathWhoseDeepestCategoryIsExcludedIsDropped(): void
    {
        $result = $this->mergeNames([42 => ['1/2/5/9']], ['excludedCategories' => [0 => [9 => true]]]);

        self::assertArrayNotHasKey(42, $result);
    }

    public function testACategoryDisabledOnThisStoreOnlyIsAlsoDropped(): void
    {
        // Both maps are keyed store first: a category switched off for one store view must not be
        // exported in that store's feed, even though the default scope has it on.
        $result = $this->mergeNames(
            [42 => ['1/2/5/9']],
            ['storeId' => 1, 'nonActiveCategories' => [1 => [9 => true]]]
        );

        self::assertArrayNotHasKey(42, $result);
    }

    public function testACategoryDisabledOnAnotherStoreStillExports(): void
    {
        $result = $this->mergeNames(
            [42 => ['1/2/5/9']],
            ['storeId' => 1, 'nonActiveCategories' => [2 => [9 => true]]]
        );

        self::assertSame('Men > Tops', $result[42][0]['path']);
    }

    public function testRawFormatReportsTheLevelNameAndCustomValue(): void
    {
        $result = $this->mergeNames(
            [42 => ['1/2/5/9']],
            ['format' => 'raw', 'customAttribute' => 'custom']
        );

        self::assertSame(
            [
                'level' => 2,
                'name' => 'Tops',
                'custom' => 'Apparel > Tops',
                'path' => 'Men > Tops',
                'custom_path' => 'Men > Apparel > Tops',
                'category_id' => 9,
            ],
            $result[42][0]
        );
    }

    /**
     * A channel that exports the merchant's own tree, with a per-category name override, reads
     * custom_path: every segment is the category's custom value where it has one and its name
     * where it has not. Nothing is inherited from deeper categories -- that is what 'custom' does.
     */
    public function testTheCustomPathReplacesOnlyTheSegmentsThatHaveACustomValue(): void
    {
        $result = $this->mergeNames(
            [42 => ['1/2/5/9/11']],
            ['format' => 'raw', 'customAttribute' => 'custom']
        );

        self::assertSame('Men > Apparel > Tops > Tees', $result[42][0]['custom_path']);
        self::assertSame('Men > Tops > Tees', $result[42][0]['path']);
    }

    public function testWithoutACustomAttributeTheCustomPathIsThePath(): void
    {
        $result = $this->mergeNames([42 => ['1/2/5/9']], ['format' => 'raw']);

        self::assertSame($result[42][0]['path'], $result[42][0]['custom_path']);
    }

    public function testTheLevelCountsEverythingBelowTheRootEvenWhenACategoryHasNoName(): void
    {
        // The channel is told how deep the product sits, not how many names we could resolve.
        $result = $this->mergeNames(
            [42 => ['1/2/5/99/9']],
            ['format' => 'raw', 'customAttribute' => 'custom']
        );

        self::assertSame(3, $result[42][0]['level']);
    }

    public function testACategoryWithoutACustomValueInheritsItFromDeeper(): void
    {
        // Men has no google_product_category of its own; the one on Tops below it applies.
        $result = $this->mergeNames(
            [42 => ['1/2/5/9']],
            ['format' => 'raw', 'customAttribute' => 'custom']
        );

        self::assertSame('Apparel > Tops', $result[42][0]['custom']);
    }

    public function testWithoutAnyCustomValueTheRawRowSaysSo(): void
    {
        $result = $this->mergeNames(
            [42 => ['1/2/6/12']],
            ['format' => 'raw', 'customAttribute' => 'custom']
        );

        self::assertFalse($result[42][0]['custom'], 'end() of an empty list is false, not a value');
    }

    public function testAStoreSpecificNameWinsOverTheDefaultOne(): void
    {
        $names = self::NAMES;
        $names[9][1] = ['name' => 'Bovenstukken', 'custom' => ''];

        $result = $this->mergeNames([42 => ['1/2/5/9']], ['storeId' => 1, 'categoryNames' => $names]);

        self::assertSame('Men > Bovenstukken', $result[42][0]['path']);
    }

    public function testAProductWithoutCategoriesGetsNoRow(): void
    {
        self::assertSame([], $this->mergeNames([42 => [null]]));
    }

    /**
     * Runs mergeNames() with the state the collector's own queries would have left behind.
     *
     * @param array $paths entity id => category paths
     * @param array $state property overrides
     * @return array
     */
    private function mergeNames(array $paths, array $state = []): array
    {
        $metadata = $this->createMock(EntityMetadataInterface::class);
        $metadata->method('getLinkField')->willReturn('entity_id');
        $metadataPool = $this->createMock(MetadataPool::class);
        $metadataPool->method('getMetadata')->with(CategoryInterface::class)->willReturn($metadata);

        $collector = new Category($this->createMock(ResourceConnection::class), $metadataPool);

        // The concrete Store, not StoreInterface: getRootCategoryId() is only on the model, which
        // is what Magento hands the collector in practice.
        $store = $this->createMock(Store::class);
        $store->method('getRootCategoryId')->willReturn($state['rootCategoryId'] ?? self::ROOT);

        $defaults = [
            'store' => $store,
            'storeId' => 0,
            'format' => 'default',
            'customAttribute' => '',
            'categoryNames' => self::NAMES,
            'nonActiveCategories' => [],
            'excludedCategories' => [],
            'categoryIds' => [],
        ];
        unset($state['rootCategoryId']);

        $reflection = new ReflectionClass(Category::class);
        foreach (array_merge($defaults, $state) as $name => $value) {
            $reflection->getProperty($name)->setValue($collector, $value);
        }

        return $reflection->getMethod('mergeNames')->invoke($collector, $paths);
    }
}
