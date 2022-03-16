<?php
/**
 * Copyright © Sooqr. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Controller\Adminhtml\Feed;

use Magento\Backend\App\Action;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\DriverInterface;
use TradeTracker\Connect\Api\Feed\RepositoryInterface as FeedRepository;

/**
 * Preview controller for product feed
 */
class Preview extends Action
{

    /**
     * @see _isAllowed()
     */
    public const ADMIN_RESOURCE = 'TradeTracker_Connect::config';

    /**
     * @var FeedRepository
     */
    private $feedRepository;
    /**
     * @var DriverInterface
     */
    private $fileDriver;
    /**
     * @var RedirectInterface
     */
    private $redirect;

    /**
     * Generate constructor.
     * @param FeedRepository $feedRepository
     * @param DriverInterface $fileDriver
     * @param Action\Context $context
     */
    public function __construct(
        Action\Context $context,
        FeedRepository $feedRepository,
        DriverInterface $fileDriver,
        RedirectInterface $redirect
    ) {
        $this->feedRepository = $feedRepository;
        $this->fileDriver = $fileDriver;
        $this->redirect = $redirect;
        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface|void
     */
    public function execute()
    {
        $storeId = (int)$this->getRequest()->getParam('store_id');
        $type = $this->getRequest()->getParam('type', 'preview');
        $result = $this->feedRepository->generateAndSaveFeed($storeId, $type);

        if (!$result['success']) {
            $this->messageManager->addErrorMessage($result['message']);
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            return $resultRedirect->setPath(
                $this->redirect->getRefererUrl()
            );
        }

        try {
            $this->getResponse()->setHeader('Content-type', 'text/xml');
            $this->getResponse()->setBody($this->fileDriver->fileGetContents($result['path']));
            return;
        } catch (FileSystemException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            return $resultRedirect->setPath(
                $this->redirect->getRefererUrl()
            );
        }
    }
}
