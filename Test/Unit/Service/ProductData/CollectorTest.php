<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Service\ProductData;

use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\EntityManager\EntityMetadataInterface;
use Magento\Framework\EntityManager\MetadataPool;
use TradeTracker\Connect\Api\Log\RepositoryInterface as LogRepository;
use TradeTracker\Connect\Service\ProductData\AttributeCollector\Data\ConfigurableKey;
use TradeTracker\Connect\Service\ProductData\AttributeCollector\Data\Parents;
use TradeTracker\Connect\Service\ProductData\ChildSelection;
use TradeTracker\Connect\Service\ProductData\Collector;
use TradeTracker\Connect\Service\ProductData\Data;
use PHPUnit\Framework\TestCase;

/**
 * A Commerce catalogue after a scheduled update: every product's row_id differs from its entity_id.
 * The collector is handed entity ids and must hand entity ids on - to the data lookup, to the
 * parent selection and into the result - whatever the relation table keys its rows by.
 *
 * The catalogue is the one from the support case: variant 7589 of configurable 1173, also packed
 * into bundle 8847, with the configurable on "Only Linked Simple Products" and the bundle on
 * "Only Bundle Product".
 */
class CollectorTest extends TestCase
{
    private const VARIANT = 7589;
    private const CONFIGURABLE = 1173;
    private const BUNDLE = 8847;
    private const ROW = [self::VARIANT => 9001, self::CONFIGURABLE => 9100, self::BUNDLE => 9200];

    /** @var array<int, array<int, mixed>> */
    private array $whereClauses = [];
    /** @var int[][] */
    private array $dataLookups = [];
    /** @var string[] */
    private array $debugLog = [];

    public function testTheVariantFollowsItsConfigurableAndStaysNextToTheBundle(): void
    {
        $result = $this->collector()->execute(
            [self::VARIANT, self::BUNDLE],
            [],
            $this->parameters(feedIds: [self::VARIANT, self::BUNDLE, self::CONFIGURABLE]),
            1
        );

        self::assertSame([self::VARIANT, self::BUNDLE], array_keys($result));
        self::assertSame([], $this->debugLog, 'nothing was left out, so there is nothing to explain');
        self::assertSame(self::CONFIGURABLE, $result[self::VARIANT]['parent_id']);
        self::assertSame('Spareribs baby back ribs', $result[self::VARIANT]['parent_title']);
        self::assertSame('From the configurable', $result[self::VARIANT]['description']);
        self::assertSame('https://shop/spareribs.html#93=50', $result[self::VARIANT]['url']);
    }

    public function testEveryLookupIsMadeByEntityIdNotRowId(): void
    {
        $this->collector()->execute([self::VARIANT, self::BUNDLE], [], $this->parameters(), 1);

        self::assertSame(
            [[self::VARIANT, self::BUNDLE, self::CONFIGURABLE]],
            $this->dataLookups,
            'the parent outside the batch is loaded by its entity id'
        );

        $clauses = array_map(fn (array $arguments) => array_slice($arguments, 0, 2), $this->whereClauses);
        self::assertContains(['entity_id IN (?)', [self::VARIANT, self::BUNDLE, self::CONFIGURABLE]], $clauses);
        foreach ($clauses as $clause) {
            self::assertStringNotContainsString('row_id IN', (string)$clause[0]);
        }
    }

    /**
     * The configurable is kept in the feed here ("both"), but this batch holds only the variant and the
     * bundle. The configurable is loaded so the variant can inherit from it, and nothing more: its own
     * batch writes it. Returning it here wrote every such parent once per batch that held a child of it.
     */
    public function testAParentLoadedForInheritanceOnlyIsNotReturnedWithTheBatch(): void
    {
        $parameters = $this->parameters();
        $parameters['behaviour']['configurable']['use'] = 'both';

        $result = $this->collector()->execute([self::VARIANT, self::BUNDLE], [], $parameters, 1);

        self::assertSame([self::VARIANT, self::BUNDLE], array_keys($result));
        self::assertSame('From the configurable', $result[self::VARIANT]['description']);
        self::assertSame([[self::VARIANT, self::BUNDLE, self::CONFIGURABLE]], $this->dataLookups);
    }

    public function testAVariantWhoseEveryParentSaysParentOnlyLeavesTheFeed(): void
    {
        $parameters = $this->parameters();
        $parameters['behaviour']['configurable']['use'] = 'parent';

        $result = $this->collector()->execute([self::VARIANT, self::BUNDLE, self::CONFIGURABLE], [], $parameters, 1);

        self::assertSame([self::BUNDLE, self::CONFIGURABLE], array_keys($result));
        self::assertCount(1, $this->debugLog);
        self::assertStringContainsString('1 children left out', $this->debugLog[0]);
        self::assertStringContainsString('7589 (1173 configurable, 8847 bundle)', $this->debugLog[0]);
    }

    /**
     * @param int[]|null $feedIds
     * @return array<string, mixed>
     */
    private function parameters(?array $feedIds = null): array
    {
        $silent = [
            'use' => '',
            'use_parent_attributes' => [],
            'use_parent_url' => 0,
            'use_parent_images' => 0,
        ];
        $filters = ['exclude_disabled' => false, 'custom' => []];
        if ($feedIds !== null) {
            $filters['feed_entity_ids'] = $feedIds;
        }

        return [
            'filters' => $filters,
            'behaviour' => [
                'configurable' => [
                    'use' => 'simple',
                    'use_parent_attributes' => ['description'],
                    'use_parent_url' => 1,
                    'use_parent_images' => 0,
                ],
                'bundle' => ['use' => 'parent'] + $silent,
                'grouped' => $silent,
            ],
        ];
    }

    private function collector(): Collector
    {
        $this->whereClauses = [];
        $this->dataLookups = [];
        $this->debugLog = [];

        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnCallback(function (...$arguments) use ($select) {
            $this->whereClauses[] = $arguments;
            return $select;
        });

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchPairs')->willReturn(self::ROW);
        // unsetReplacedParentTypes asks for the parents of the replaced types, by entity id; the
        // configurable is one of them only when its own setting replaces it.
        $connection->method('fetchCol')->willReturnCallback(function () {
            foreach (array_reverse($this->whereClauses) as $clause) {
                if ($clause[0] === 'type_id IN (?)') {
                    return in_array('configurable', $clause[1], true) ? [self::CONFIGURABLE] : [];
                }
            }
            return [];
        });

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $metadata = $this->createMock(EntityMetadataInterface::class);
        $metadata->method('getLinkField')->willReturn('row_id');
        $metadataPool = $this->createMock(MetadataPool::class);
        $metadataPool->method('getMetadata')->with(CategoryInterface::class)->willReturn($metadata);

        // The relation lookup answers in entity ids, the bundle first: the database promises no order.
        $parents = $this->createMock(Parents::class);
        $parents->method('execute')->willReturn([
            self::VARIANT => [self::BUNDLE => 'bundle', self::CONFIGURABLE => 'configurable'],
        ]);

        $configurableKey = $this->createMock(ConfigurableKey::class);
        $configurableKey->method('execute')->willReturn([
            self::VARIANT => [self::CONFIGURABLE => [0 => '#93=50']],
        ]);

        $catalogue = [
            self::VARIANT => [
                'product_id' => self::VARIANT,
                'type_id' => 'simple',
                'title' => 'Spareribs baby back ribs (ca.500gr)',
                'url' => 'https://shop/spareribs-500.html',
            ],
            self::BUNDLE => [
                'product_id' => self::BUNDLE,
                'type_id' => 'bundle',
                'title' => 'Spareribs Kit "Hot"',
                'url' => 'https://shop/kit-hot.html',
            ],
            self::CONFIGURABLE => [
                'product_id' => self::CONFIGURABLE,
                'type_id' => 'configurable',
                'title' => 'Spareribs baby back ribs',
                'url' => 'https://shop/spareribs.html',
                'description' => 'From the configurable',
            ],
        ];
        $data = $this->createMock(Data::class);
        $data->method('execute')->willReturnCallback(function (array $entityIds) use ($catalogue) {
            $this->dataLookups[] = $entityIds;
            return array_intersect_key($catalogue, array_flip($entityIds));
        });

        $log = $this->createMock(LogRepository::class);
        $log->method('addDebugLog')->willReturnCallback(function (string $type, $data): void {
            $this->debugLog[] = $type . ': ' . $data;
        });

        return new Collector($data, $configurableKey, $parents, $resource, $metadataPool, new ChildSelection(), $log);
    }
}
