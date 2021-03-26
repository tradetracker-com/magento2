<?php
/**
 * Copyright © Sooqr. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Controller\Adminhtml\Feed;

use Magento\Backend\App\Action;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use TradeTracker\Connect\Api\Feed\RepositoryInterface as FeedRepository;

/**
 * Generate controller for product feed
 */
class Generate extends Action
{

    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'TradeTracker_Connect::feed_generate';

    /**
     * @var FeedRepository
     */
    private $feedRepository;

    /**
     * Generate constructor.
     * @param FeedRepository $feedRepository
     * @param Action\Context $context
     */
    public function __construct(
        FeedRepository $feedRepository,
        Action\Context $context
    ) {
        $this->feedRepository = $feedRepository;
        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        $storeId = (int)$this->getRequest()->getParam('store_id');

        $result = $this->feedRepository->generateAndSaveFeed($storeId, 'manual');
        if ($result['success']) {
            $this->messageManager->addSuccessMessage($result['message']);
        } else {
            $this->messageManager->addErrorMessage($result['message']);
        }

        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath(
            $this->_redirect->getRefererUrl()
        );
    }
}
