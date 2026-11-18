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
use Magento\Sales\Model\Order\Creditmemo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TradeTracker\Connect\Observer\Transaction\ChangeStatus;
use TradeTracker\Connect\Service\Transaction\Enqueue;

/**
 * A credit memo queues what the refund means for the commission: a full refund rejects the
 * transaction, a partial one accepts it for what the customer kept. The remaining amount counts
 * every credit memo of the order, not only the one being saved.
 */
#[CoversClass(ChangeStatus::class)]
class ChangeStatusTest extends TestCase
{
    public function testAFullRefundQueuesARejection(): void
    {
        $enqueue = $this->createMock(Enqueue::class);
        $enqueue->expects(self::once())->method('forOrder')->with(self::isInstanceOf(Order::class), 'rejected', 'order_canceled', null);

        (new ChangeStatus($enqueue))->execute($this->observer(100.0, 100.0, 100.0));
    }

    public function testAPartialRefundAcceptsTheAmountThatStands(): void
    {
        $enqueue = $this->createMock(Enqueue::class);
        $enqueue->expects(self::once())->method('forOrder')->with(self::isInstanceOf(Order::class), 'accepted', null, 70.0);

        (new ChangeStatus($enqueue))->execute($this->observer(100.0, 30.0, 30.0));
    }

    public function testASecondPartialRefundCountsTheFirstOne(): void
    {
        $enqueue = $this->createMock(Enqueue::class);
        $enqueue->expects(self::once())->method('forOrder')->with(self::isInstanceOf(Order::class), 'accepted', null, 50.0);

        (new ChangeStatus($enqueue))->execute($this->observer(100.0, 20.0, 50.0));
    }

    public function testRefundsThatAddUpToTheOrderReject(): void
    {
        $enqueue = $this->createMock(Enqueue::class);
        $enqueue->expects(self::once())->method('forOrder')->with(self::isInstanceOf(Order::class), 'rejected', 'order_canceled', null);

        (new ChangeStatus($enqueue))->execute($this->observer(100.0, 70.0, 100.0));
    }

    public function testAnEventWithoutACreditMemoQueuesNothing(): void
    {
        $enqueue = $this->createMock(Enqueue::class);
        $enqueue->expects(self::never())->method('forOrder');

        (new ChangeStatus($enqueue))->execute(new Observer(['event' => new Event([])]));
    }

    private function observer(float $orderTotal, float $creditmemoTotal, float $totalRefunded): Observer
    {
        $order = $this->createMock(Order::class);
        $order->method('getGrandTotal')->willReturn($orderTotal);
        $order->method('getTotalRefunded')->willReturn($totalRefunded);
        $creditmemo = $this->createMock(Creditmemo::class);
        $creditmemo->method('getOrder')->willReturn($order);
        $creditmemo->method('getGrandTotal')->willReturn($creditmemoTotal);

        return new Observer(['event' => new Event(['creditmemo' => $creditmemo])]);
    }
}
