<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Model\Cron;

use Magento\Framework\App\ResourceConnection;
use TradeTracker\Connect\Api\Config\System\PixelInterface;
use TradeTracker\Connect\Service\Api\Adapter;
use TradeTracker\Connect\Api\Log\RepositoryInterface as Logger;

/**
 * Cron class to sync transactions
 */
class SyncTransactions
{

    private ResourceConnection $resource;
    private Adapter $adapter;
    private PixelInterface $configRepository;
    private Logger $logger;

    public function __construct(
        ResourceConnection $resource,
        Adapter $adapter,
        PixelInterface $configRepository,
        Logger $logger
    ) {
        $this->resource = $resource;
        $this->adapter = $adapter;
        $this->configRepository = $configRepository;
        $this->logger = $logger;
    }

    /**
     * Send Invalidated products to API
     *
     * @return void
     */
    public function execute()
    {
        if (!$this->configRepository->isEnabled()) {
            return;
        }
        $this->updateTransactions();
    }

    /**
     * @return void
     */
    private function updateTransactions()
    {
        $campaignID = $this->configRepository->getCampaignId();
        $data = $this->configRepository->getApiCredentials();

        $result = $this->adapter->execute($data);

        if (empty($result['success']) || empty($result['client'])) {
            $this->logger->addDebugLog('Cron', 'API connection failed or returned no client.');
            return;
        }

        $client = $result['client'];

        try {
            $transactions = $client->getConversionTransactions($campaignID);
        } catch (\Throwable $e) {
            $this->logger->addDebugLog('Cron', 'Failed to fetch conversion transactions.
                campaign_id = ' . $campaignID .
                '. Exception: ' . $e->getMessage());
            return;
        }

        $connection = $this->resource->getConnection();
        $connection->beginTransaction();

        foreach ($transactions as $item) {
            if (!$item) {
                $this->logger->addDebugLog('Cron', 'Skipping falsy transaction item.');
                continue;
            }
            $connection->update(
                $this->resource->getTableName('sales_order'),
                ['tt_status' => $item->transactionStatus],
                ['increment_id = ?' => $item->characteristic]
            );
            $connection->update(
                $this->resource->getTableName('sales_order_grid'),
                ['tt_status' => $item->transactionStatus],
                ['increment_id = ?' => $item->characteristic]
            );
        }
        $connection->commit();
    }
}
