<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Block\Adminhtml\Order\View;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use TradeTracker\Connect\Exception\ApiException;
use TradeTracker\Connect\Service\Transaction\Lookup;

/**
 * The TradeTracker transaction of the order named in the request; the body of the conversion tab.
 */
class Transaction extends Template
{
    /**
     * @var string
     */
    protected $_template = 'TradeTracker_Connect::order/view/tab/transaction_detail.phtml';

    public function __construct(
        Context $context,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly Lookup $lookup,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return array{success: bool, data?: \stdClass, error?: string}
     */
    public function getTransaction(): array
    {
        $orderId = (int)$this->getRequest()->getParam('order_id');
        try {
            $order = $this->orderRepository->get($orderId);
        } catch (LocalizedException $e) {
            return ['success' => false, 'error' => (string)__('Order %1 was not found.', $orderId)];
        }

        try {
            $transaction = $this->lookup->find((int)$order->getStoreId(), (string)$order->getIncrementId());
        } catch (ApiException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
        if ($transaction === null) {
            return ['success' => false, 'error' => (string)__('No transaction found for this order.')];
        }

        return ['success' => true, 'data' => $transaction];
    }
}
