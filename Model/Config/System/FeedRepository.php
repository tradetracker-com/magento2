<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Model\Config\System;

use TradeTracker\Connect\Api\Config\System\FeedInterface;
use TradeTracker\Connect\Model\Config\Repository as ConfigRepository;

/**
 * Feed provider class
 */
class FeedRepository extends ConfigRepository implements FeedInterface
{

    /**
     * @inheritDoc
     */
    public function getAllEnabledStoreIds(): array
    {
        $storeIds = [];
        foreach ($this->storeManager->getStores() as $store) {
            if ($this->isEnabled((int)$store->getId()) && $store->getIsActive()) {
                $storeIds[] = (int)$store->getId();
            }
        }

        return $storeIds;
    }

    /**
     * @inheritDoc
     */
    public function isEnabled(int $storeId = null): bool
    {
        if (!parent::isEnabled($storeId)) {
            return false;
        }

        return $this->isSetFlag(self::XPATH_FEED_ENABELD, $storeId);
    }

    /**
     * @return string
     */
    public function getCronFrequency(): string
    {
        return $this->getStoreValue(self::XPATH_CRON_FREQUENCY);
    }

    /**
     * @inheritDoc
     */
    public function getAttributes($storeId): array
    {
        $attributes = [
            'name' => $this->getNameAttribute($storeId),
            'description' => $this->getDescriptionAttribute($storeId),
            'description_long' => $this->getLongDescriptionAttribute($storeId),
            'ean' => $this->getEanAttribute($storeId),
            'brand' => $this->getBrandAttribute($storeId),
            'color' => $this->getColorAttribute($storeId),
            'material' => $this->getMaterialAttribute($storeId),
            'size' => $this->getSizeAttribute($storeId),
            'gender' => $this->getGenderAttribute($storeId)
        ];

        if ($this->getCategorySource($storeId) == 'attribute') {
            $attributes += ['category_custom' => $this->getCategoryAttribute($storeId)];
        }

        if ($this->getDeliverySource($storeId) == 'attribute') {
            $attributes += ['delivery_time' => $this->getDeliveryAttribute($storeId)];
        }

        return $attributes;
    }

    /**
     * Get selected attribute for 'name'
     *
     * @param int $storeId
     *
     * @return string
     */
    private function getNameAttribute(int $storeId): string
    {
        return $this->getStoreValue(self::XML_PATH_NAME_SOURCE, $storeId);
    }

    /**
     * Get selected attribute for 'description'
     *
     * @param int $storeId
     *
     * @return string
     */
    private function getDescriptionAttribute(int $storeId): string
    {
        return $this->getStoreValue(self::XML_PATH_DESCRIPTION_SOURCE, $storeId);
    }

    /**
     * Get selected attribute for 'long description'
     *
     * @param int $storeId
     *
     * @return string
     */
    private function getLongDescriptionAttribute(int $storeId): string
    {
        return $this->getStoreValue(self::XML_PATH_DESCRIPTION_LONG_SOURCE, $storeId);
    }

    /**
     * Get selected attribute for 'ean'
     *
     * @param int $storeId
     *
     * @return string
     */
    private function getEanAttribute(int $storeId): string
    {
        return $this->getStoreValue(self::XML_PATH_EAN_SOURCE, $storeId);
    }

    /**
     * Get selected attribute for 'brand'
     *
     * @param int $storeId
     *
     * @return string
     */
    private function getBrandAttribute(int $storeId): string
    {
        return $this->getStoreValue(self::XML_PATH_BRAND_SOURCE, $storeId);
    }

    /**
     * Get selected attribute for 'color'
     *
     * @param int $storeId
     *
     * @return string
     */
    private function getColorAttribute(int $storeId): string
    {
        return $this->getStoreValue(self::XML_PATH_COLOR_SOURCE, $storeId);
    }

    /**
     * Get selected attribute for 'material'
     *
     * @param int $storeId
     *
     * @return string
     */
    private function getMaterialAttribute(int $storeId): string
    {
        return $this->getStoreValue(self::XML_PATH_MATERIAL_SOURCE, $storeId);
    }

    /**
     * Get selected attribute for 'size'
     *
     * @param int $storeId
     *
     * @return string
     */
    private function getSizeAttribute(int $storeId): string
    {
        return $this->getStoreValue(self::XML_PATH_SIZE_SOURCE, $storeId);
    }

    /**
     * Get selected attribute for 'gender'
     *
     * @param int $storeId
     *
     * @return string
     */
    private function getGenderAttribute(int $storeId): string
    {
        return $this->getStoreValue(self::XML_PATH_GENDER_SOURCE, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getCategorySource(int $storeId): string
    {
        return $this->getStoreValue(self::XPATH_CATEGORY_SOURCE, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getCategoryAttribute(int $storeId = null): ?string
    {
        return $this->getStoreValue(self::XPATH_CATEGORY_ATTRIBUTE, $storeId);
    }

    /**
     * Get source for 'delivery time' feed entity
     *
     * @param int $storeId
     *
     * @return string
     * @see \TradeTracker\Connect\Model\Config\System\Source\SourceType
     */
    private function getDeliverySource(int $storeId): string
    {
        return $this->getStoreValue(self::XPATH_DELIVERY_SOURCE, $storeId);
    }

    /**
     * Get attribute for 'delivery time'
     *
     * @param int $storeId
     *
     * @return string
     */
    private function getDeliveryAttribute(int $storeId): string
    {
        return $this->getStoreValue(self::XPATH_DELIVERY_ATTRIBUTE, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getImageAttributes(int $storeId): array
    {
        $source = $this->getStoreValue(self::XML_PATH_IMAGE_SOURCE, $storeId);
        if ($source == 'all') {
            return [
                'image' => 'image',
                'small_image' => 'small_image',
                'thumbnail' => 'thumbnail',
                'swatch_image' => 'swatch_image',
                'main_image' => $this->getStoreValue(self::XML_PATH_IMAGE_MAIN, $storeId),
            ];
        } else {
            return ['image' => $source];
        }
    }

    /**
     * @inheritDoc
     */
    public function getExtraFields(int $storeId): array
    {
        return $this->getStoreValueArray(self::XML_PATH_EXTRA_FIELDS, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getStaticFields($storeId): array
    {
        $staticFields = [
            'currency' => $this->getStore($storeId)->getCurrentCurrency()->getCode(),
            'extra_info' => $this->getExtraInfo($storeId),
            'utm_string' => $this->getUtmString($storeId)
        ];

        if ($this->getCategorySource($storeId) == 'custom') {
            $staticFields += ['category_custom' => $this->getCategoryCustomField($storeId)];
        }

        if ($shippingPrices = $this->getShipping($storeId)) {
            foreach ($shippingPrices as $shippingPrice) {
                $condition = sprintf(
                    'final_price between %s/%s',
                    $shippingPrice['price_from'],
                    $shippingPrice['price_to']
                );
                $staticFields['shipping_price'][$condition] = $shippingPrice['price'];
            }
        }

        if ($this->getDeliverySource($storeId) == 'static') {
            $staticFields['delivery_time']['is_in_stock == 1'] = $this->getDeliveryInStock($storeId);
            $staticFields['delivery_time']['is_in_stock != 1'] = $this->getDeliveryOutOfStock($storeId);
        }

        return $staticFields;
    }

    /**
     * Get extra info open field
     *
     * @param int $storeId
     *
     * @return string
     */
    private function getExtraInfo(int $storeId): string
    {
        return $this->getStoreValue(self::XML_PATH_EXTRA_INFO, $storeId);
    }

    /**
     * Get UTM codes string
     *
     * @param int $storeId
     *
     * @return string
     */
    private function getUtmString(int $storeId): string
    {
        return $this->getStoreValue(self::XML_PATH_UTM_STRING, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getCategoryCustomField(int $storeId): string
    {
        return $this->getStoreValue(self::XPATH_CATEGORY_CUSTOM, $storeId);
    }

    /**
     * Get Shipping Prices array
     *
     * @param int $storeId
     *
     * @return array
     */
    private function getShipping(int $storeId): array
    {
        return $this->getStoreValueArray(self::XML_PATH_SHIPPING, $storeId);
    }

    /**
     * Get static value for 'out of stock' delivery time
     *
     * @param int $storeId
     *
     * @return string
     */
    private function getDeliveryInStock(int $storeId): string
    {
        return $this->getStoreValue(self::XPATH_DELIVERY_IN_STOCK, $storeId);
    }

    /**
     * Get static value for 'in stock' delivery time
     *
     * @param int $storeId
     *
     * @return string
     */
    private function getDeliveryOutOfStock(int $storeId): string
    {
        return $this->getStoreValue(self::XPATH_DELIVERY_OUT_OF_STOCK, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getFilters(int $storeId): array
    {
        return [
            'filter_by_visibility' => $this->restricProductFeedByVisibility($storeId),
            'visibility' => $this->productFeedVisibilityRestrictions($storeId),
            'restrict_by_category' => $this->restrictProductFeedByCategory($storeId),
            'category_restriction_behaviour' => $this->categoryRestrictionsFilterType($storeId),
            'category' => $this->getCategoryIds($storeId),
            'add_disabled_products' => !$this->excludeOutOfStock($storeId),
            'advanced_filters' => $this->getAdvancedFiltersData($storeId),
        ];
    }

    /**
     * Restrict by 'visibility'
     *
     * @param int $storeId
     *
     * @return bool
     */
    private function restricProductFeedByVisibility(int $storeId): bool
    {
        return $this->isSetFlag(self::XML_PATH_VISBILITY, $storeId);
    }

    /**
     * Only add products with these following Visibility
     *
     * @param int $storeId
     *
     * @return array
     */
    private function productFeedVisibilityRestrictions(int $storeId): array
    {
        return $this->getStoreValueArray(self::XML_PATH_VISIBILITY_OPTIONS, $storeId);
    }

    /**
     * Restrict by 'category'
     *
     * @param int $storeId
     *
     * @return bool
     */
    private function restrictProductFeedByCategory(int $storeId): bool
    {
        return $this->isSetFlag(self::XML_PATH_CATEGORY_FILTER, $storeId);
    }

    /**
     * Get category restriction filter type
     *
     * @param int $storeId
     *
     * @return string
     * @see \TradeTracker\Connect\Model\Config\System\Source\CategoryTypeList
     */
    private function categoryRestrictionsFilterType(int $storeId): string
    {
        return (string)$this->getStoreValue(self::XML_PATH_CATEGORY_FILTER_TYPE, $storeId);
    }

    /**
     * Only add products that belong to these categories
     *
     * @param int $storeId
     *
     * @return array
     */
    private function getCategoryIds(int $storeId): array
    {
        $categoryIds = $this->getStoreValue(self::XML_PATH_CATEGORY_IDS, $storeId);
        return $categoryIds ? explode(',', $categoryIds) : [];
    }

    /**
     * Exclude of of stock products
     *
     * @param int $storeId
     *
     * @return bool
     */
    public function excludeOutOfStock(int $storeId): bool
    {
        return $this->isSetFlag(self::XML_PATH_STOCK, $storeId);
    }

    /**
     * Advanced filter data
     *
     * @param int $storeId
     *
     * @return array
     */
    public function getAdvancedFiltersData(int $storeId): array
    {
        if (!$this->isSetFlag(self::XML_PATH_FILTERS, $storeId)) {
            return [];
        }
        return $this->getStoreValueArray(self::XML_PATH_FILTERS_DATA, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getConfigProductsBehaviour(int $storeId): array
    {
        return [
            'use' => $this->configurableProductLogic($storeId),
            'use_parent_url' => $this->configurableProductUrl($storeId),
            'use_parent_images' => $this->configurableProductImage($storeId),
            'use_parent_attributes' => $this->configurableParentAttributes($storeId),
            'use_non_visible_fallback' => $this->configurableNonVisibleFallback($storeId)
        ];
    }

    /**
     * Logic for 'configurable' products
     *
     * @param int $storeId
     *
     * @return string
     * @see \TradeTracker\Connect\Model\Config\System\Source\Configurable\Options
     */
    private function configurableProductLogic(int $storeId): string
    {
        return (string)$this->getStoreValue(self::XML_PATH_CONFIGURABLE, $storeId);
    }

    /**
     * Logic for 'configurable' product links
     *
     * @param int $storeId
     *
     * @return string
     */
    private function configurableProductUrl(int $storeId): string
    {
        return $this->getStoreValue(self::XML_PATH_CONFIGURABLE_LINK, $storeId);
    }

    /**
     * Logic for 'configurable' product image
     *
     * @param int $storeId
     *
     * @return int
     * @see \TradeTracker\Connect\Model\Config\System\Source\Configurable\Image
     */
    private function configurableProductImage(int $storeId): int
    {
        return (int)$this->getStoreValue(self::XML_PATH_CONFIGURABLE_IMAGE, $storeId);
    }

    /**
     * Attributes that should be forced to get data from parent 'configurable' product
     *
     * @param int $storeId
     *
     * @return array
     */
    private function configurableParentAttributes(int $storeId): array
    {
        $attributes = $this->getStoreValue(self::XML_PATH_CONFIGURABLE_PARENT_ATTS, $storeId);
        return $attributes ? explode(',', $attributes) : [];
    }

    /**
     * Flag to only use fallback to parent 'configurable' attributes on non visible parents
     *
     * @param int $storeId
     *
     * @return bool
     */
    private function configurableNonVisibleFallback(int $storeId): bool
    {
        return $this->isSetFlag(self::XML_PATH_CONFIGURABLE_NONVISIBLE, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getBundleProductsBehaviour(int $storeId): array
    {
        return [
            'use' => $this->bundleProductLogic($storeId),
            'use_parent_url' => $this->bundleProductUrl($storeId),
            'use_parent_images' => $this->bundleProductImage($storeId),
            'use_parent_attributes' => $this->bundleParentAttributes($storeId),
            'use_non_visible_fallback' => $this->bundleNonVisibleFallback($storeId)
        ];
    }

    /**
     * Logic for 'bundle' products
     *
     * @param int $storeId
     *
     * @return string
     * @see \TradeTracker\Connect\Model\Config\System\Source\Bundle\Options
     */
    private function bundleProductLogic(int $storeId): string
    {
        return $this->getStoreValue(self::XML_PATH_BUNDLE, $storeId);
    }

    /**
     * Logic for 'bundle' product links
     *
     * @param int $storeId
     *
     * @return string
     */
    private function bundleProductUrl(int $storeId): string
    {
        return $this->getStoreValue(self::XML_PATH_BUNDLE_LINK, $storeId);
    }

    /**
     * Logic for 'bundle' product image
     *
     * @param int $storeId
     *
     * @return int
     * @see \TradeTracker\Connect\Model\Config\System\Source\Bundle\Image
     */
    private function bundleProductImage(int $storeId): int
    {
        return (int)$this->getStoreValue(self::XML_PATH_BUNDLE_IMAGE, $storeId);
    }

    /**
     * Attributes that should be forced to get data from parent 'bundle' product
     *
     * @param int $storeId
     *
     * @return array
     */
    private function bundleParentAttributes(int $storeId): array
    {
        $attributes = $this->getStoreValue(self::XML_PATH_BUNDLE_PARENT_ATTS, $storeId);
        return $attributes ? explode(',', $attributes) : [];
    }

    /**
     * Flag to only use fallback to parent 'bundle' attributes on non visible parents
     *
     * @param int $storeId
     *
     * @return bool
     */
    private function bundleNonVisibleFallback(int $storeId): bool
    {
        return $this->isSetFlag(self::XML_PATH_BUNDLE_NONVISIBLE, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getGroupedProductsBehaviour(int $storeId): array
    {
        return [
            'use' => $this->groupedProductLogic($storeId),
            'use_parent_url' => $this->groupedProductUrl($storeId),
            'use_parent_images' => $this->groupedProductImage($storeId),
            'use_parent_attributes' => $this->groupedParentAttributes($storeId),
            'use_non_visible_fallback' => $this->groupedNonVisibleFallback($storeId),
            'price_logic' => $this->groupedPriceLogic($storeId),
        ];
    }

    /**
     * Logic for 'grouped' products
     *
     * @param int $storeId
     *
     * @return string
     * @see \TradeTracker\Connect\Model\Config\System\Source\Grouped\Options
     */
    public function groupedProductLogic(int $storeId): string
    {
        return $this->getStoreValue(self::XML_PATH_GROUPED, $storeId);
    }

    /**
     * Logic for 'grouped' product links
     *
     * @param int $storeId
     *
     * @return string
     */
    public function groupedProductUrl(int $storeId): string
    {
        return $this->getStoreValue(self::XML_PATH_GROUPED_LINK, $storeId);
    }

    /**
     * Logic for 'grouped' product image
     *
     * @param int $storeId
     *
     * @return int
     * @see \TradeTracker\Connect\Model\Config\System\Source\Grouped\Image
     */
    public function groupedProductImage(int $storeId): int
    {
        return (int)$this->getStoreValue(self::XML_PATH_GROUPED_IMAGE, $storeId);
    }

    /**
     * Attributes that should be forced to get data from parent 'grouped' product
     *
     * @param int $storeId
     *
     * @return array
     */
    public function groupedParentAttributes(int $storeId): array
    {
        $attributes = $this->getStoreValue(self::XML_PATH_GROUPED_PARENT_ATTS, $storeId);
        return $attributes ? explode(',', $attributes) : [];
    }

    /**
     * Flag to only use fallback to parent 'grouped' attributes on non visible parents
     *
     * @param int $storeId
     *
     * @return bool
     */
    public function groupedNonVisibleFallback(int $storeId): bool
    {
        return $this->isSetFlag(self::XML_PATH_GROUPED_NONVISIBLE, $storeId);
    }

    /**
     * Get grouped price logics
     *
     * @param int $storeId
     *
     * @return string
     */
    private function groupedPriceLogic(int $storeId): string
    {
        return $this->getStoreValue(self::XML_PATH_GROUPED_PARENT_PRICE, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getFeedGenerationResult(int $storeId): string
    {
        return $this->getUncachedStoreValue(self::XPATH_FEED_RESULT, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function setFeedGenerationResult(int $storeId, string $msg): void
    {
        $this->setConfigData($msg, self::XPATH_FEED_RESULT, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getFileName(int $storeId): string
    {
        $filename = $this->getStoreValue(self::XPATH_FEED_FILENAME, $storeId) ?? 'tradetracker.xml';
        return str_replace('.xml', sprintf('-%s.xml', $storeId), $filename);
    }
}
