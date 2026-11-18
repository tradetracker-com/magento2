<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Observer\Transaction;

use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use TradeTracker\Connect\Service\Transaction\Assess;
use TradeTracker\Connect\Service\Transaction\Enqueue;

/**
 * A cancelled order queues the rejection of its TradeTracker transaction.
 * Event: sales_order_save_after
 */
class Reject implements ObserverInterface
{
    public const REASON = 'order_canceled';

    public function __construct(
        private readonly Enqueue $enqueue
    ) {
    }

    /**
     * @param EventObserver $observer
     * @return void
     */
    public function execute(EventObserver $observer): void
    {
        $order = $observer->getEvent()->getData('order');
        if (!$order instanceof Order || $order->getState() !== Order::STATE_CANCELED) {
            return;
        }

        $this->enqueue->forOrder($order, Assess::REJECTED, self::REASON, null);
    }
}
