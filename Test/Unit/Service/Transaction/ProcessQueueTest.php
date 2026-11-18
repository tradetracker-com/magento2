<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Service\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TradeTracker\Connect\Api\Log\RepositoryInterface as LogRepository;
use TradeTracker\Connect\Exception\ApiAuthenticationException;
use TradeTracker\Connect\Exception\ApiException;
use TradeTracker\Connect\Exception\ApiUnavailableException;
use TradeTracker\Connect\Service\Transaction\Assess;
use TradeTracker\Connect\Service\Transaction\ProcessQueue;
use TradeTracker\Connect\Service\Transaction\Queue;

/**
 * The cron run: each pending row is sent once. A refusal counts against its row; an outage ends the
 * run, so fifty rows do not each wait out the same timeout, and nothing is counted against them.
 */
#[CoversClass(ProcessQueue::class)]
class ProcessQueueTest extends TestCase
{
    private Queue&MockObject $queue;
    private Assess&MockObject $assess;
    private LogRepository&MockObject $logger;

    protected function setUp(): void
    {
        $this->queue = $this->createMock(Queue::class);
        $this->assess = $this->createMock(Assess::class);
        $this->logger = $this->createMock(LogRepository::class);
    }

    public function testASentAssessmentFinishesItsRow(): void
    {
        $this->queue->method('pending')->with(ProcessQueue::BATCH_SIZE)->willReturn([$this->row(1, 0, 'accepted', null, '80.5000')]);
        $this->assess->expects(self::once())->method('execute')->with(2, '100000001', 'accepted', null, 80.5)->willReturn('Transaction 99 accepted');
        $this->queue->expects(self::once())->method('finish')->with(1, 'Transaction 99 accepted');

        self::assertSame(['processed' => 1, 'done' => 1, 'refused' => 0, 'failed' => 0, 'postponed' => 0], $this->process()->execute());
    }

    public function testARefusalCountsAnAttempt(): void
    {
        $this->queue->method('pending')->willReturn([$this->row(1, 1)]);
        $this->assess->method('execute')->willThrowException(new ApiException('authenticate: Failed to authenticate.'));
        $this->queue->expects(self::once())->method('refused')->with(1, 2, 'authenticate: Failed to authenticate.')->willReturn(false);
        $this->logger->expects(self::never())->method('addErrorLog');

        self::assertSame(['processed' => 1, 'done' => 0, 'refused' => 1, 'failed' => 0, 'postponed' => 0], $this->process()->execute());
    }

    public function testTheLastRefusalIsAnError(): void
    {
        $this->queue->method('pending')->willReturn([$this->row(1, Queue::MAX_ATTEMPTS - 1)]);
        $this->assess->method('execute')->willThrowException(new ApiException('unknown transaction'));
        $this->queue->method('refused')->with(1, Queue::MAX_ATTEMPTS, 'unknown transaction')->willReturn(true);
        $this->logger->expects(self::once())->method('addErrorLog')->with('Transaction queue', self::stringContains('failed for good'));

        self::assertSame(1, $this->process()->execute()['failed']);
    }

    public function testAnOutageEndsTheRunAndCountsAgainstNobody(): void
    {
        $this->queue->method('pending')->willReturn([$this->row(1, 0), $this->row(2, 0)]);
        $this->assess->expects(self::once())->method('execute')->willThrowException(new ApiUnavailableException('No answer within 3 seconds'));
        $this->queue->expects(self::once())->method('postpone')->with(1, 'No answer within 3 seconds');
        $this->queue->expects(self::never())->method('refused');
        $this->queue->expects(self::never())->method('finish');

        self::assertSame(['processed' => 1, 'done' => 0, 'refused' => 0, 'failed' => 0, 'postponed' => 1], $this->process()->execute());
    }

    public function testRefusedCredentialsPostponeTheRowInsteadOfCountingAgainstIt(): void
    {
        // Wrong credentials are the shop's configuration, not TradeTracker's verdict on the row:
        // counted as refusals they used a row's five attempts up in twenty minutes of cron.
        $this->queue->method('pending')->willReturn([$this->row(1, 0), $this->row(2, 0)]);
        $this->assess->expects(self::once())->method('execute')->willThrowException(new ApiAuthenticationException('authenticate: Failed to authenticate.'));
        $this->queue->expects(self::once())->method('postpone')->with(1, 'authenticate: Failed to authenticate.');
        $this->queue->expects(self::never())->method('refused');

        self::assertSame(['processed' => 1, 'done' => 0, 'refused' => 0, 'failed' => 0, 'postponed' => 1], $this->process()->execute());
    }

    public function testAnEmptyQueueDoesNothing(): void
    {
        $this->queue->method('pending')->willReturn([]);
        $this->assess->expects(self::never())->method('execute');

        self::assertSame(0, $this->process()->execute()['processed']);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(int $id, int $attempts, string $action = 'rejected', ?string $reason = 'order_canceled', ?string $amount = null): array
    {
        return [
            'entity_id' => (string)$id,
            'order_id' => '7',
            'increment_id' => '10000000' . $id,
            'store_id' => '2',
            'action' => $action,
            'reason' => $reason,
            'amount' => $amount,
            'status' => 'pending',
            'attempts' => (string)$attempts,
        ];
    }

    private function process(): ProcessQueue
    {
        return new ProcessQueue($this->queue, $this->assess, $this->logger);
    }
}
