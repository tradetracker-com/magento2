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
 * Class ChangeStatus - Change transaction status according credit memo data
 * Event: sales_order_creditmemo_save_after
 */
class ChangeStatus implements ObserverInterface
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
        $creditmemo = $observer->getEvent()->getCreditmemo();
        $order = $creditmemo->getOrder();
        $incrementId = $order->getIncrementId();
        $storeId = (int)$order->getStoreId();
        $campaignID = $this->configRepository->getCampaingId($storeId);
        $data = $this->configRepository->getApiCredentials($storeId);
        $result = $this->adapter->execute($data);
        $id = 0;
        if ($result['success']) {
            $client = $result['client'];
            try {
                $transactions = $client->getConversionTransactions(
                    $campaignID,
                    ['Characteristics' => $order->getIncrementId()]
                );
                foreach ($transactions as $transaction) {
                    if ($transaction->characteristic == $incrementId) {
                        $id = $transaction->ID;
                        break;
                    }
                }
                if (empty($transactions)) {
                    return $this;
                }
                if ($creditmemo->getGrandTotal() == $order->getGrandTotal()) {
                    $client->assessConversionTransaction($id, 'rejected', 'order_canceled');
                } else {
                    $amount = $order->getGrandTotal() - $creditmemo->getGrandTotal();
                    $client->assessConversionTransaction($id, 'accepted', false, (float)$amount);
                }
            } catch (\Exception $exception) {
                return $this;
            }
        }
        return $this;
    }
}
