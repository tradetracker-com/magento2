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
use TradeTracker\Connect\Api\Config\System\PixelInterface as ConfigRepository;
use TradeTracker\Connect\Service\Api\Adapter;

/**
 * Class Reject - automatically reject transaction in case or order cancel
 * Event: sales_order_save_after
 */
class Reject implements ObserverInterface
{
    /**
     * @var ConfigRepository
     */
    private $configRepository;
    /**
     * @var Adapter
     */
    private $adapter;

    /**
     * SaveRewrite constructor.
     * @param Adapter $adapter
     * @param ConfigRepository $configRepository
     */
    public function __construct(
        Adapter $adapter,
        ConfigRepository $configRepository
    ) {
        $this->adapter = $adapter;
        $this->configRepository = $configRepository;
    }

    /**
     * @param EventObserver $observer
     * @return $this
     */
    public function execute(EventObserver $observer): self
    {
        /* @var Creditmemo $creditmemo*/
        $order = $observer->getEvent()->getOrder();
        if ($order->getStatus() != 'canceled') {
            return $this;
        }
        $incrementId = $order->getIncrementId();
        $storeId = (int)$order->getStoreId();
        $campaignID = $this->configRepository->getCampaignId($storeId);
        $data = $this->configRepository->getApiCredentials($storeId);
        $result = $this->adapter->execute($data);
        $id = 0;
        if (!$result['success']) {
            return $this;
        }
        $client = $result['client'];
        try {
            $transactions = $client->getConversionTransactions(
                $campaignID,
                ['Characteristics' => $order->getIncrementId()]
            );
            foreach ($transactions as $transaction) {
                if ($transaction->characteristic == $incrementId) {
                    if ($transaction->transactionStatus != 'pending') {
                        return $this;
                    }
                    $id = $transaction->ID;
                    break;
                }
            }
            if (empty($transactions)) {
                return $this;
            }
            $client->assessConversionTransaction($id, 'rejected', 'order_canceled');
        } catch (\Exception $exception) {
            return $this;
        }
        return $this;
    }
}
