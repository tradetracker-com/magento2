<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Api\Config\System;

use TradeTracker\Connect\Api\Config\RepositoryInterface;

/**
 * Pixel group interface
 */
interface PixelInterface extends RepositoryInterface
{

    /** Pixel Group */
    public const XML_PATH_PIXEL_ENABLE = 'tradetracker/pixel/enable';
    public const XML_PATH_ORDER_TAB = 'tradetracker/pixel/order_tab';
    public const XML_PATH_CAMPAIGN_ID = 'tradetracker/pixel/campaign_id';
    public const XML_PATH_PRODUCT_ID = 'tradetracker/pixel/product_id';
    public const XML_PATH_OVERWRITE_CURRENCY = 'tradetracker/pixel/overwrite_currency';
    public const XML_PATH_NON_DEFAULT_CURRENCY = 'tradetracker/pixel/non_default_currency';

    /**
     * Check if the module is enabled; the pixel has its own switch, see isPixelEnabled()
     *
     * @param int|null $storeId
     *
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool;

    /**
     * Check if the conversion pixel is enabled for the store view
     *
     * @param int|null $storeId
     *
     * @return bool
     */
    public function isPixelEnabled(?int $storeId = null): bool;

    /**
     * Check if the TradeTracker Conversion tab is shown on the order page for orders of the store view
     *
     * @param int|null $storeId
     *
     * @return bool
     */
    public function isOrderTabEnabled(?int $storeId = null): bool;

    /**
     * Get campaign ID from config
     *
     * @param int|null $storeId
     *
     * @return string
     */
    public function getCampaignId(?int $storeId = null): string;

    /**
     * Get product ID from config
     *
     * @param int|null $storeId
     *
     * @return string
     */
    public function getProductId(?int $storeId = null): string;

    /**
     * Get custom currency code if set
     *
     * @param int|null $storeId
     *
     * @return null|string
     */
    public function getCustomCurrencyCode(?int $storeId = null): ?string;
}
