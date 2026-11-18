<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Observer\Transaction;

use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TradeTracker\Connect\Observer\Transaction\Reject;
use TradeTracker\Connect\Service\Transaction\Enqueue;

/**
 * Saving a cancelled order queues the rejection; saving any other order does nothing. The observer
 * itself never talks to TradeTracker, so a cancellation returns as fast as any other save.
 */
#[CoversClass(Reject::class)]
class RejectTest extends TestCase
{
    public function testACancelledOrderQueuesARejection(): void
    {
        $order = $this->order(Order::STATE_CANCELED);
        $enqueue = $this->createMock(Enqueue::class);
        $enqueue->expects(self::once())->method('forOrder')->with($order, 'rejected', 'order_canceled', null);

        (new Reject($enqueue))->execute($this->observer(['order' => $order]));
    }

    public function testAnyOtherStateQueuesNothing(): void
    {
        $enqueue = $this->createMock(Enqueue::class);
        $enqueue->expects(self::never())->method('forOrder');

        (new Reject($enqueue))->execute($this->observer(['order' => $this->order(Order::STATE_PROCESSING)]));
    }

    public function testAnEventWithoutAnOrderQueuesNothing(): void
    {
        $enqueue = $this->createMock(Enqueue::class);
        $enqueue->expects(self::never())->method('forOrder');

        (new Reject($enqueue))->execute($this->observer([]));
    }

    private function order(string $state): Order
    {
        $order = $this->createMock(Order::class);
        $order->method('getState')->willReturn($state);

        return $order;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function observer(array $data): Observer
    {
        return new Observer(['event' => new Event($data)]);
    }
}
