<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Controller\Adminhtml\Feed;

use Magento\Backend\App\Action;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use TradeTracker\Connect\Service\Feed\Progress as FeedProgress;

/**
 * Reports how far a running feed generation is, for the progress bar on the configuration page.
 */
class Progress extends Action
{
    public const ADMIN_RESOURCE = 'TradeTracker_Connect::config';

    public function __construct(
        Action\Context $context,
        private readonly FeedProgress $progress
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $request = $this->getRequest();
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        if (!$request instanceof HttpRequest || !$request->isXmlHttpRequest()) {
            return $this->resultRedirectFactory->create()
                ->setPath('adminhtml/system_config/edit/section/tradetracker_feed');
        }

        $storeId = (int)$request->getParam('store_id');
        $state = $storeId > 0 ? $this->progress->get($storeId) : null;

        if ($state === null) {
            return $result->setData(['success' => true, 'running' => false]);
        }

        return $result->setData(['success' => true, 'running' => !$state['done']] + $state);
    }
}
