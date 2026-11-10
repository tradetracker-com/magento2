<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Setup\Patch\Data;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TradeTracker\Connect\Api\Config\System\FeedInterface;
use TradeTracker\Connect\Api\Config\System\PixelInterface;
use TradeTracker\Connect\Setup\Patch\Data\MigrateConfigPaths;

/**
 * Configuration written by 1.8.0 and older has to land on the paths 1.9.0 reads, or the merchant
 * loses a setting without a message: cron rejects a stored "custom", the grouped link setting
 * lived under a path the feed never read, and the currency code sat in the toggle's row.
 *
 * The patch runs three selects in a fixed order (cron frequency, grouped link, overwrite currency);
 * each test feeds one of them rows and leaves the other two empty.
 */
#[CoversClass(MigrateConfigPaths::class)]
class MigrateConfigPathsTest extends TestCase
{
    private const TABLE = 'core_config_data';

    /** @var array<int, array{string, array}> */
    private array $writes = [];

    public function testAStoredCustomBecomesTheCustomExpressionOfTheSameScope(): void
    {
        $this->apply(
            [[['config_id' => 7, 'scope' => 'default', 'scope_id' => 0]], [], []],
            ['*/15 * * * *']
        );

        self::assertSame(
            [['update', [self::TABLE, ['value' => '*/15 * * * *'], ['config_id = ?' => 7]]]],
            $this->writes
        );
    }

    public function testAStoredCustomWithoutAnExpressionIsRemovedSoTheDefaultApplies(): void
    {
        $this->apply(
            [[['config_id' => 7, 'scope' => 'default', 'scope_id' => 0]], [], []],
            [false]
        );

        self::assertSame([['delete', [self::TABLE, ['config_id = ?' => 7]]]], $this->writes);
    }

    public function testTheGroupedLinkMovesToThePathTheFeedReads(): void
    {
        $this->apply(
            [[], [['config_id' => 12, 'scope' => 'stores', 'scope_id' => 2]], []],
            [false]
        );

        self::assertSame(
            [['update', [self::TABLE, ['path' => FeedInterface::XML_PATH_GROUPED_LINK], ['config_id = ?' => 12]]]],
            $this->writes
        );
    }

    public function testAnOldGroupedLinkRowGivesWayToAValueAlreadyOnTheNewPath(): void
    {
        $this->apply(
            [[], [['config_id' => 12, 'scope' => 'stores', 'scope_id' => 2]], []],
            ['44']
        );

        self::assertSame([['delete', [self::TABLE, ['config_id = ?' => 12]]]], $this->writes);
    }

    public function testACurrencyCodeInTheToggleRowMovesToItsOwnPathAndTurnsTheToggleOn(): void
    {
        $this->apply(
            [[], [], [['config_id' => 30, 'scope' => 'websites', 'scope_id' => 1, 'value' => 'EUR']]],
            []
        );

        self::assertSame(
            [
                ['insertOnDuplicate', [
                    self::TABLE,
                    [
                        'scope' => 'websites',
                        'scope_id' => 1,
                        'path' => PixelInterface::XML_PATH_NON_DEFAULT_CURRENCY,
                        'value' => 'EUR',
                    ],
                    ['value'],
                ]],
                ['update', [self::TABLE, ['value' => '1'], ['config_id = ?' => 30]]],
            ],
            $this->writes
        );
    }

    public function testNothingIsWrittenWhenEveryRowAlreadyHasTheNewShape(): void
    {
        $this->apply([[], [], []], []);

        self::assertSame([], $this->writes);
    }

    /**
     * @param array<int, array<int, array<string, mixed>>> $fetchAll rows per select, in patch order
     * @param array<int, mixed> $fetchOne answers to the lookups the rows trigger, in order
     */
    private function apply(array $fetchAll, array $fetchOne): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturnOnConsecutiveCalls(...$fetchAll);
        if ($fetchOne !== []) {
            $connection->method('fetchOne')->willReturnOnConsecutiveCalls(...$fetchOne);
        }
        foreach (['update', 'delete', 'insertOnDuplicate'] as $write) {
            $connection->method($write)->willReturnCallback(function (...$arguments) use ($write): int {
                $this->writes[] = [$write, $arguments];
                return 1;
            });
        }

        $setup = $this->createMock(ModuleDataSetupInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturn(self::TABLE);

        $patch = new MigrateConfigPaths($setup);
        self::assertSame($patch, $patch->apply());
    }
}
