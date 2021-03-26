<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Model\Config\System;

use TradeTracker\Connect\Api\Config\System\DirectLinkingInterface;
use TradeTracker\Connect\Model\Config\Repository as ConfigRepository;

/**
 * Direct Linking provider class
 */
class DirectLinkingRepository extends ConfigRepository implements DirectLinkingInterface
{

    /**
     * @inheritDoc
     */
    public function isEnabled(int $storeId = null): bool
    {
        if (!parent::isEnabled($storeId)) {
            return false;
        }

        return $this->isSetFlag(self::XML_PATH_REDIRECT_ENABLE, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getRedirectUrl(int $storeId = null): string
    {
        if (!$storeId) {
            $storeId = (int)$this->getStore()->getId();
        }
        return (string)$this->getStoreValue(self::XML_PATH_REDIRECT_URL, $storeId);
    }
}
