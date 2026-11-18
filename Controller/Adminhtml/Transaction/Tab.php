<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Controller\Adminhtml\Transaction;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\View\Result\Layout;
use Magento\Framework\View\Result\LayoutFactory;

/**
 * The content of the TradeTracker Conversion tab on the order page, loaded when the tab is opened.
 *
 * The admin tabs widget fetches an AJAX tab with a POST that carries the form key, so this is a
 * POST action: a GET gets Magento's 404, as every other POST-only admin action does.
 */
class Tab extends Action implements HttpPostActionInterface
{
    /**
     * Whoever may view the order may see its transaction.
     *
     * @see _isAllowed()
     */
    public const ADMIN_RESOURCE = 'Magento_Sales::actions_view';

    public function __construct(
        Context $context,
        private readonly LayoutFactory $resultLayoutFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): Layout
    {
        return $this->resultLayoutFactory->create();
    }
}
