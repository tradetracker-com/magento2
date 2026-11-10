<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Model\ProductData;

use TradeTracker\Connect\Api\Config\System\FeedInterface as FeedConfigRepository;
use TradeTracker\Connect\Api\ProductData\RepositoryInterface as ProductData;
use TradeTracker\Connect\Service\ProductData\Collector;
use TradeTracker\Connect\Service\ProductData\Filter;
use TradeTracker\Connect\Service\ProductData\ImageProcessor;

/**
 * Builds the rows of the TradeTracker product feed from the shared ProductData drop-in.
 *
 * The drop-in collects and filters; what is TradeTracker's own stays here: the feed field names,
 * the conditional static fields (shipping price per price band, delivery time per stock state),
 * the category name a merchant sets per category, and the formatting the TradeTracker feed wants.
 */
class Repository implements ProductData
{
    private const IMAGE_SOURCE_ALL = 'all';
    private const EXCLUDE_ATTRIBUTE = 'tradetracker_exclude';
    private const CATEGORY_EXCLUDE_ATTRIBUTE = 'tradetracker_disable_export';
    private const CATEGORY_NAME_ATTRIBUTE = 'tradetracker_category';

    /**
     * Attributes every row needs, result key => Magento attribute
     */
    private const BASE_ATTRIBUTE_MAP = [
        'product_id' => 'entity_id',
        'sku' => 'sku',
        'visibility' => 'visibility',
        'type_id' => 'type_id',
        self::EXCLUDE_ATTRIBUTE => self::EXCLUDE_ATTRIBUTE,
    ];

    /**
     * Feed structure, feed field => collected key
     */
    private const BASE_RESULT_MAP = [
        'ID' => 'product_id',
        'sku' => 'sku',
        'name' => 'name',
        'description' => 'description',
        'descriptionLong' => 'description_long',
        'productURL' => 'url',
        'imageURL' => 'image',
        'additionalImages' => 'additional_images',
        'fromPrice' => 'price',
        'price' => 'final_price',
        'discount' => 'discount_perc',
        'EAN' => 'ean',
        'brand' => 'brand',
        'color' => 'color',
        'material' => 'material',
        'size' => 'size',
        'deliveryTime' => 'delivery_time',
        'deliveryCosts' => 'shipping_price',
        'qty' => 'salable_qty',
        'availability' => 'is_in_stock',
        'categoryPath' => 'categoryPath',
        'categories' => 'categories',
        'subcategories' => 'subcategories',
        'subsubcategories' => 'subsubcategories',
        'extra_info' => 'extra_info',
        'vat_pct' => 'vat_pct',
    ];

    private array $attributeMap = [];
    private array $resultMap = [];
    private array $staticFields = [];

    public function __construct(
        private readonly FeedConfigRepository $feedConfigRepository,
        private readonly Filter $filter,
        private readonly Collector $collector,
        private readonly ImageProcessor $imageProcessor
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getProductData(int $storeId = 0, array $entityIds = [], $type = 'manual'): array
    {
        $this->collectAttributes($storeId);
        $this->staticFields = $this->feedConfigRepository->getStaticFields($storeId);

        $ids = $this->collectIds($storeId, $entityIds, $type === 'preview');
        [$imageSource, $imageMain] = $this->getImageSelection($storeId);
        $excludeOutOfStock = $this->feedConfigRepository->excludeOutOfStock($storeId);
        $extraParameters = $this->getExtraParameters($storeId);

        $result = [];
        foreach (array_chunk($ids, max(1, $this->feedConfigRepository->getBatchSize())) as $batchIds) {
            $data = $this->collector->execute($batchIds, $this->attributeMap, $extraParameters, $storeId);
            foreach ($data as $entityId => $productData) {
                if (empty($productData['product_id']) || !empty($productData[self::EXCLUDE_ATTRIBUTE])) {
                    continue;
                }

                $this->imageProcessor->addImageData(
                    $storeId,
                    $productData,
                    $imageSource,
                    $imageMain,
                    self::IMAGE_SOURCE_ALL
                );
                $this->addStaticFields($productData);
                $this->addCategoryData($productData);

                $row = [];
                foreach ($this->resultMap as $index => $attribute) {
                    $row[$index] = $this->prepareAttribute($attribute, $productData);
                }

                if ($excludeOutOfStock && $row['availability'] === 'out of stock') {
                    continue;
                }

                $result[$entityId] = $row;
            }
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function getProductAttributes(int $storeId = 0): array
    {
        $this->collectAttributes($storeId);
        return $this->attributeMap;
    }

    /**
     * Entity ids the feed covers: the configured filters, narrowed to the requested ids and the
     * preview size.
     */
    private function collectIds(int $storeId, array $entityIds, bool $preview): array
    {
        $filters = $this->feedConfigRepository->getFilters($storeId);
        // The drop-in reads this key; the TradeTracker feed never carries disabled products.
        $filters['add_disabled_products'] = false;

        $ids = $this->filter->execute($filters, $storeId);
        if ($entityIds) {
            $ids = array_values(array_intersect($entityIds, $ids));
        }
        if ($preview) {
            $ids = array_slice($ids, 0, $this->feedConfigRepository->getPreviewSize());
        }

        return $ids;
    }

    /**
     * Attributes to collect and the feed fields they land in. The drop-in keys collected values by
     * the attribute map key, so an extra field is read back under its own name.
     */
    private function collectAttributes(int $storeId): void
    {
        $this->attributeMap = self::BASE_ATTRIBUTE_MAP + $this->feedConfigRepository->getAttributes($storeId);
        $this->resultMap = self::BASE_RESULT_MAP;

        foreach ($this->feedConfigRepository->getExtraFields($storeId) as $field) {
            if (empty($field['name']) || empty($field['attribute'])) {
                continue;
            }
            $this->attributeMap[$field['name']] = $field['attribute'];
            $this->resultMap[$field['name']] = $field['name'];
        }

        $advancedFilters = $this->feedConfigRepository->getFilters($storeId)['advanced_filters'] ?? [];
        foreach ($advancedFilters as $advancedFilter) {
            if (!empty($advancedFilter['attribute'])) {
                $this->attributeMap[$advancedFilter['attribute']] = $advancedFilter['attribute'];
            }
        }

        $this->attributeMap = array_filter($this->attributeMap);
    }

    /**
     * Image source and main image as the drop-in's image processor expects them: a single image
     * type, or all images with the configured main image ('' for the first, 'last', or a type).
     *
     * @return array{0: string, 1: string}
     */
    private function getImageSelection(int $storeId): array
    {
        $imageConfig = $this->feedConfigRepository->getImageAttributes($storeId);
        if (count($imageConfig) === 1) {
            return [(string)reset($imageConfig), ''];
        }

        return [self::IMAGE_SOURCE_ALL, (string)($imageConfig['main_image'] ?? '')];
    }

    private function getExtraParameters(int $storeId): array
    {
        $filters = $this->feedConfigRepository->getFilters($storeId);

        return [
            'filters' => [
                'exclude_attribute' => null,
                'exclude_disabled' => true,
                'custom' => $filters['advanced_filters'] ?? [],
            ],
            'stock' => [
                'inventory' => true,
                'inventory_fields' => ['qty', 'is_in_stock', 'salable_qty'],
            ],
            'category' => [
                'exclude_attribute' => ['code' => self::CATEGORY_EXCLUDE_ATTRIBUTE, 'value' => 1],
                'custom' => self::CATEGORY_NAME_ATTRIBUTE,
                'include_anchor' => true,
            ],
            'behaviour' => [
                'configurable' => $this->feedConfigRepository->getConfigProductsBehaviour($storeId),
                'bundle' => $this->feedConfigRepository->getBundleProductsBehaviour($storeId),
                'grouped' => $this->feedConfigRepository->getGroupedProductsBehaviour($storeId),
            ],
            'images' => [
                'include_hidden' => false,
            ],
        ];
    }

    /**
     * Static fields are either a plain value or a list of conditions ("attribute op value" => value):
     * the shipping price per price band, the delivery time per stock state.
     */
    private function addStaticFields(array &$productData): void
    {
        foreach ($this->staticFields as $field => $value) {
            if (!is_array($value)) {
                $productData[$field] = $value;
                continue;
            }

            foreach ($value as $condition => $conditionalValue) {
                $resolved = $this->resolveCondition($condition, $conditionalValue, $productData);
                if ($resolved !== null) {
                    $productData[$field] = $resolved;
                }
            }
        }
    }

    /**
     * @return mixed|null the conditional value when the condition holds, null otherwise
     */
    private function resolveCondition(string $condition, $conditionalValue, array $productData)
    {
        $parts = explode(' ', $condition, 3);
        if (count($parts) !== 3) {
            return null;
        }
        [$attribute, $operator, $expected] = $parts;
        if (!isset($productData[$attribute])) {
            return null;
        }
        $actual = $productData[$attribute];

        switch ($operator) {
            case '==':
                return $actual == $expected ? $conditionalValue : null;
            case '!=':
                return $actual != $expected ? $conditionalValue : null;
            case '>=':
                return $actual >= $expected ? $conditionalValue : null;
            case '>':
                return $actual > $expected ? $conditionalValue : null;
            case '<=':
                return $actual <= $expected ? $conditionalValue : null;
            case '<':
                return $actual < $expected ? $conditionalValue : null;
            case 'between':
                [$from, $to] = array_pad(explode('/', $expected, 2), 2, null);
                return $actual >= $from && $actual <= $to ? $conditionalValue : null;
        }

        return null;
    }

    /**
     * The category path: the custom source when configured, otherwise the deepest category the
     * product sits in, with the per-category "Category Name for Feed" replacing names where set.
     */
    private function addCategoryData(array &$productData): void
    {
        if (isset($productData['category_custom'])) {
            $path = $productData['category_custom'];
        } else {
            $category = $productData['category'][0] ?? [];
            $path = $category['custom_path'] ?? $category['path'] ?? null;
        }

        if (empty($path)) {
            return;
        }

        $segments = explode(' > ', $path);
        $productData += [
            'categoryPath' => $path,
            'categories' => $segments[0] ?? null,
            'subcategories' => $segments[1] ?? null,
            'subsubcategories' => $segments[2] ?? null,
        ];
    }

    /**
     * Formatting the TradeTracker feed wants for a collected value.
     *
     * @return mixed
     */
    private function prepareAttribute(string $attribute, array $productData)
    {
        $value = $productData[$attribute] ?? null;
        $currency = $productData['currency'] ?? null;

        switch ($attribute) {
            case 'status':
                return $value ? 'Enabled' : 'Disabled';
            case 'is_in_stock':
                return $value ? 'in stock' : 'out of stock';
            case 'manage_stock':
                return $value ? 'true' : 'false';
            case 'url':
                if (!isset($productData['url'])) {
                    return '';
                }
                if (!empty($productData['utm_string'])) {
                    $prefix = strpos($productData['url'], '?') !== false ? '&' : '?';
                    return $productData['url'] . $prefix . $productData['utm_string'];
                }
                return $productData['url'];
            case 'price':
            case 'price_ex':
            case 'final_price':
            case 'final_price_ex':
            case 'min_price':
            case 'max_price':
            case 'sales_price':
                return $value === null ? null : number_format((float)$value, 2, '.', '') . ' ' . $currency;
            case 'visibility':
                switch ((int)$value) {
                    case 1:
                        return 'Not Visible Individually';
                    case 2:
                        return 'Catalog';
                    case 3:
                        return 'Search';
                    case 4:
                        return 'Catalog, Search';
                }
                return $value;
        }

        return $value;
    }
}
