<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Service\ProductData;

use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\StoreRepositoryInterface;

class Data
{
    private array $entityIds = [];
    private int $storeId = 0;
    private array $productIds = [];
    private array $extraParameters = [];
    private StoreInterface $store;

    public function __construct(
        private readonly StoreRepositoryInterface $storeRepository,
        private readonly AttributeCollector\Data\AttributeMapper $attributeMapper,
        private readonly AttributeCollector\Data\Url $url,
        private readonly AttributeCollector\Data\Category $category,
        private readonly AttributeCollector\Data\Stock $stock,
        private readonly AttributeCollector\Data\Price $price,
        private readonly AttributeCollector\Data\Rating $rating,
        private readonly AttributeCollector\Data\SuperAttribute $superAttribute,
        private readonly AttributeCollector\Data\VariantOption $variantOption,
        private readonly AttributeCollector\Data\RelatedProduct $relatedProduct,
        private readonly AttributeCollector\Data\PopularityRank $popularityRank,
        private readonly AttributeCollector\Data\ItemGroup $itemGroup,
        private readonly AttributeCollector\Data\Image $image
    ) {
    }

    /**
     * Collect product data based on IDs and attributes.
     *
     * @param array $entityIds Product entity IDs to process.
     * @param array $attributeMap Mapping of attributes to collect.
     * @param array $extraParameters Additional parameters for processing.
     * @param int $storeId Store ID context.
     * @return array Product data keyed by the product_id
     */
    public function execute(
        array $entityIds,
        array $attributeMap,
        array $extraParameters,
        int $storeId = 0,
        array $rowIds = []
    ): array {
        if (empty($entityIds)) {
            return [];
        }

        $this->entityIds = $entityIds;
        $this->storeId = $storeId;
        $store = $this->getStore();
        if ($store === null) {
            return [];
        }

        $this->store = $store;

        $this->extraParameters = $extraParameters;
        $this->productIds = array_flip($rowIds);

        $data = [];
        $this->mergeAttributeData($data, $attributeMap);
        $this->mergeUrlData($data);
        $this->mergeCategoryData($data);
        $this->mergeStockData($data);
        $this->mergePriceData($data);
        $this->mergeSuperAttributeData($data);
        $this->mergeVariantOptionData($data);
        $this->mergeRelatedProductData($data);
        $this->mergePopularityRankData($data);
        $this->mergeRatingData($data);
        $this->mergeItemGroupIds($data);
        $this->mergeImageData($data);

        ksort($data);
        return $data;
    }

    /**
     * Merge attribute data into the main result set.
     *
     * @param array $data Existing data array.
     * @param array $attributeMap Mapping of attributes to collect.
     * @return void
     */
    private function mergeAttributeData(array &$data, array $attributeMap): void
    {
        $result = $this->attributeMapper->execute($this->entityIds, $this->store, $attributeMap, 'catalog_product');
        foreach ($attributeMap as $targetCode => $attributeCode) {
            if (!isset($result[$attributeCode])) {
                continue;
            }
            foreach ($result[$attributeCode] as $entityId => $value) {
                $productId = $this->productIds[$entityId] ?? null;
                if ($productId) {
                    $data[$productId][$targetCode] = $value;
                }
            }
        }
    }

    /**
     * Merge URL data into the main result set.
     *
     * @param array $data Existing data array.
     * @return void
     */
    private function mergeUrlData(array &$data): void
    {
        $result = $this->url->execute($this->entityIds, 'product', $this->store, $this->productIds);

        foreach ($result as $productId => $url) {
            if (!isset($data[$productId])) {
                $data[$productId] = [];
            }
            $data[$productId]['url'] = $url;
        }
    }

    /**
     * Merge category data into the main result set.
     *
     * @param array $data Existing data array.
     * @return void
     */
    private function mergeCategoryData(array &$data): void
    {
        $result = $this->category->execute($this->productIds, $this->store, 'raw', $this->extraParameters);

        foreach ($result as $productId => $categoryData) {
            if (!isset($data[$productId])) {
                continue;
            }
            $data[$productId]['category'] = $categoryData;
        }
    }

    /**
     * Merge stock data into the main result set.
     *
     * @param array $data Existing data array.
     * @return void
     */
    private function mergeStockData(array &$data): void
    {
        if (!$this->extraParameters['stock']['inventory']) {
            return;
        }

        $result = $this->stock->execute($this->productIds, $this->store);
        $inventoryFields = array_merge(
            $this->extraParameters['stock']['inventory_fields'],
            ['qty', 'msi', 'salable_qty', 'reserved', 'is_in_stock']
        );
        $inventoryFieldKeys = array_flip($inventoryFields);

        foreach ($result as $productId => $stockData) {
            if (!isset($data[$productId])) {
                continue;
            }
            $data[$productId] += array_intersect_key($stockData, $inventoryFieldKeys);
        }
    }

    /**
     * Merge price data into the main result set.
     *
     * @param array $data Existing data array.
     * @return void
     */
    private function mergePriceData(array &$data): void
    {
        $result = $this->price->execute(
            $this->productIds,
            $this->store,
            $this->extraParameters['behaviour']['grouped']['price_logic'] ?? 'max',
            $this->extraParameters['behaviour']['bundle']['price_logic'] ?? 'min',
            $this->extraParameters['behaviour']['configurable']['price_logic'] ?? '',
        );

        foreach ($result as $productId => $priceData) {
            if (!isset($data[$productId])) {
                continue;
            }
            $data[$productId] += $priceData;
        }
    }

    /**
     * Merge super attribute labels into the main result set.
     * This method collects labels for configurable attributes (e.g., color, size)
     * for parent products and merges them into the data array based on the
     * mapping of entity IDs to row IDs.
     *
     * @param array $data Existing data array keyed by product_id.
     * @return void
     */
    private function mergeSuperAttributeData(array &$data): void
    {
        $result = $this->superAttribute->execute($this->entityIds, $this->storeId);

        foreach ($result as $productId => $superAttributes) {
            if (!isset($data[$productId])) {
                continue;
            }
            $data[$productId] += $superAttributes;
        }
    }

    /**
     * Merge Google Shopping "variant_option" pairs into the main result set.
     * For each child product of a configurable, this attaches the name/value pairs
     * (e.g. Color/Red, Size/M) derived from the parent's super attributes and the
     * child's own attribute values, keyed by product_id.
     *
     * @param array $data Existing data array keyed by product_id.
     * @return void
     */
    private function mergeVariantOptionData(array &$data): void
    {
        if (empty($this->extraParameters['advanced']['variant_option'])) {
            return;
        }

        $result = $this->variantOption->execute($this->entityIds, $this->storeId);
        foreach ($result as $productId => $variantOptions) {
            if (!isset($data[$productId])) {
                continue;
            }
            $data[$productId]['variant_option'] = $variantOptions;
        }
    }

    /**
     * Merge Magento's related products in as Google's related_product attribute.
     *
     * @param array $data Existing data array keyed by product_id.
     * @return void
     */
    private function mergeRelatedProductData(array &$data): void
    {
        if (empty($this->extraParameters['advanced']['related_products'])) {
            return;
        }

        $result = $this->relatedProduct->execute(
            $this->entityIds,
            (string)($this->extraParameters['advanced']['related_products_type'] ?? 'accessory')
        );

        foreach ($result as $entityId => $relatedProducts) {
            $productId = $this->productIds[$entityId] ?? $entityId;
            if (!isset($data[$productId])) {
                continue;
            }
            $data[$productId]['related_product'] = $relatedProducts;
        }
    }

    /**
     * Merge the popularity rank in, for the products that sold anything in the period.
     *
     * @param array $data Existing data array keyed by product_id.
     * @return void
     */
    private function mergePopularityRankData(array &$data): void
    {
        if (empty($this->extraParameters['advanced']['popularity_rank'])) {
            return;
        }

        $result = $this->popularityRank->execute(
            $this->entityIds,
            $this->storeId,
            (int)($this->extraParameters['advanced']['popularity_rank_days'] ?? 90)
        );

        foreach ($result as $entityId => $rank) {
            $productId = $this->productIds[$entityId] ?? $entityId;
            if (!isset($data[$productId])) {
                continue;
            }
            $data[$productId]['popularity_rank'] = $rank;
        }
    }

    /**
     * Merge product rating summary into the main result set.
     * If enabled via extraParameters, this method retrieves and merges the rating summary
     * per product into the data array, using product_id as the key.
     *
     * @param array $data Existing data array keyed by product_id.
     * @return void
     */
    private function mergeRatingData(array &$data): void
    {
        if (!empty($this->extraParameters['advanced']['product_rating'])) {
            $ratings = $this->rating->execute($this->entityIds, $this->storeId);
            foreach ($ratings as $productId => $rating) {
                if (!isset($data[$productId])) {
                    continue;
                }
                $data[$productId]['rating'] = $rating['rating'];
                $data[$productId]['rating_5'] = number_format($rating['rating'] / 20, 1);
                $data[$productId]['review_count'] = $rating['review_count'];
            }
        }
    }

    /**
     * Merge item group IDs into the main result set.
     * This ensures that products which belong to a configurable group share the same item_group_id.
     *
     * @param array $data Existing data array keyed by product_id.
     * @return void
     */
    private function mergeItemGroupIds(array &$data): void
    {
        if (!empty($this->extraParameters['advanced']['item_group_id'])) {
            $itemGroup = $this->itemGroup->execute($this->productIds, true, $this->storeId);
            foreach ($itemGroup as $productId => $groupData) {
                if (!isset($data[$productId])) {
                    continue;
                }
                $data[$productId]['item_group_id'] = $groupData['item_group_id'];
                if (!empty($groupData['item_group_title'])) {
                    $data[$productId]['item_group_title'] = $groupData['item_group_title'];
                }
            }
        }
    }

    /**
     * Merge product image data into the main result set.
     * This method retrieves image data per product and merges it into the data array,
     * keyed by product_id, under the 'image_data' key.
     *
     * @param array $data Existing data array keyed by product_id.
     * @return void
     */
    private function mergeImageData(array &$data): void
    {
        $includeHidden = $this->extraParameters['images']['include_hidden'] ?? true;
        $imageDataArray = $this->image->execute($this->entityIds, $this->store, $includeHidden, $this->productIds);
        foreach ($imageDataArray as $productId => $imageData) {
            if (!isset($data[$productId])) {
                continue;
            }
            $data[$productId]['image_data'] = $imageData;
        }
    }

    /**
     * @return StoreInterface|null
     */
    private function getStore(): ?StoreInterface
    {
        try {
            return $this->storeRepository->getById($this->storeId);
        } catch (\Exception $exception) {
            return null;
        }
    }
}
