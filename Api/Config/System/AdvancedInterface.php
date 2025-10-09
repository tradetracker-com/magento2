<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Api\Config\System;

use TradeTracker\Connect\Api\Config\RepositoryInterface;

/**
 * Advanced group interface
 */
interface AdvancedInterface extends RepositoryInterface
{

    public const ENABLE_MULTI_COUNTRY = 'tradetracker/advanced/enable_multi_country';
    public const TGI_VALUE = 'tradetracker/advanced/tgi_value';

    /**
     * Check if multi country tracking is enabled
     *
     * @param int|null $storeId
     *
     * @return bool
     */
    public function isMultiCountryEnabled(?int $storeId = null): bool;

    /**
     * Get TGI Value
     *
     * @param int|null $storeId
     *
     * @return string
     */
    public function getTGIValue(?int $storeId = null): string;
}
