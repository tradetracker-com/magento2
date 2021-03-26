<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Api\Config\System;

use TradeTracker\Connect\Api\Config\RepositoryInterface;

/**
 * Feed group interface
 */
interface FeedInterface extends RepositoryInterface
{

    /** General Group */
    const XPATH_FEED_ENABELD = 'tradetracker/feed/enable';
    const XPATH_FEED_FILENAME = 'tradetracker/feed/filename';
    const XPATH_FEED_RESULT = 'tradetracker/feeds/results';
    const XPATH_CRON_FREQUENCY = 'tradetracker/feeds/cron_frequency';

    /** Product Data Group */
    const XML_PATH_NAME_SOURCE = 'tradetracker/feed/name_attribute';
    const XML_PATH_DESCRIPTION_SOURCE = 'tradetracker/feed/description_attribute';
    const XML_PATH_DESCRIPTION_LONG_SOURCE = 'tradetracker/feed/description_long_attribute';
    const XML_PATH_IMAGE_SOURCE = 'tradetracker/feed/image';
    const XML_PATH_IMAGE_MAIN = 'tradetracker/feed/main_image';
    const XML_PATH_EAN_SOURCE = 'tradetracker/feed/ean_attribute';
    const XML_PATH_BRAND_SOURCE = 'tradetracker/feed/brand_attribute';
    const XML_PATH_COLOR_SOURCE = 'tradetracker/feed/color_attribute';
    const XML_PATH_MATERIAL_SOURCE = 'tradetracker/feed/material_attribute';
    const XML_PATH_SIZE_SOURCE = 'tradetracker/feed/size_attribute';
    const XML_PATH_GENDER_SOURCE = 'tradetracker/feed/gender_attribute';
    const XML_PATH_EXTRA_INFO = 'tradetracker/feed/extra_info';
    const XPATH_CATEGORY_SOURCE = 'tradetracker/feed/category_source';
    const XPATH_CATEGORY_ATTRIBUTE = 'tradetracker/feed/category_attribute';
    const XPATH_CATEGORY_CUSTOM = 'tradetracker/feed/category_custom';
    const XPATH_DELIVERY_SOURCE = 'tradetracker/feed/delivery_source';
    const XPATH_DELIVERY_ATTRIBUTE = 'tradetracker/feed/delivery_attribute';
    const XPATH_DELIVERY_IN_STOCK = 'tradetracker/feed/delivery_in_stock';
    const XPATH_DELIVERY_OUT_OF_STOCK = 'tradetracker/feed/delivery_out_of_stock';

    /** Product Types Group */
    const XML_PATH_CONFIGURABLE = 'tradetracker/feed/configurable';
    const XML_PATH_CONFIGURABLE_LINK = 'tradetracker/feed/configurable_link';
    const XML_PATH_CONFIGURABLE_IMAGE = 'tradetracker/feed/configurable_image';
    const XML_PATH_CONFIGURABLE_PARENT_ATTS = 'tradetracker/feed/configurable_parent_atts';
    const XML_PATH_CONFIGURABLE_NONVISIBLE = 'tradetracker/feed/configurable_nonvisible';
    const XML_PATH_BUNDLE = 'tradetracker/feed/bundle';
    const XML_PATH_BUNDLE_LINK = 'tradetracker/feed/bundle_link';
    const XML_PATH_BUNDLE_IMAGE = 'tradetracker/feed/bundle_image';
    const XML_PATH_BUNDLE_PARENT_ATTS = 'tradetracker/feed/bundle_parent_atts';
    const XML_PATH_BUNDLE_NONVISIBLE = 'tradetracker/feed/bundle_nonvisible';
    const XML_PATH_GROUPED = 'tradetracker/feed/grouped';
    const XML_PATH_GROUPED_LINK = 'tradetracker/feed/grouped_link';
    const XML_PATH_GROUPED_IMAGE = 'tradetracker/feed/grouped_image';
    const XML_PATH_GROUPED_PARENT_PRICE = 'tradetracker/feed/grouped_parent_price';
    const XML_PATH_GROUPED_PARENT_ATTS = 'tradetracker/feed/grouped_parent_atts';
    const XML_PATH_GROUPED_NONVISIBLE = 'tradetracker/feed/grouped_nonvisible';

    /** Additional Configuration Group */
    const XML_PATH_EXTRA_FIELDS = 'tradetracker/feed/extra_fields';
    const XML_PATH_SHIPPING = 'tradetracker/feed/shipping';
    const XML_PATH_UTM_STRING = 'tradetracker/feed/utm_string';

    /** Filter Options Group */
    const XML_PATH_VISBILITY = 'tradetracker/feed/filter_visbility';
    const XML_PATH_VISIBILITY_OPTIONS = 'tradetracker/feed/filter_visbility_options';
    const XML_PATH_CATEGORY_FILTER = 'tradetracker/feed/filter_category';
    const XML_PATH_CATEGORY_FILTER_TYPE = 'tradetracker/feed/filter_type_category';
    const XML_PATH_CATEGORY_IDS = 'tradetracker/feed/filter_category_ids';
    const XML_PATH_STOCK = 'tradetracker/feed/filter_stock';
    const XML_PATH_FILTERS = 'tradetracker/feed/custom_filters';
    const XML_PATH_FILTERS_DATA = 'tradetracker/feed/custom_filters_data';

    /**
     * Check if feed generation is enabled
     *
     * @param int|null $storeId
     *
     * @return bool
     */
    public function isEnabled(int $storeId = null): bool;

    /**
     * Return all enabled storeIds
     *
     * @return array
     */
    public function getAllEnabledStoreIds(): array;

    /**
     * Returns cron frequency expression
     *
     * @return string
     * @see \TradeTracker\Connect\Model\Config\System\Source\CronFrequency
     */
    public function getCronFrequency(): string;

    /**
     * Returns array of attributes
     *
     * @param int $storeId
     *
     * @return array
     */
    public function getAttributes(int $storeId): array;

    /**
     * Get Extra fields array
     *
     * @param int $storeId
     *
     * @return array
     */
    public function getExtraFields(int $storeId): array;

    /**
     * Get 'image' fields array
     *
     * @param int $storeId
     *
     * @return array
     */
    public function getImageAttributes(int $storeId): array;

    /**
     * Returns array of static fields
     *
     * @param int $storeId
     *
     * @return array
     */
    public function getStaticFields(int $storeId): array;

    /**
     * Get source for category feed entity
     *
     * @param int $storeId
     *
     * @return string
     * @see \TradeTracker\Connect\Model\Config\System\Source\CategorySource
     */
    public function getCategorySource(int $storeId): string;

    /**
     * Get custom field for category
     *
     * @param int $storeId
     *
     * @return string
     */
    public function getCategoryCustomField(int $storeId): string;

    /**
     * Get product data filters
     *
     * @param int $storeId
     * @return array
     */
    public function getFilters(int $storeId): array;

    /**
     * Get 'configurable' products data behaviour
     *
     * @param int $storeId
     * @return array
     */
    public function getConfigProductsBehaviour(int $storeId): array;

    /**
     * Get 'bundle' products data behaviour
     *
     * @param int $storeId
     * @return array
     */
    public function getBundleProductsBehaviour(int $storeId): array;

    /**
     * Get 'grouped' products data behaviour
     *
     * @param int $storeId
     * @return array
     */
    public function getGroupedProductsBehaviour(int $storeId): array;

    /**
     * @param int $storeId
     * @return string
     */
    public function getFeedGenerationResult(int $storeId): string;

    /**
     * @param int $storeId
     * @param string $msg
     */
    public function setFeedGenerationResult(int $storeId, string $msg): void;

    /**
     * Get filename of feed
     *
     * @param int $storeId
     * @return string
     */
    public function getFileName(int $storeId): string;
}
