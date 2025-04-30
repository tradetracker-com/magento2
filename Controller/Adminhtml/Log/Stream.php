<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Controller\Adminhtml\Log;

use Magento\Backend\App\Action;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use TradeTracker\Connect\Api\Log\RepositoryInterface as LogRepository;

/**
 * AJAX controller to check logs
 */
class Stream extends Action
{

    public const ADMIN_RESOURCE = 'TradeTracker_Connect::config';

    private JsonFactory $resultJsonFactory;
    private LogRepository $logRepository;
    private RequestInterface $request;

    /**
     * @param Action\Context $context
     * @param JsonFactory $resultJsonFactory
     * @param LogRepository $logRepository
     */
    public function __construct(
        Action\Context $context,
        JsonFactory $resultJsonFactory,
        LogRepository $logRepository
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->request = $context->getRequest();
        $this->logRepository = $logRepository;
        parent::__construct($context);
    }

    /**
     * @return Json
     */
    public function execute(): Json
    {
        $resultJson = $this->resultJsonFactory->create();
        $type = $this->request->getParam('type') == 'error' ? 'error' : 'debug';
        $logFilePath = $this->logRepository->getLogFilePath($type);

        if ($logFilePath && $result = $this->logRepository->getLogEntriesAsArray($logFilePath)) {
            $result = ['result' => $result];
        } else {
            $result = __('Log is empty');
        }

        return $resultJson->setData($result);
    }
}
