<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Controller\Adminhtml\Feed;

use Magento\Backend\App\Action;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use TradeTracker\Connect\Api\Config\RepositoryInterface as ConfigRepository;
use TradeTracker\Connect\Api\Feed\RepositoryInterface as FeedRepository;
use TradeTracker\Connect\Api\Log\RepositoryInterface as LogRepository;
use TradeTracker\Connect\Service\Api\Adapter;
use TradeTracker\Connect\Service\Feed\Progress as FeedProgress;

/**
 * Generates one feed from the configuration page without leaving it, so the page can poll
 * Feed\Progress and show how far the run is. Same checks as Feed\Generate, which stays the
 * fallback for a browser without JavaScript.
 */
class AjaxGenerate extends Action
{
    public const ADMIN_RESOURCE = 'TradeTracker_Connect::config';

    public function __construct(
        Action\Context $context,
        private readonly FeedRepository $feedRepository,
        private readonly ConfigRepository $configRepository,
        private readonly Adapter $adapter,
        private readonly LogRepository $logRepository,
        private readonly FeedProgress $progress,
        private readonly AuthSession $authSession
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
        if ($storeId <= 0) {
            return $result->setData([
                'success' => false,
                'error' => (string)__('Select a store view to generate the feed for.'),
            ]);
        }

        $credentials = $this->adapter->execute($this->configRepository->getApiCredentials($storeId));
        if (!$credentials['success']) {
            return $result->setData([
                'success' => false,
                'error' => (string)__(
                    'Please fill in a valid customer ID & Passphrase in the general section to create an XML feed.'
                ),
            ]);
        }

        // The run holds this worker for minutes. Without releasing the session the progress polls
        // from the same browser would queue up behind it and the bar would never move.
        $this->authSession->writeClose();
        $this->progress->clear($storeId);

        try {
            $generated = $this->feedRepository->generateAndSaveFeed($storeId, 'manual');
        } catch (\Throwable $exception) {
            $this->logRepository->addErrorLog('AjaxGenerate', $exception->getMessage());
            $this->progress->fail($storeId, $exception->getMessage());

            return $result->setData(['success' => false, 'error' => $exception->getMessage()]);
        }

        if (empty($generated['success'])) {
            return $result->setData(['success' => false, 'error' => (string)($generated['message'] ?? '')]);
        }

        // The page reloads once the row has reported the run, so the result is handed to the
        // session rather than to the response: a message put on the page by JavaScript would be
        // wiped by that reload.
        $this->messageManager->addSuccessMessage((string)$generated['message']);

        return $result->setData(['success' => true, 'message' => (string)$generated['message']]);
    }
}
