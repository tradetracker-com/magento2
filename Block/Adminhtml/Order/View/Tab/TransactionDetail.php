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
use Magento\Framework\Registry;
use Magento\Sales\Model\Order;
use TradeTracker\Connect\Api\Config\System\PixelInterface;

/**
 * The "TradeTracker Conversion" tab on the order page.
 *
 * An AJAX tab: the order page renders only its title, and the tab's content comes from
 * tradetracker/transaction/tab when the admin opens it. Nothing here calls TradeTracker.
 *
 * @api
 */
class TransactionDetail extends Template implements TabInterface
{
    public const VIEW_RESOURCE = 'Magento_Sales::actions_view';

    public function __construct(
        Context $context,
        private readonly Registry $coreRegistry,
        private readonly PixelInterface $config,
        array $data = []
    ) {
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
        $order = $this->getOrder();
        if ($order === null) {
            return false;
        }
        $storeId = (int)$order->getStoreId();

        return $this->config->isEnabled()
            && $this->config->isPixelEnabled($storeId)
            && $this->config->isOrderTabEnabled($storeId)
            && $this->_authorization->isAllowed(self::VIEW_RESOURCE);
    }

    /**
     * @inheritdoc
     */
    public function isHidden(): bool
    {
        return false;
    }

    /**
     * Marks the tab as loaded over AJAX, from getTabUrl(), when it is opened.
     */
    public function getTabClass(): string
    {
        return 'ajax only';
    }

    public function getTabUrl(): string
    {
        return $this->getUrl('tradetracker/transaction/tab', ['order_id' => $this->getOrder()?->getId()]);
    }

    public function getOrder(): ?Order
    {
        $order = $this->coreRegistry->registry('current_order');

        return $order instanceof Order ? $order : null;
    }

    /**
     * The tab has no body of its own on the order page.
     */
    protected function _toHtml(): string
    {
        return '';
    }
}
