<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Service\Transaction;

use Magento\Sales\Api\Data\OrderInterface;
use TradeTracker\Connect\Api\Config\System\PixelInterface;

/**
 * Queue an assessment for an order, when the module tracks that order's store view at all.
 *
 * Called from observers inside an order or credit memo save, so it touches the database and
 * nothing else: no TradeTracker call ever runs while an admin waits for a save.
 */
class Enqueue
{
    public function __construct(
        private readonly PixelInterface $config,
        private readonly Queue $queue
    ) {
    }

    /**
     * @return bool true when a new row was queued
     */
    public function forOrder(OrderInterface $order, string $action, ?string $reason, ?float $amount): bool
    {
        $storeId = (int)$order->getStoreId();
        if (!$this->config->isEnabled()
            || !$this->config->isPixelEnabled($storeId)
            || $this->config->getCampaignId($storeId) === ''
        ) {
            return false;
        }

        return $this->queue->add(
            (int)$order->getEntityId(),
            (string)$order->getIncrementId(),
            $storeId,
            $action,
            $reason,
            $amount
        );
    }
}
