<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Model\Config\System;

use TradeTracker\Connect\Api\Config\System\AdvancedInterface;
use TradeTracker\Connect\Model\Config\Repository as ConfigRepository;

/**
 * Advanced settings provider class
 */
class AdvancedRepository extends ConfigRepository implements AdvancedInterface
{

    /**
     * @inheritDoc
     */
    public function isMultiCountryEnabled(?int $storeId = null): bool
    {
        return $this->isSetFlag(self::ENABLE_MULTI_COUNTRY, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getTGIValue(?int $storeId = null): string
    {
        return $this->getStoreValue(self::TGI_VALUE);
    }

    /**
     * @inheritDoc
     */
    public function getApiTimeout(): int
    {
        $timeout = (int)$this->getStoreValue(self::API_TIMEOUT);

        return $timeout > 0 ? $timeout : self::DEFAULT_API_TIMEOUT;
    }
}
