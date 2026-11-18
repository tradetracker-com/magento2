<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Service\Transaction;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TradeTracker\Connect\Service\Transaction\Queue;

/**
 * The queue holds each assessment once, hands out the oldest pending rows, and tells a refusal
 * (counted, given up on after MAX_ATTEMPTS) from an outage (not counted, tried again next run).
 */
#[CoversClass(Queue::class)]
class QueueTest extends TestCase
{
    private AdapterInterface&MockObject $connection;
    /** @var array<int, array<string, mixed>> */
    private array $inserted = [];
    /** @var array<int, array{0: array<string, mixed>, 1: array<string, int>}> */
    private array $updated = [];

    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);
        $select = $this->createMock(Select::class);
        foreach (['from', 'where', 'limit', 'order'] as $method) {
            $select->method($method)->willReturnSelf();
        }
        $this->connection->method('select')->willReturn($select);
        $this->connection->method('quoteInto')->willReturnCallback(static fn (string $text, $value) => str_replace('?', (string)$value, $text));
        $this->connection->method('insert')->willReturnCallback(function (string $table, array $data) {
            $this->inserted[] = $data;
            return 1;
        });
        $this->connection->method('update')->willReturnCallback(function (string $table, array $data, array $where) {
            $this->updated[] = [$data, $where];
            return 1;
        });
    }

    public function testANewAssessmentIsQueuedPending(): void
    {
        $this->connection->method('fetchOne')->willReturn(false);

        self::assertTrue($this->queue()->add(7, '100000007', 2, 'rejected', 'order_canceled', null));
        self::assertSame([[
            'order_id' => 7,
            'increment_id' => '100000007',
            'store_id' => 2,
            'action' => 'rejected',
            'reason' => 'order_canceled',
            'amount' => null,
            'status' => 'pending',
            'attempts' => 0,
            'created_at' => '2026-11-18 10:00:00',
        ]], $this->inserted);
    }

    public function testTheSameAssessmentIsNotQueuedTwice(): void
    {
        $this->connection->method('fetchOne')->willReturn('12');

        self::assertFalse($this->queue()->add(7, '100000007', 2, 'rejected', 'order_canceled', null));
        self::assertSame([], $this->inserted);
    }

    public function testFinishMarksTheRowDoneWithItsMessage(): void
    {
        $this->queue()->finish(12, 'Transaction 99 rejected');

        self::assertSame([[
            ['status' => 'done', 'message' => 'Transaction 99 rejected', 'processed_at' => '2026-11-18 10:00:00'],
            ['entity_id = ?' => 12],
        ]], $this->updated);
    }

    public function testARefusalCountsAndTheRowStaysPending(): void
    {
        self::assertFalse($this->queue()->refused(12, 2, 'Failed to authenticate.'));
        self::assertSame(
            ['attempts' => 2, 'message' => 'Failed to authenticate.', 'status' => 'pending', 'processed_at' => null],
            $this->updated[0][0]
        );
    }

    public function testTheLastRefusalFailsTheRowForGood(): void
    {
        self::assertTrue($this->queue()->refused(12, Queue::MAX_ATTEMPTS, 'Failed to authenticate.'));
        self::assertSame('failed', $this->updated[0][0]['status']);
        self::assertSame('2026-11-18 10:00:00', $this->updated[0][0]['processed_at']);
    }

    public function testAnOutageOnlyNotesWhy(): void
    {
        $this->queue()->postpone(12, 'No answer within 3 seconds');

        self::assertSame([[['message' => 'No answer within 3 seconds'], ['entity_id = ?' => 12]]], $this->updated);
    }

    private function queue(): Queue
    {
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->connection);
        $resource->method('getTableName')->willReturnArgument(0);
        $dateTime = $this->createMock(DateTime::class);
        $dateTime->method('gmtDate')->willReturn('2026-11-18 10:00:00');

        return new Queue($resource, $dateTime);
    }
}
