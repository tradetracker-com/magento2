<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Service\Feed;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TradeTracker\Connect\Service\Feed\Progress;

/**
 * The progress of a feed run lives in the cache, because the run and the admin poll that reads it
 * are two separate requests. The service has to keep store views apart, and has to survive a poll
 * that arrives before anything was written.
 */
#[CoversClass(Progress::class)]
class ProgressTest extends TestCase
{
    /** @var array<string, string> */
    private array $storage = [];

    public function testNothingIsReportedBeforeARunStarts(): void
    {
        self::assertNull($this->progress()->get(1));
    }

    public function testAStartedRunReportsItsTotalAndNoProductsYet(): void
    {
        $progress = $this->progress();
        $progress->start(1, 250);

        self::assertSame(
            ['total' => 250, 'written' => 0, 'done' => false, 'message' => null, 'error' => null],
            $progress->get(1)
        );
    }

    public function testAdvanceRecordsHowFarTheRunIs(): void
    {
        $progress = $this->progress();
        $progress->start(1, 250);
        $progress->advance(1, 100);

        self::assertSame(100, $progress->get(1)['written']);
    }

    public function testAdvanceWithoutAStartIsIgnored(): void
    {
        $progress = $this->progress();
        $progress->advance(1, 100);

        self::assertNull($progress->get(1));
    }

    public function testFinishKeepsTheCountAndCarriesTheResultLine(): void
    {
        $progress = $this->progress();
        $progress->start(1, 250);
        $progress->advance(1, 250);
        $progress->finish(1, 'Products in feed: 250');

        $state = $progress->get(1);
        self::assertTrue($state['done']);
        self::assertSame(250, $state['written']);
        self::assertSame('Products in feed: 250', $state['message']);
        self::assertNull($state['error']);
    }

    public function testAdvanceAfterFinishDoesNotReopenTheRun(): void
    {
        $progress = $this->progress();
        $progress->start(1, 250);
        $progress->finish(1, 'done');
        $progress->advance(1, 10);

        $state = $progress->get(1);
        self::assertTrue($state['done']);
        self::assertSame(0, $state['written']);
    }

    public function testFailureIsReportedWithItsMessage(): void
    {
        $progress = $this->progress();
        $progress->start(1, 250);
        $progress->fail(1, 'Product Feed for this StoreId not enabled.');

        $state = $progress->get(1);
        self::assertTrue($state['done']);
        self::assertSame('Product Feed for this StoreId not enabled.', $state['error']);
        self::assertNull($state['message']);
    }

    public function testAFailureWithoutAStartStillReportsItself(): void
    {
        $progress = $this->progress();
        $progress->fail(2, 'Store is not enabled');

        self::assertSame('Store is not enabled', $progress->get(2)['error']);
    }

    public function testStoreViewsDoNotShareState(): void
    {
        $progress = $this->progress();
        $progress->start(1, 10);
        $progress->start(2, 30);

        self::assertSame(10, $progress->get(1)['total']);
        self::assertSame(30, $progress->get(2)['total']);
    }

    public function testClearRemovesTheRecord(): void
    {
        $progress = $this->progress();
        $progress->start(1, 10);
        $progress->clear(1);

        self::assertNull($progress->get(1));
    }

    private function progress(): Progress
    {
        $this->storage = [];

        $cache = $this->createMock(CacheInterface::class);
        $cache->method('save')->willReturnCallback(function (string $data, string $identifier): bool {
            $this->storage[$identifier] = $data;
            return true;
        });
        $cache->method('load')->willReturnCallback(fn (string $identifier) => $this->storage[$identifier] ?? false);
        $cache->method('remove')->willReturnCallback(function (string $identifier): bool {
            unset($this->storage[$identifier]);
            return true;
        });

        return new Progress($cache, new Json());
    }
}
