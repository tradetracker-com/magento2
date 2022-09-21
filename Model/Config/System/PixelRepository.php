<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Model\Config\System;

use TradeTracker\Connect\Api\Config\System\PixelInterface;
use TradeTracker\Connect\Model\Config\Repository as ConfigRepository;

/**
 * Pixel provider class
 */
class PixelRepository extends ConfigRepository implements PixelInterface
{

    /**
     * @inheritDoc
     */
    public function getCampaignId(int $storeId = null): string
    {
        return $this->getStoreValue(self::XML_PATH_CAMPAIGN_ID, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getProductId(int $storeId = null): string
    {
        return $this->getStoreValue(self::XML_PATH_PRODUCT_ID, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getCustomCurrencyCode(int $storeId = null): ?string
    {
        $customCurrencyCode = $this->getStoreValue(self::XML_PATH_NON_DEFAULT_CURRENCY, $storeId);
        if (empty($customCurrencyCode) || $this->isSetFlag(self::XML_PATH_OVERWRITE_CURRENCY, $storeId)) {
            return null;
        }

        return $customCurrencyCode;
    }
}
