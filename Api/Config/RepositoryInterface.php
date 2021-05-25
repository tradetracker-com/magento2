<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Api\Config;

use Magento\Store\Api\Data\StoreInterface;

/**
 * Config repository interface
 */
interface RepositoryInterface
{

    /** Module's extension code */
    const EXTENSION_CODE = 'TradeTracker_Connect';

    /** General Group */
    const XML_PATH_EXTENSION_ENABLE = 'tradetracker/general/enable';
    const XML_PATH_EXTENSION_VERSION = 'tradetracker/general/version';
    const XML_PATH_DEBUG = 'tradetracker/general/debug';
    const XML_PATH_CUSTOMER_ID = 'tradetracker/general/customer_id';
    const XML_PATH_PASSPHRASE = 'tradetracker/general/passphrase';
    const XML_PATH_SANDBOX = 'tradetracker/general/sandbox';
    const XML_PATH_DEMO = 'tradetracker/general/demo';

    /**
     * Get extension version
     *
     * @return string
     */
    public function getExtensionVersion(): string;

    /**
     * Get Magento Version
     *
     * @return string
     */
    public function getMagentoVersion(): string;

    /**
     * Check if module is enabled
     *
     * @param int|null $storeId
     *
     * @return bool
     */
    public function isEnabled(int $storeId = null): bool;

    /**
     * Get API Credentials
     *
     * @param int|null $storeId
     *
     * @return array
     */
    public function getApiCredentials(int $storeId = null): array;

    /**
     * Get current or specified store
     *
     * @param int|null $storeId
     *
     * @return StoreInterface
     */
    public function getStore(int $storeId = null): StoreInterface;

    /**
     * Returns true if debug log is enabled
     *
     * @return bool
     */
    public function logDebug(): bool;
}
