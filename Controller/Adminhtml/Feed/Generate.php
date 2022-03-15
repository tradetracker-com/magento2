<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Controller\Adminhtml\Feed;

use Magento\Backend\App\Action;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use TradeTracker\Connect\Api\Config\RepositoryInterface as ConfigRepository;
use TradeTracker\Connect\Api\Feed\RepositoryInterface as FeedRepository;
use TradeTracker\Connect\Service\Api\Adapter;

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
    public const ADMIN_RESOURCE = 'TradeTracker_Connect::feed_generate';

    /**
     * @var FeedRepository
     */
    private $feedRepository;
    /**
     * @var ConfigRepository
     */
    private $configRepository;
    /**
     * @var Adapter
     */
    private $adapter;

    /**
     * @var RedirectInterface
     */
    private $redirect;

    /**
     * Generate constructor.
     * @param FeedRepository $feedRepository
     * @param Action\Context $context
     * @param ConfigRepository $configRepository
     * @param Adapter $adapter
     */
    public function __construct(
        FeedRepository $feedRepository,
        Action\Context $context,
        ConfigRepository $configRepository,
        Adapter $adapter,
        RedirectInterface $redirect
    ) {
        $this->feedRepository = $feedRepository;
        $this->configRepository = $configRepository;
        $this->adapter = $adapter;
        $this->redirect = $redirect;
        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        $storeId = (int)$this->getRequest()->getParam('store_id');
        $apiCredentials = $this->configRepository->getApiCredentials($storeId);
        $result = $this->adapter->execute($apiCredentials);
        if (!$result['success']) {
            $this->messageManager->addErrorMessage(
                'Please fill in a valid customer ID & Passphrase in the general section to create an XML feed.'
            );
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            return $resultRedirect->setPath(
                $this->redirect->getRefererUrl()
            );
        }
        $result = $this->feedRepository->generateAndSaveFeed($storeId, 'manual');
        if ($result['success']) {
            $this->messageManager->addSuccessMessage($result['message']);
        } else {
            $this->messageManager->addErrorMessage($result['message']);
        }

        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath(
            $this->redirect->getRefererUrl()
        );
    }
}
