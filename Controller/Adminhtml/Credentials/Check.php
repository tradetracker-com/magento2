<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Controller\Adminhtml\Credentials;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\Json;
use TradeTracker\Connect\Service\Api\Adapter;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * Class Check
 *
 * AJAX controller to is provided credentials correct
 */
class Check extends Action
{

    /**
     * @see _isAllowed()
     */
    public const ADMIN_RESOURCE = 'TradeTracker_Connect::config';

    /**
     * @var Adapter
     */
    private $adapter;
    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * Check constructor.
     *
     * @param Action\Context $context
     * @param JsonFactory    $resultJsonFactory
     * @param Adapter        $adapter
     */
    public function __construct(
        Action\Context $context,
        JsonFactory $resultJsonFactory,
        Adapter $adapter
    ) {
        $this->adapter = $adapter;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->messageManager = $context->getMessageManager();
        parent::__construct($context);
    }

    /**
     * @return Json
     */
    public function execute()
    {
        $data = [
            'customer_id' => (int)$this->getRequest()->getParam('customer_id'),
            'passphrase' => $this->getRequest()->getParam('passphrase'),
            'sandbox' => false,
            'locale' => 'en_GB',
            'demo' => false
        ];
        $result = $this->adapter->execute($data);
        if ($result['success']) {
            $result = 'Success!';
        } else {
            $result = $result['error'];
        }
        $resultJson = $this->resultJsonFactory->create();
        return $resultJson->setData(['result' => $result]);
    }
}
