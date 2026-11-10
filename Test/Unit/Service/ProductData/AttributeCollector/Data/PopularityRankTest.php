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
use TradeTracker\Connect\Service\ProductData\AttributeCollector\Data\PopularityRank;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Google reads popularity_rank as a rank "as a percentage of total inventory": the best seller is
 * 100 and the worst is 0, with everything in between placed by how much of the measured catalogue
 * it outsells.
 *
 * The arithmetic is what a channel ranks the whole catalogue on, so the edges matter: a catalogue
 * of one, products that sold exactly the same amount, and products that sold nothing at all.
 */
class PopularityRankTest extends TestCase
{
    public function testTheBestSellerIsAHundredAndTheWorstIsZero(): void
    {
        $ranks = $this->rank([1 => 100.0, 2 => 50.0, 3 => 10.0]);

        self::assertSame(100.0, $ranks[1]);
        self::assertSame(0.0, $ranks[3]);
    }

    public function testTheMiddleIsPlacedByHowManyItOutsells(): void
    {
        // Of four products the second outsells two of the other three: 2/3 = 66.7%.
        $ranks = $this->rank([1 => 100.0, 2 => 50.0, 3 => 10.0, 4 => 1.0]);

        self::assertSame(66.7, $ranks[2]);
        self::assertSame(33.3, $ranks[3]);
    }

    public function testProductsThatSoldTheSameShareARank(): void
    {
        $ranks = $this->rank([1 => 100.0, 2 => 50.0, 3 => 50.0, 4 => 1.0]);

        self::assertSame($ranks[2], $ranks[3], 'equal sales cannot rank differently');
        self::assertSame(66.7, $ranks[2]);
        self::assertSame(0.0, $ranks[4], 'the tie does not move the product below it off the floor');
    }

    public function testTheOnlyProductThatSoldIsTheBestSeller(): void
    {
        // Dividing by (count - 1) would be a division by zero here.
        self::assertSame([7 => 100.0], $this->rank([7 => 3.0]));
    }

    public function testACatalogueThatSoldNothingHasNoRanks(): void
    {
        self::assertSame([], $this->rank([]));
    }

    public function testOnlyTheProductsInTheBatchAreReturned(): void
    {
        $collector = $this->seeded([10 => 100.0, 20 => 50.0, 30 => 0.0]);

        self::assertSame([20 => 50.0], $collector->execute([20], 1));
    }

    public function testAProductWithoutSalesIsLeftOutOfTheBatchResult(): void
    {
        // Not a zero: a product nobody bought was never measured against anything.
        self::assertSame([], $this->seeded([10 => 100.0])->execute([99], 1));
    }

    public function testAVariantInheritsTheRankOfTheProductItIsSoldAs(): void
    {
        // An order for a configurable is recorded against the configurable; the feed exports the
        // simple. Without this the whole catalogue of a shop that sells variants goes out unranked.
        $collector = $this->seeded([1428 => 87.5], [['child_id' => 9001, 'parent_id' => 1428]]);

        self::assertSame([9001 => 87.5], $collector->execute([9001], 1));
    }

    public function testAVariantOfTwoParentsTakesTheBetterRank(): void
    {
        $collector = $this->seeded(
            [1428 => 40.0, 1492 => 90.0],
            [['child_id' => 9001, 'parent_id' => 1428], ['child_id' => 9001, 'parent_id' => 1492]]
        );

        self::assertSame([9001 => 90.0], $collector->execute([9001], 1));
    }

    public function testAProductsOwnRankWinsOverItsParents(): void
    {
        $collector = $this->seeded(
            [9001 => 10.0, 1428 => 90.0],
            [['child_id' => 9001, 'parent_id' => 1428]]
        );

        self::assertSame([9001 => 10.0], $collector->execute([9001], 1));
    }

    public function testAnOrphanVariantStaysUnranked(): void
    {
        self::assertSame([], $this->seeded([1428 => 90.0], [])->execute([9001], 1));
    }

    public function testAnEmptyBatchAsksTheDatabaseNothing(): void
    {
        $resource = $this->createMock(ResourceConnection::class);
        $resource->expects(self::never())->method('getConnection');

        self::assertSame([], $this->collector($resource)->execute([], 1));
    }

    /**
     * A collector whose sales history is already worked out, and whose relation table answers with
     * the given rows.
     *
     * @param array<int, float> $ranks rank per product id, as the sales query would have left it
     * @param array<int, array{child_id: int, parent_id: int}>|null $relations
     */
    private function seeded(array $ranks, ?array $relations = null): PopularityRank
    {
        $select = $this->createMock(Select::class);
        foreach (['from', 'where'] as $method) {
            $select->method($method)->willReturnSelf();
        }

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturn($relations ?? []);

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $collector = $this->collector($resource);
        (new ReflectionClass(PopularityRank::class))
            ->getProperty('ranks')
            ->setValue($collector, [1 => $ranks]);

        return $collector;
    }

    /**
     * @param array<int, float> $sold quantity ordered per product id
     * @return array<int, float>
     */
    private function rank(array $sold): array
    {
        return (new ReflectionClass(PopularityRank::class))
            ->getMethod('rank')
            ->invoke($this->collector(), $sold);
    }

    private function collector(?ResourceConnection $resource = null): PopularityRank
    {
        $metadata = $this->createMock(EntityMetadataInterface::class);
        $metadata->method('getLinkField')->willReturn('entity_id');
        $metadataPool = $this->createMock(MetadataPool::class);
        $metadataPool->method('getMetadata')->with(ProductInterface::class)->willReturn($metadata);

        return new PopularityRank(
            $resource ?? $this->createMock(ResourceConnection::class),
            $this->createMock(ModuleManager::class),
            $this->createMock(WebsiteRepositoryInterface::class),
            $this->createMock(StoreRepositoryInterface::class),
            $this->createMock(LogRepository::class),
            $metadataPool
        );
    }
}
