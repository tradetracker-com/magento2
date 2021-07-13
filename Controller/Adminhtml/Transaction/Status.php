<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Controller\Adminhtml\Transaction;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Sales\Api\OrderRepositoryInterface as OrderRepository;
use Magento\Sales\Model\Order\Status\HistoryFactory;
use TradeTracker\Connect\Api\Config\System\PixelInterface as ConfigRepository;
use TradeTracker\Connect\Service\Api\Adapter;

/**
 * Class Status
 * Switch transaction status
 */
class Status extends Action
{

    /**
     * @var ResourceConnection
     */
    private $resource;
    /**
     * @var OrderRepository
     */
    private $orderRepository;
    /**
     * @var HistoryFactory
     */
    private $orderHistoryFactory;
    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;
    /**
     * @var Adapter
     */
    private $adapter;
    /**
     * @var ConfigRepository
     */
    private $configRepository;

    /**
     * Status constructor.
     * @param Context $context
     * @param ResourceConnection $resource
     * @param OrderRepository $orderRepository
     * @param HistoryFactory $orderHistoryFactory
     * @param JsonFactory $resultJsonFactory
     * @param Adapter $adapter
     * @param ConfigRepository $configRepository
     */
    public function __construct(
        Context $context,
        ResourceConnection $resource,
        OrderRepository $orderRepository,
        HistoryFactory $orderHistoryFactory,
        JsonFactory $resultJsonFactory,
        Adapter $adapter,
        ConfigRepository $configRepository
    ) {
        $this->resource = $resource;
        $this->orderRepository = $orderRepository;
        $this->orderHistoryFactory = $orderHistoryFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->adapter = $adapter;
        $this->configRepository = $configRepository;
        parent::__construct($context);
    }

    /**
     * @return Json
     * @throws Exception
     */
    public function execute()
    {
        $orderId = $this->getRequest()->getParam('id');
        $reason = $this->getRequest()->getParam('reason');
        $order = $this->orderRepository->get((int)$orderId);
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
                    }
                }
                $client->assessConversionTransaction($id, 'rejected', $reason);
                $result = ['success' => true];
            } catch (\Exception $exception) {
                $result = [
                    'success' => false,
                    'error' => $exception->getMessage()
                ];
            }
        }
        $resultJson = $this->resultJsonFactory->create();
        return $resultJson->setData(['status' => $result]);
    }
}
