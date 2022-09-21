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

/**
 * Cron class to sync transactions
 */
class SyncTransactions
{

    /**
     * @var ResourceConnection
     */
    private $resource;
    /**
     * @var Adapter
     */
    private $adapter;
    /**
     * @var PixelInterface
     */
    private $configRepository;

    /**
     * SyncTransactions constructor.
     * @param ResourceConnection $resource
     * @param Adapter $adapter
     * @param PixelInterface $configRepository
     */
    public function __construct(
        ResourceConnection $resource,
        Adapter $adapter,
        PixelInterface $configRepository
    ) {
        $this->resource = $resource;
        $this->adapter = $adapter;
        $this->configRepository = $configRepository;
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
        if ($result['success']) {
            $client = $result['client'];
            try {
                $result = $client->getConversionTransactions(
                    $campaignID
                );
            } catch (\Exception $exception) {
                return;
            }
        }
        $connection = $this->resource->getConnection();
        $connection->beginTransaction();
        foreach ($result as $item) {
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
