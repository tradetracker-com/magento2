<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Model\Config;

use Exception;
use Magento\Config\Model\ResourceModel\Config as ConfigData;
use Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory as ConfigDataCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use TradeTracker\Connect\Api\Config\RepositoryInterface as ConfigRepositoryInterface;

/**
 * Config repository class
 */
class Repository implements ConfigRepositoryInterface
{

    /**
     * @var StoreManagerInterface
     */
    public $storeManager;
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;
    /**
     * @var Json
     */
    private $json;
    /**
     * @var ProductMetadataInterface
     */
    private $metadata;
    /**
     * @var ConfigDataCollectionFactory
     */
    private $configDataCollectionFactory;
    /**
     * @var ConfigData
     */
    private $config;

    /**
     * Repository constructor.
     *
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param ConfigDataCollectionFactory $configDataCollectionFactory
     * @param ConfigData $config
     * @param Json $json
     * @param ProductMetadataInterface $metadata
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        ConfigDataCollectionFactory $configDataCollectionFactory,
        ConfigData $config,
        Json $json,
        ProductMetadataInterface $metadata
    ) {
        $this->storeManager = $storeManager;
        $this->configDataCollectionFactory = $configDataCollectionFactory;
        $this->scopeConfig = $scopeConfig;
        $this->config = $config;
        $this->json = $json;
        $this->metadata = $metadata;
    }

    /**
     * {@inheritDoc}
     */
    public function getExtensionVersion(): string
    {
        return $this->getStoreValue(self::XML_PATH_EXTENSION_VERSION);
    }

    /**
     * Get Configuration data
     *
     * @param string $path
     * @param int|null $storeId
     * @param string|null $scope
     *
     * @return string
     */
    protected function getStoreValue(
        string $path,
        int $storeId = null,
        string $scope = null
    ): string {
        if (!$storeId) {
            $storeId = (int)$this->getStore()->getId();
        }
        $scope = $scope ?? ScopeInterface::SCOPE_STORE;
        return (string)$this->scopeConfig->getValue($path, $scope, (int)$storeId);
    }

    /**
     * {@inheritDoc}
     */
    public function getStore(int $storeId = null): StoreInterface
    {
        try {
            if ($storeId) {
                return $this->storeManager->getStore($storeId);
            } else {
                return $this->storeManager->getStore();
            }
        } catch (Exception $e) {
            if ($store = $this->storeManager->getDefaultStoreView()) {
                return $store;
            }
        }
        $stores = $this->storeManager->getStores();
        return reset($stores);
    }

    /**
     * {@inheritDoc}
     */
    public function getMagentoVersion(): string
    {
        return $this->metadata->getVersion();
    }

    /**
     * @inheritDoc
     */
    public function isEnabled(int $storeId = null): bool
    {
        return $this->isSetFlag(self::XML_PATH_EXTENSION_ENABLE, $storeId);
    }

    /**
     * Get config value flag
     *
     * @param string $path
     * @param int|null $storeId
     * @param string|null $scope
     *
     * @return bool
     */
    protected function isSetFlag(string $path, int $storeId = null, string $scope = null): bool
    {
        if (empty($scope)) {
            $scope = ScopeInterface::SCOPE_STORE;
        }

        if (empty($storeId)) {
            $storeId = $this->getStore()->getId();
        }
        return $this->scopeConfig->isSetFlag($path, $scope, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function logDebug(): bool
    {
        return $this->isSetFlag(self::XML_PATH_DEBUG);
    }

    /**
     * @inheritDoc
     */
    public function getApiCredentials(int $storeId = null): array
    {
        return [
            'customer_id' => $this->getCustomerId($storeId),
            'passphrase' => $this->getPassphrase($storeId),
            'sandbox' => $this->isSandbox($storeId),
            'locale' => 'en_GB',
            'demo' => $this->isDemo($storeId)
        ];
    }

    /**
     * Get customer ID
     *
     * @param int|null $storeId
     *
     * @return int
     */
    private function getCustomerId(int $storeId = null): int
    {
        return (int)$this->getStoreValue(self::XML_PATH_CUSTOMER_ID, $storeId);
    }

    /**
     * Get passphrase
     *
     * @param int|null $storeId
     *
     * @return string
     */
    private function getPassphrase(int $storeId = null): string
    {
        return $this->getStoreValue(self::XML_PATH_PASSPHRASE, $storeId);
    }

    /**
     * Is currently enabled sandbox mode
     *
     * @param int|null $storeId
     *
     * @return bool
     */
    private function isSandbox(int $storeId = null): bool
    {
        return $this->isSetFlag(self::XML_PATH_SANDBOX, $storeId);
    }

    /**
     * Is currently enabled demo mode
     *
     * @param int|null $storeId
     *
     * @return bool
     */
    private function isDemo(int $storeId = null): bool
    {
        return $this->isSetFlag(self::XML_PATH_DEMO, $storeId);
    }

    /**
     * Retrieve config value array by path, storeId and scope
     *
     * @param string $path
     * @param int|null $storeId
     * @param string|null $scope
     *
     * @return array
     */
    protected function getStoreValueArray(string $path, int $storeId = null, string $scope = null): array
    {
        $value = $this->getStoreValue($path, (int)$storeId, $scope);

        if (empty($value)) {
            return [];
        }

        try {
            return $this->json->unserialize($value);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Return uncached store config data
     *
     * @param string $path
     * @param int|null $storeId
     *
     * @return string
     */
    protected function getUncachedStoreValue(string $path, int $storeId = null): string
    {
        $collection = $this->configDataCollectionFactory->create()
            ->addFieldToSelect('value')
            ->addFieldToFilter('path', $path);

        if ($storeId > 0) {
            $collection->addFieldToFilter('scope_id', $storeId);
            $collection->addFieldToFilter('scope', 'stores');
        } else {
            $collection->addFieldToFilter('scope_id', 0);
            $collection->addFieldToFilter('scope', 'default');
        }

        $collection->getSelect()->limit(1);

        return (string)$collection->getFirstItem()->getData('value');
    }

    /**
     * Set Store data
     *
     * @param string $value
     * @param string $key
     * @param int|null $storeId
     */
    protected function setConfigData(string $value, string $key, int $storeId = null): void
    {
        if ($storeId) {
            $this->config->saveConfig($key, $value, 'stores', $storeId);
        } else {
            $this->config->saveConfig($key, $value, 'default', 0);
        }
    }
}
