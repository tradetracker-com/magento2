<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Block\Adminhtml\Order\View\Tab;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Tab\TabInterface;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Directory\Model\CountryFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order;
use TradeTracker\Connect\Api\Config\System\PixelInterface as ConfigRepository;
use TradeTracker\Connect\Service\Api\Adapter;

/**
 * Class TransactionDetail
 * To add tab to sales order view
 *
 * @api
 */
class TransactionDetail extends Template implements TabInterface
{

    /**
     * Template
     *
     * @var string
     */
    protected $_template = 'TradeTracker_Connect::order/view/tab/transaction_detail.phtml';

    /**
     * Core registry
     *
     * @var Registry
     */
    private $coreRegistry;

    /**
     * @var \Magento\Framework\DataObject|null;
     */
    private $order = null;

    /**
     * @var CountryFactory
     */
    private $countryFactory;

    /**
     * @var GroupRepositoryInterface
     */
    private $groupRepository;

    /**
     * @var Adapter
     */
    private $adapter;
    /**
     * @var ConfigRepository
     */
    private $configRepository;
    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * TransactionDetail constructor.
     * @param Context $context
     * @param Registry $coreRegistry
     * @param CountryFactory $countryFactory
     * @param GroupRepositoryInterface $groupRepository
     * @param Adapter $adapter
     * @param ConfigRepository $configRepository
     * @param ResourceConnection $resource
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        CountryFactory $countryFactory,
        GroupRepositoryInterface $groupRepository,
        Adapter $adapter,
        ConfigRepository $configRepository,
        ResourceConnection $resource,
        array $data = []
    ) {
        $this->coreRegistry = $coreRegistry;
        $this->countryFactory = $countryFactory;
        $this->groupRepository = $groupRepository;
        $this->adapter = $adapter;
        $this->configRepository = $configRepository;
        $this->resource = $resource;
        parent::__construct($context, $data);
    }

    /**
     * @inheritdoc
     */
    public function getTabLabel(): string
    {
        return (string)__('TradeTracker Conversion');
    }

    /**
     * @inheritdoc
     */
    public function getTabTitle(): string
    {
        return (string)__('TradeTracker Conversion');
    }

    /**
     * @inheritdoc
     */
    public function canShowTab(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function isHidden(): bool
    {
        return false;
    }

    /**
     * @return array
     */
    public function getTransaction()
    {
        $order = $this->getOrder();
        $storeId = (int)$order->getStoreId();
        $campaignID = $this->configRepository->getCampaingId($storeId);
        $data = $this->configRepository->getApiCredentials($storeId);
        $result = $this->adapter->execute($data);
        if ($result['success']) {
            $client = $result['client'];
            try {
                $result = $client->getConversionTransactions(
                    $campaignID,
                    ['Characteristics' => $order->getIncrementId()]
                );
            } catch (\Exception $exception) {
                return [
                    'success' => false,
                    'error' => $exception->getMessage()
                ];
            }
            if (empty($result)) {
                return [
                    'success' => false,
                    'error' => 'No transaction found for current order'
                ];
            }
            $trans = [];
            foreach ($result as $transaction) {
                if ($transaction->characteristic == $order->getIncrementId()) {
                    $connection = $this->resource->getConnection();
                    $connection->update(
                        $this->resource->getTableName('sales_order'),
                        ['tt_status' => $transaction->transactionStatus],
                        ['increment_id = ?' => $transaction->characteristic]
                    );
                    $connection->update(
                        $this->resource->getTableName('sales_order_grid'),
                        ['tt_status' => $transaction->transactionStatus],
                        ['increment_id = ?' => $transaction->characteristic]
                    );
                    $trans = $transaction;
                }
            }
            if (empty($trans)) {
                $connection = $this->resource->getConnection();
                $connection->update(
                    $this->resource->getTableName('sales_order'),
                    ['tt_status' => 'No transaction'],
                    ['increment_id = ?' => $order->getIncrementId()]
                );
                $connection->update(
                    $this->resource->getTableName('sales_order_grid'),
                    ['tt_status' => 'No transaction'],
                    ['increment_id = ?' => $order->getIncrementId()]
                );
                return [
                    'success' => false,
                    'error' => 'No transaction found for current order'
                ];
            }
            return [
                'success' => true,
                'data' => $trans
            ];
        } else {
            return $result;
        }
    }

    /**
     * Retrieves order model instance
     *
     * @return Order
     */
    public function getOrder(): Order
    {
        return $this->coreRegistry->registry('current_order');
    }

    /**
     * @return array
     */
    public function getReject(): array
    {
        if ($this->getOrder()->getCreditmemosCollection()->getSize()
            || !$this->getOrder()->getInvoiceCollection()->getSize()
        ) {
            return ['success' => true];
        }
        return [
            'success' => false,
            'data' => 'Transaction not allowed to reject, as creditmemo is missing.'
        ];
    }
}
