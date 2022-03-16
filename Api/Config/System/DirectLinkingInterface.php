<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Api\Config\System;

use TradeTracker\Connect\Api\Config\RepositoryInterface;

/**
 * DirectLinking group interface
 */
interface DirectLinkingInterface extends RepositoryInterface
{

    public const XML_PATH_REDIRECT_ENABLE = 'tradetracker/direct_linking/enable';
    public const XML_PATH_REDIRECT_URL = 'tradetracker/direct_linking/url_key';

    /**
     * Check if direct linking is enabled
     *
     * @param int|null $storeId
     *
     * @return bool
     */
    public function isEnabled(int $storeId = null): bool;

    /**
     * Get redirect url key
     *
     * @param int|null $storeId
     *
     * @return string
     */
    public function getRedirectUrl(int $storeId = null): string;
}
