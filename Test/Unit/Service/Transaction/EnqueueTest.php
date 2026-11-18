<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Service\Transaction;

use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TradeTracker\Connect\Api\Config\System\PixelInterface;
use TradeTracker\Connect\Service\Transaction\Enqueue;
use TradeTracker\Connect\Service\Transaction\Queue;

/**
 * Up to 1.9.0 the observers called TradeTracker for every cancelled order and every credit memo of
 * every shop that had the module installed, enabled or not. A row is queued only when the module
 * is on, the pixel is on for the order's store view and a campaign is configured.
 */
#[CoversClass(Enqueue::class)]
class EnqueueTest extends TestCase
{
    private Queue&MockObject $queue;

    protected function setUp(): void
    {
        $this->queue = $this->createMock(Queue::class);
    }

    public function testAnOrderOfATrackedStoreViewIsQueued(): void
    {
        $this->queue->expects(self::once())->method('add')->with(7, '100000007', 2, 'rejected', 'order_canceled', null)->willReturn(true);

        self::assertTrue($this->enqueue(true, true, '1234')->forOrder($this->order(), 'rejected', 'order_canceled', null));
    }

    public function testADisabledModuleQueuesNothing(): void
    {
        $this->queue->expects(self::never())->method('add');

        self::assertFalse($this->enqueue(false, true, '1234')->forOrder($this->order(), 'rejected', 'order_canceled', null));
    }

    public function testAStoreViewWithoutThePixelQueuesNothing(): void
    {
        $this->queue->expects(self::never())->method('add');

        self::assertFalse($this->enqueue(true, false, '1234')->forOrder($this->order(), 'rejected', 'order_canceled', null));
    }

    public function testAStoreViewWithoutACampaignQueuesNothing(): void
    {
        $this->queue->expects(self::never())->method('add');

        self::assertFalse($this->enqueue(true, true, '')->forOrder($this->order(), 'rejected', 'order_canceled', null));
    }

    private function order(): Order
    {
        $order = $this->createMock(Order::class);
        $order->method('getStoreId')->willReturn(2);
        $order->method('getEntityId')->willReturn(7);
        $order->method('getIncrementId')->willReturn('100000007');

        return $order;
    }

    private function enqueue(bool $enabled, bool $pixel, string $campaign): Enqueue
    {
        $config = $this->createMock(PixelInterface::class);
        $config->method('isEnabled')->willReturn($enabled);
        $config->method('isPixelEnabled')->with(2)->willReturn($pixel);
        $config->method('getCampaignId')->with(2)->willReturn($campaign);

        return new Enqueue($config, $this->queue);
    }
}
