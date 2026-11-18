<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Model\Cron;

use TradeTracker\Connect\Api\Config\System\PixelInterface;
use TradeTracker\Connect\Api\Log\RepositoryInterface as Logger;
use TradeTracker\Connect\Exception\ApiException;
use TradeTracker\Connect\Service\Api\Adapter;
use TradeTracker\Connect\Service\Transaction\StatusWriter;

/**
 * Nightly: copy the status of every transaction of the campaign to the order grid.
 */
class SyncTransactions
{
    public function __construct(
        private readonly Adapter $adapter,
        private readonly PixelInterface $configRepository,
        private readonly StatusWriter $statusWriter,
        private readonly Logger $logger
    ) {
    }

    public function execute(): void
    {
        if (!$this->configRepository->isEnabled()) {
            return;
        }

        $campaignId = $this->configRepository->getCampaignId();
        try {
            $transactions = $this->adapter->call(
                $this->configRepository->getApiCredentials(),
                'getConversionTransactions',
                [$campaignId]
            );
        } catch (ApiException $e) {
            $this->logger->addDebugLog(
                'Cron',
                sprintf('Failed to fetch conversion transactions for campaign %s: %s', $campaignId, $e->getMessage())
            );
            return;
        }

        foreach (is_array($transactions) ? $transactions : [] as $item) {
            if (!$item instanceof \stdClass || !isset($item->characteristic)) {
                $this->logger->addDebugLog('Cron', 'Skipping a transaction without an order reference.');
                continue;
            }
            $this->statusWriter->write((string)$item->characteristic, (string)($item->transactionStatus ?? ''));
        }
    }
}
