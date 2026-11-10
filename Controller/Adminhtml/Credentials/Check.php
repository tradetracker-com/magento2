<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Controller\Adminhtml\Credentials;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Model\StoreManagerInterface;
use TradeTracker\Connect\Api\Config\RepositoryInterface as ConfigRepository;
use TradeTracker\Connect\Service\Api\Adapter;

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
     * @var ConfigRepository
     */
    private $configRepository;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * Check constructor.
     *
     * @param Action\Context        $context
     * @param JsonFactory           $resultJsonFactory
     * @param Adapter               $adapter
     * @param ConfigRepository      $configRepository
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Action\Context $context,
        JsonFactory $resultJsonFactory,
        Adapter $adapter,
        ConfigRepository $configRepository,
        StoreManagerInterface $storeManager
    ) {
        $this->adapter = $adapter;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->configRepository = $configRepository;
        $this->storeManager = $storeManager;
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
            'passphrase' => $this->getPassphrase(),
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

    /**
     * Passphrase to test with.
     *
     * The passphrase field is an obscure (password) field: when a value is already saved the form only contains
     * a "******" placeholder. In that case the stored (decrypted) passphrase for the requested scope is used.
     *
     * @return string
     */
    private function getPassphrase(): string
    {
        $passphrase = (string)$this->getRequest()->getParam('passphrase');
        if ($passphrase !== '' && !preg_match('/^\*+$/', $passphrase)) {
            return $passphrase;
        }

        $credentials = $this->configRepository->getApiCredentials($this->getScopeStoreId());
        return (string)($credentials['passphrase'] ?? '');
    }

    /**
     * Store ID matching the scope currently selected in the admin configuration.
     *
     * @return int|null
     */
    private function getScopeStoreId(): ?int
    {
        if ($storeId = (int)$this->getRequest()->getParam('store')) {
            return $storeId;
        }

        if ($websiteId = (int)$this->getRequest()->getParam('website')) {
            foreach ($this->storeManager->getStores() as $store) {
                if ((int)$store->getWebsiteId() === $websiteId) {
                    return (int)$store->getId();
                }
            }
        }

        return null;
    }
}
