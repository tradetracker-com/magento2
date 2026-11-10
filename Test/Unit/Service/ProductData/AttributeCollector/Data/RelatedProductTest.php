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
use TradeTracker\Connect\Service\ProductData\AttributeCollector\Data\RelatedProduct;
use PHPUnit\Framework\TestCase;

/**
 * Magento's related products become Google's related_product: a relationship, how the other
 * product is identified, and the identifier itself.
 *
 * Google accepts thirty per product and ignores the rest, so the cap is ours to apply — and it has
 * to keep the merchant's own order, because which thirty of a hundred accessories a shopper is
 * shown is a merchandising decision, not an accident of row ids.
 */
class RelatedProductTest extends TestCase
{
    public function testALinkBecomesARelatedProductEntry(): void
    {
        $result = $this->collect([
            ['product_id' => 1, 'linked_product_id' => 2, 'position' => 1],
        ]);

        self::assertSame([
            ['relationship_type' => 'accessory', 'identifier_type' => 'id', 'identifier' => '2'],
        ], $result[1]);
    }

    public function testTheConfiguredRelationshipIsWhatIsExported(): void
    {
        $result = $this->collect(
            [['product_id' => 1, 'linked_product_id' => 2, 'position' => 1]],
            'often_bought_with'
        );

        self::assertSame('often_bought_with', $result[1][0]['relationship_type']);
    }

    public function testEveryLinkOfAProductIsKeptInTheOrderTheQueryReturns(): void
    {
        $result = $this->collect([
            ['product_id' => 1, 'linked_product_id' => 5, 'position' => 1],
            ['product_id' => 1, 'linked_product_id' => 9, 'position' => 2],
            ['product_id' => 1, 'linked_product_id' => 7, 'position' => 3],
        ]);

        self::assertSame(['5', '9', '7'], array_column($result[1], 'identifier'));
    }

    public function testLinksAreKeptApartPerProduct(): void
    {
        $result = $this->collect([
            ['product_id' => 1, 'linked_product_id' => 5, 'position' => 1],
            ['product_id' => 2, 'linked_product_id' => 6, 'position' => 1],
        ]);

        self::assertSame(['5'], array_column($result[1], 'identifier'));
        self::assertSame(['6'], array_column($result[2], 'identifier'));
    }

    public function testAProductWithMoreLinksThanGoogleAcceptsKeepsTheFirstThirty(): void
    {
        $rows = [];
        for ($i = 1; $i <= 45; $i++) {
            $rows[] = ['product_id' => 1, 'linked_product_id' => 100 + $i, 'position' => $i];
        }

        $result = $this->collect($rows);

        self::assertCount(RelatedProduct::MAX_PER_PRODUCT, $result[1]);
        self::assertSame('101', $result[1][0]['identifier'], 'the merchant ordering decides which thirty');
        self::assertSame('130', $result[1][29]['identifier']);
    }

    public function testAProductWithoutLinksIsAbsentRatherThanEmpty(): void
    {
        self::assertSame([], $this->collect([]));
    }

    public function testAnEmptyBatchAsksTheDatabaseNothing(): void
    {
        $resource = $this->createMock(ResourceConnection::class);
        $resource->expects(self::never())->method('getConnection');

        self::assertSame([], $this->collector($resource)->execute([]));
    }

    /**
     * @param array<int, array<string, mixed>> $rows what the link query returns
     * @param string $relationshipType
     * @return array<int, array<int, array<string, string>>>
     */
    private function collect(array $rows, string $relationshipType = 'accessory'): array
    {
        $select = $this->createMock(Select::class);
        foreach (['from', 'joinLeft', 'where', 'order'] as $method) {
            $select->method($method)->willReturnSelf();
        }

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturn($rows);

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        return $this->collector($resource)->execute([1, 2], $relationshipType);
    }

    private function collector(ResourceConnection $resource): RelatedProduct
    {
        $metadata = $this->createMock(EntityMetadataInterface::class);
        $metadata->method('getLinkField')->willReturn('entity_id');
        $metadataPool = $this->createMock(MetadataPool::class);
        $metadataPool->method('getMetadata')->with(ProductInterface::class)->willReturn($metadata);

        return new RelatedProduct(
            $resource,
            $this->createMock(ModuleManager::class),
            $this->createMock(WebsiteRepositoryInterface::class),
            $this->createMock(StoreRepositoryInterface::class),
            $this->createMock(LogRepository::class),
            $metadataPool
        );
    }
}
