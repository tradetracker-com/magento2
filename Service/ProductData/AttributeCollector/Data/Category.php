<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Service\ProductData\AttributeCollector\Data;

use Exception;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Store\Api\Data\StoreInterface;

class Category
{

    private array $entityIds = [];
    private int $storeId;
    private string $format;
    private array $categoryNames = [];
    private array $excludedCategories = [];
    private array $excludeAttribute = [];
    private array $nonActiveCategories = [];
    private string $linkField;
    private string $customAttribute = '';
    private array $categoryIds = [];
    private bool $includeAnchor = false;
    private StoreInterface $store;

    public function __construct(
        private readonly ResourceConnection $resource,
        MetadataPool $metadataPool
    ) {
        $this->linkField = $metadataPool->getMetadata(CategoryInterface::class)->getLinkField();
    }

    /**
     * Executes the collection of category paths with names and custom attribute values.
     *
     * @param array $entityIds Array of product IDs.
     * @param StoreInterface $store
     * @param string $format Format of the return data ('raw' or other).
     * @param array $extraParameters Additional parameters (keys: custom, exclude_attribute, include_anchor, add_url).
     * @return array
     */
    public function execute(
        array $entityIds,
        StoreInterface $store,
        string $format = 'raw',
        array $extraParameters = []
    ): array {
        if (empty($extraParameters['category'])) {
            return [];
        }

        $this->entityIds = $entityIds;
        $this->storeId = (int)$store->getId();
        $this->store = $store;
        $this->format = $format;
        $this->customAttribute = $extraParameters['category']['custom'] ?? '';
        $this->includeAnchor = $extraParameters['category']['include_anchor'] ?? false;
        $this->excludeAttribute = $extraParameters['category']['exclude_attribute'] ?? [];

        $this->collectCategoryNames();
        $this->collectNotActive();

        if (!empty($this->excludeAttribute)) {
            $this->collectExcluded();
        }

        $data = $this->collectCategories();
        $data = $this->mergeNames($data);

        if (!empty($extraParameters['category']['add_url'])) {
            return $this->mergeUrl($data);
        }

        // Sort each product's categories by level (descending).
        ksort($data);
        foreach ($data as &$productCats) {
            array_multisort(array_column($productCats, 'level'), SORT_DESC, $productCats);
        }

        return $data;
    }

    /**
     * Collects category names and custom attribute values from the database.
     *
     * @return void
     */
    private function collectCategoryNames(): void
    {
        $connection = $this->resource->getConnection();
        $attributes = $this->customAttribute ? ['name', $this->customAttribute] : ['name'];
        $varCharTable = $this->resource->getTableName('catalog_category_entity_varchar');
        $categoryEntityTable = $this->resource->getTableName('catalog_category_entity');

        // Determine which field to use for entity: row_id (EE) of entity_id (CE)
        $entityField = ($this->linkField === 'row_id') ? 'row_id' : 'entity_id';

        $select = $connection->select()
            ->from(
                ['eav_attribute' => $this->resource->getTableName('eav_attribute')],
                ['attribute_id', 'attribute_code']
            )
            ->joinLeft(
                ['ccev' => $varCharTable],
                'ccev.attribute_id = eav_attribute.attribute_id',
                ['value', 'store_id']
            )
            ->joinLeft(
                ['cce' => $categoryEntityTable],
                "cce.$entityField = ccev.$entityField",
                ['entity_id']
            )
            ->where('eav_attribute.attribute_code IN (?)', $attributes)
            ->where('ccev.store_id IN (?)', [0, $this->storeId]);

        foreach ($connection->fetchAll($select) as $item) {
            if (!$item['value'] || !isset($item['entity_id'])) {
                continue;
            }
            if (!isset($this->categoryNames[$item['entity_id']][$item['store_id']])) {
                $this->categoryNames[$item['entity_id']][$item['store_id']] = [];
            }
            $this->categoryNames[$item['entity_id']][$item['store_id']][$item['attribute_code']] = $item['value'];
        }
    }

    /**
     * Collects excluded category IDs based on the is_active
     *
     * @return void
     */
    private function collectNotActive(): void
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(
                ['eav_attribute' => $this->resource->getTableName('eav_attribute')],
                ['attribute_code']
            )
            ->joinLeft(
                ['catalog_category_entity_int' => $this->resource->getTableName('catalog_category_entity_int')],
                'catalog_category_entity_int.attribute_id = eav_attribute.attribute_id',
                ['entity_id' => $this->linkField, 'value', 'store_id']
            )
            ->where('eav_attribute.attribute_code = ?', 'is_active')
            ->where('catalog_category_entity_int.value = ?', 0)
            ->where('catalog_category_entity_int.store_id IN (?)', [0, $this->storeId]);

        foreach ($connection->fetchAll($select) as $item) {
            $this->nonActiveCategories[$item['store_id']][$item['entity_id']] = 1;
        }
    }

    /**
     * Collects excluded category IDs based on the provided attribute configuration.
     *
     * @return void
     */
    private function collectExcluded(): void
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(
                ['eav_attribute' => $this->resource->getTableName('eav_attribute')],
                ['attribute_code']
            )
            ->joinLeft(
                ['catalog_category_entity_int' => $this->resource->getTableName('catalog_category_entity_int')],
                'catalog_category_entity_int.attribute_id = eav_attribute.attribute_id',
                ['entity_id' => $this->linkField, 'value', 'store_id']
            )
            ->where('eav_attribute.attribute_code = ?', $this->excludeAttribute['code'])
            ->where('catalog_category_entity_int.store_id IN (?)', [0, $this->storeId]);

        foreach ($connection->fetchAll($select) as $item) {
            if ($item['value'] == $this->excludeAttribute['value']) {
                $this->excludedCategories[(int)$item['store_id']][(int)$item['entity_id']] = 1;
            }
        }
    }

    /**
     * Collects category paths assigned to products.
     *
     * @return array
     */
    private function collectCategories(): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(
                ['catalog_category_product' => $this->resource->getTableName('catalog_category_product')],
                'product_id'
            )
            ->joinLeft(
                ['catalog_category_entity' => $this->resource->getTableName('catalog_category_entity')],
                "catalog_category_entity.entity_id = catalog_category_product.category_id",
                ['path', 'parent_id']
            )
            ->where('product_id IN (?)', $this->entityIds);

        $excluded = array_unique(
            array_merge(
                array_keys($this->excludedCategories[0] ?? []),
                array_keys($this->excludedCategories[$this->storeId] ?? [])
            )
        );
        if (!empty($excluded)) {
            $select->where("catalog_category_entity.{$this->linkField} NOT IN (?)", $excluded);
        }

        $result = $connection->fetchAll($select);
        $paths = [];
        $parentIds = [];

        foreach ($result as $item) {
            if (!empty($item['path'])) {
                $paths[$item['product_id']][] = $item['path'];
            }
            $parentIds[] = $item['parent_id'];
        }

        if ($this->includeAnchor) {
            $this->addAnchorCategories($paths, array_unique($parentIds));
        }

        return $paths;
    }

    /**
     * Appends anchor category paths (parent categories marked as anchors) to product paths.
     *
     * @param array &$paths Reference to array of category paths.
     * @param array $parentIds Array of parent category IDs.
     * @return void
     */
    private function addAnchorCategories(array &$paths, array $parentIds): void
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(
                ['catalog_category_entity' => $this->resource->getTableName('catalog_category_entity')],
                ['entity_id' => $this->linkField, 'path']
            )
            ->joinLeft(
                ['catalog_category_entity_int' => $this->resource->getTableName('catalog_category_entity_int')],
                "catalog_category_entity_int.{$this->linkField} = catalog_category_entity.{$this->linkField}",
                []
            )
            ->joinLeft(
                ['eav_attribute' => $this->resource->getTableName('eav_attribute')],
                "catalog_category_entity_int.{$this->linkField} = catalog_category_entity.{$this->linkField}",
                []
            )
            ->where('eav_attribute.attribute_code = ?', 'is_anchor')
            ->where('catalog_category_entity_int.value = ?', 1)
            ->where("catalog_category_entity.{$this->linkField} IN (?)", $parentIds);

        if (!empty($this->excludedCategories)) {
            $select->where("catalog_category_entity.{$this->linkField} NOT IN (?)", $this->excludedCategories);
        }

        $anchorPaths = $connection->fetchPairs($select);

        foreach ($paths as $productId => $pathArray) {
            foreach ($pathArray as $singlePath) {
                if ($singlePath === null) {
                    continue;
                }

                $categoryIds = explode('/', $singlePath);
                array_pop($categoryIds);
                $diff = array_intersect(array_keys(array_flip($categoryIds)), array_keys($anchorPaths));
                foreach ($diff as $catId) {
                    if (!in_array($anchorPaths[$catId], $paths[$productId], true)) {
                        $paths[$productId][] = $anchorPaths[$catId];
                    }
                }
            }
        }
    }

    /**
     * Merges category paths with their names and custom attribute values.
     * For each product and each category path (after removing the root category),
     * this function builds a "name" path and uses fallback logic to get a custom attribute value.
     *
     * @param array $data Array of category paths.
     * @return array
     */
    private function mergeNames(array $data): array
    {
        $result = [];
        $rootCategoryId = $this->getRootCategoryId();
        if ($rootCategoryId === null) {
            return $result;
        }

        foreach ($data as $entityId => $categoryPaths) {
            foreach ($categoryPaths as $categoryPath) {
                $pathData = $this->buildCategoryPathData($categoryPath, $rootCategoryId);
                if ($pathData === null) {
                    continue;
                }
                $fullPath = $pathData['fullPath'];
                $namesForPath = $pathData['namesForPath'];
                $realId = $pathData['realId'];
                $level = $pathData['level'];

                if ($this->format === 'raw') {
                    $pathString = implode(' > ', $namesForPath);
                    $customForPath = $this->getCustomForTruncatedPath($fullPath, count($namesForPath));
                    $result[$entityId][] = [
                        'level' => $level,
                        'name' => $this->getCategoryAttribute($realId, 'name'),
                        'custom' => end($customForPath),
                        'path' => $pathString,
                        'custom_path' => $this->getCustomPath($fullPath, $namesForPath),
                        'category_id' => $realId
                    ];
                } else {
                    $result[$entityId][] = [
                        'path' => implode(' > ', $namesForPath),
                        'category_id' => $realId
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Retrieves the root category ID for the current store.
     *
     * @return int|null
     */
    private function getRootCategoryId(): ?int
    {
        try {
            return (int)$this->store->getRootCategoryId();
        } catch (Exception $exception) {
            return null;
        }
    }

    /**
     * Helper to extract category path data, names and checks.
     *
     * @param string|null $categoryPath
     * @param int $rootCategoryId
     * @return array|null
     */
    private function buildCategoryPathData(?string $categoryPath, int $rootCategoryId): ?array
    {
        if (!$categoryPath) {
            return null;
        }
        $categoryIds = explode('/', $categoryPath);
        $rootKey = array_search($rootCategoryId, $categoryIds);
        if ($rootKey === false) {
            return null;
        }
        // Work only on categories below the root.
        $categoryIds = array_slice($categoryIds, $rootKey + 1);
        $level = count($categoryIds);
        if ($level === 0) {
            return null;
        }
        $fullPath = $categoryIds;
        $namesForPath = [];
        $realId = 0;
        foreach ($categoryIds as $categoryId) {
            if (!isset($this->categoryNames[$categoryId])) {
                continue;
            }
            $namesForPath[] = $this->getCategoryAttribute((int)$categoryId, 'name');
            $realId = (int)$categoryId;
            if (!in_array($categoryId, $this->categoryIds, true)) {
                $this->categoryIds[] = $categoryId;
            }
        }
        if (empty($namesForPath)) {
            return null;
        }
        if (!$this->isActiveOnStore($realId) || $this->isExcludedOnStore($realId)) {
            return null;
        }
        return [
            'fullPath' => $fullPath,
            'namesForPath' => $namesForPath,
            'realId' => $realId,
            'level' => $level
        ];
    }

    /**
     * Retrieves a category's attribute value for the current store, with a fallback to store 0.
     *
     * @param int $categoryId
     * @param string $attribute
     * @return string
     */
    private function getCategoryAttribute(int $categoryId, string $attribute): string
    {
        return $this->categoryNames[$categoryId][$this->storeId][$attribute]
            ?? $this->categoryNames[$categoryId][0][$attribute]
            ?? '';
    }

    /**
     * @param $entityId
     * @return bool
     */
    private function isActiveOnStore($entityId): bool
    {
        if (isset($this->nonActiveCategories[$this->storeId][$entityId])) {
            return false;
        }
        if (isset($this->nonActiveCategories[0][$entityId])) {
            return false;
        }

        return true;
    }

    /**
     * @param $entityId
     * @return bool
     */
    private function isExcludedOnStore($entityId): bool
    {
        if (isset($this->excludedCategories[$this->storeId][$entityId])) {
            return true;
        }
        if (isset($this->excludedCategories[0][$entityId])) {
            return true;
        }

        return false;
    }

    /**
     * The path with every category's custom value in place of its name, where it has one.
     *
     * 'custom' is the deepest custom value and stands on its own (a channel taxonomy such as a
     * Google category). A channel that wants the merchant's own tree with renamed segments reads
     * this one instead: each segment is the category's custom value, or its name when the category
     * has none.
     *
     * @param array $fullPath Array of category IDs (after the root).
     * @param array $namesForPath Names of the truncated path, in order.
     * @return string
     */
    private function getCustomPath(array $fullPath, array $namesForPath): string
    {
        if ($this->customAttribute === '') {
            return implode(' > ', $namesForPath);
        }

        $segments = [];
        foreach (array_values($namesForPath) as $i => $name) {
            $custom = isset($fullPath[$i])
                ? $this->getCategoryAttribute((int)$fullPath[$i], $this->customAttribute)
                : '';
            $segments[] = $custom !== '' ? $custom : $name;
        }

        return implode(' > ', $segments);
    }

    /**
     * Returns an array of custom attribute values for the truncated path.
     * If a category does not have a custom value, a fallback from deeper categories is used.
     *
     * @param array $fullPath Array of category IDs (after the root).
     * @param int $truncatedCount Count of elements in the truncated path.
     * @return array
     */
    private function getCustomForTruncatedPath(array $fullPath, int $truncatedCount): array
    {
        $customValues = [];
        $fullPathCount = count($fullPath);

        for ($i = 0; $i < $truncatedCount; $i++) {
            $catId = $fullPath[$i];
            $custom = $this->getCategoryAttribute((int)$catId, $this->customAttribute);

            if ($custom === '' && $this->customAttribute) {
                for ($j = $i + 1; $j < $fullPathCount; $j++) {
                    $fallback = $this->getCategoryAttribute((int)$fullPath[$j], $this->customAttribute);
                    if ($fallback !== '') {
                        $custom = $fallback;
                        break;
                    }
                }
            }
            $customValues[] = $custom;
        }

        return array_filter($customValues, static fn($val) => $val !== '');
    }

    /**
     * Merges URL information into the category data.
     *
     * @param array $data Category data array.
     * @return array
     */
    private function mergeUrl(array $data): array
    {
        $baseUrl = $this->getBaseUrl();
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(
                ['catalog_category_entity' => $this->resource->getTableName('catalog_category_entity')],
                [$this->linkField]
            )
            ->join(
                ['url_rewrite' => $this->resource->getTableName('url_rewrite')],
                'catalog_category_entity.entity_id = url_rewrite.entity_id',
                ['request_path']
            )
            ->where("catalog_category_entity.{$this->linkField} IN (?)", $this->categoryIds)
            ->where('entity_type = ?', 'category');

        $urls = $connection->fetchAll($select);
        foreach ($data as &$datum) {
            foreach ($datum as &$item) {
                $key = array_search($item['category_id'], array_column($urls, 'entity_id'));
                if ($key !== false && isset($urls[$key]['request_path'])) {
                    $item['url'] = $baseUrl . $urls[$key]['request_path'];
                }
            }
        }
        return $data;
    }

    /**
     * Retrieve the base URL for the current store.
     *
     * @return string
     */
    private function getBaseUrl(): string
    {
        try {
            return (string)$this->store->getBaseUrl();
        } catch (Exception $exception) {
            return '';
        }
    }
}
