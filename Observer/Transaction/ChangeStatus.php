<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Observer\Transaction;

use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Creditmemo;
use TradeTracker\Connect\Service\Transaction\Assess;
use TradeTracker\Connect\Service\Transaction\Enqueue;

/**
 * A credit memo queues the matching assessment: a full refund rejects the transaction, a partial
 * refund accepts it for the amount that still stands.
 * Event: sales_order_creditmemo_save_after
 */
class ChangeStatus implements ObserverInterface
{
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
        $creditmemo = $observer->getEvent()->getData('creditmemo');
        if (!$creditmemo instanceof Creditmemo) {
            return;
        }
        $order = $creditmemo->getOrder();

        // The order already counts this credit memo in its refunded total; an older save that does
        // not is covered by the credit memo's own amount.
        $refunded = max((float)$order->getTotalRefunded(), (float)$creditmemo->getGrandTotal());
        $remaining = round((float)$order->getGrandTotal() - $refunded, 2);

        if ($remaining <= 0.0) {
            $this->enqueue->forOrder($order, Assess::REJECTED, Reject::REASON, null);
            return;
        }

        $this->enqueue->forOrder($order, Assess::ACCEPTED, null, $remaining);
    }
}
