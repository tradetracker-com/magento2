<?php
/**
 * Copyright © Sooqr. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Controller\Adminhtml\Feed;

use Exception;
use Magento\Backend\App\Action;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Io\File;
use TradeTracker\Connect\Api\Feed\RepositoryInterface as FeedRepository;

/**
 * Generate controller for product feed
 */
class Download extends Action
{

    /**
     * @see _isAllowed()
     */
    public const ADMIN_RESOURCE = 'TradeTracker_Connect::config';

    /**
     * Error message
     */
    public const ERROR = 'File not found, please generate new feed.';

    /**
     * @var FeedRepository
     */
    private $feedRepository;
    /**
     * @var FileFactory
     */
    private $fileFactory;
    /**
     * @var RawFactory
     */
    private $resultRawFactory;
    /**
     * @var File
     */
    private $ioFilesystem;
    /**
     * @var RedirectInterface
     */
    private $redirect;

    /**
     * Download constructor.
     * @param Action\Context $context
     * @param FeedRepository $feedRepository
     * @param RawFactory $resultRawFactory
     * @param FileFactory $fileFactory
     * @param File $ioFilesystem
     */
    public function __construct(
        Action\Context $context,
        FeedRepository $feedRepository,
        RawFactory $resultRawFactory,
        FileFactory $fileFactory,
        File $ioFilesystem,
        RedirectInterface $redirect
    ) {
        $this->feedRepository = $feedRepository;
        $this->fileFactory = $fileFactory;
        $this->resultRawFactory = $resultRawFactory;
        $this->ioFilesystem = $ioFilesystem;
        $this->redirect = $redirect;
        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface
     * @throws Exception
     */
    public function execute()
    {
        $storeId = (int)$this->getRequest()->getParam('store_id');
        $type = (string)$this->getRequest()->getParam('type');

        try {
            $result = $this->feedRepository->getFeedLocation($storeId, $type);
            if (!$result['available']) {
                $exceptionMsg = self::ERROR;
                throw new LocalizedException(__($exceptionMsg));
            }

            $fileInfo = $this->ioFilesystem->getPathInfo($result['path']);
            $this->fileFactory->create(
                $fileInfo['basename'],
                [
                    'type' => 'filename',
                    'value' => 'tradetracker/' . $fileInfo['basename'],
                    'rm' => false,
                ],
                DirectoryList::MEDIA,
                'application/octet-stream',
                null
            );

            return $this->resultRawFactory->create();
        } catch (Exception $exception) {
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            $this->messageManager->addErrorMessage(__($exception->getMessage()));
            return $resultRedirect->setPath(
                $this->redirect->getRefererUrl()
            );
        }
    }
}
