<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Service\ProductData;

use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\EntityManager\MetadataPool;

class Collector
{
    private const ATTR_URL = 'url';
    private const ATTR_IMAGE = 'image';
    private const PARENT_TYPES = ['configurable', 'bundle', 'grouped'];
    private string $linkField;

    private Data $data;
    private AttributeCollector\Data\ConfigurableKey $configurableKey;
    private AttributeCollector\Data\Parents $parents;
    private ResourceConnection $resourceConnection;

    public function __construct(
        Data $data,
        AttributeCollector\Data\ConfigurableKey $configurableKey,
        AttributeCollector\Data\Parents $parents,
        ResourceConnection $resourceConnection,
        MetadataPool $metadataPool
    ) {
        $this->data = $data;
        $this->configurableKey = $configurableKey;
        $this->parents = $parents;
        $this->resourceConnection = $resourceConnection;
        $this->linkField = $metadataPool->getMetadata(CategoryInterface::class)->getLinkField();
    }

    /**
     * Process product data for given entity IDs, handling parent-child relationships,
     * attribute inheritance, and applying filters.
     *
     * @param int[] $entityIds List of product entity IDs.
     * @param array $attributeMap Mapping of attributes to retrieve.
     * @param array $extraParameters Additional parameters including behavior and filters.
     * @param int $storeId Store ID context for data retrieval.
     * @return array<int, array<string, mixed>> Processed product data keyed by product entity ID.
     */
    public function execute(
        array $entityIds,
        array $attributeMap,
        array $extraParameters,
        int $storeId = 0
    ): array {
        if (empty($entityIds)) {
            return [];
        }

        $rowIds = $this->getRowsIds($entityIds);
        $productIds = array_flip($rowIds);
        $excludeDisabled = $extraParameters['filters']['exclude_disabled'];
        $parents = $this->parents->execute($productIds, $excludeDisabled, $storeId);

        $toUnset = [];
        $parentAttributeToUse = [];
        $extraProductsToLoad = [];

        $parentAttributes = $this->getParentAttributes($extraParameters);
        foreach ($productIds as $productId) {
            if (!isset($parents[$productId])) {
                continue;
            }

            foreach ($parents[$productId] as $parentEntityId => $parentType) {
                if (!isset($extraParameters['behaviour'][$parentType])) {
                    continue;
                }

                $this->handleParentUsage(
                    (int)$productId,
                    $parentEntityId,
                    $parentType,
                    $extraParameters,
                    $toUnset,
                    $parentAttributes,
                    $parentAttributeToUse,
                    $extraProductsToLoad,
                    $entityIds
                );
            }
        }

        $allIds = array_merge($entityIds, $extraProductsToLoad);
        $rowIds = $this->getRowsIds($allIds);

        $data = $this->data->execute(
            $allIds,
            $attributeMap,
            $extraParameters,
            $storeId,
            $rowIds
        );

        $configKeys = $this->configurableKey->execute($productIds, $excludeDisabled, $storeId);
        $this->processData(
            $data,
            $parents,
            $parentAttributeToUse,
            $configKeys,
            $extraParameters,
            $storeId,
            $toUnset,
            $rowIds
        );

        $this->unsetReplacedParentTypes($toUnset, $allIds, $extraParameters);

        return array_diff_key($data, array_flip($toUnset));
    }

    /**
     * For any behaviour set to "No" (empty) or use='simple', remove products of that type from
     * the result. The per-child unset in handleParentUsage misses parents that the Parents lookup
     * drops (e.g. Not Visible Individually), so we also remove by type_id directly.
     */
    private function unsetReplacedParentTypes(array &$toUnset, array $allIds, array $extraParameters): void
    {
        if (empty($allIds)) {
            return;
        }

        $typesToReplace = [];
        foreach ($extraParameters['behaviour'] ?? [] as $type => $behaviour) {
            if (empty($behaviour['use']) || $behaviour['use'] === 'simple') {
                $typesToReplace[] = $type;
            }
        }
        if (empty($typesToReplace)) {
            return;
        }

        $connection = $this->resourceConnection->getConnection();
        $cpeTable = $this->resourceConnection->getTableName('catalog_product_entity');
        $select = $connection->select()
            ->from($cpeTable, [$this->linkField])
            ->where("{$this->linkField} IN (?)", $allIds)
            ->where('type_id IN (?)', $typesToReplace);

        foreach ($connection->fetchCol($select) as $linkId) {
            $toUnset[] = (int)$linkId;
        }
    }

    /**
     * Retrieve mapping of entity IDs to their corresponding row IDs.
     *
     * @param int[] $entityIds List of product entity IDs.
     * @return array<int, int> Array mapping entity IDs to row IDs keyed by entity ID.
     */
    public function getRowsIds(array $entityIds): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('catalog_product_entity');

        $select = $connection->select()
            ->from($table, ['entity_id', $this->linkField])
            ->where("{$this->linkField} IN (?)", $entityIds);

        return $connection->fetchPairs($select);
    }

    /**
     * Retrieve parent attributes to inherit based on behavior settings.
     *
     * @param array $extraParameters Additional parameters defining behavior.
     * @return array<string, string[]> Array keyed by product type ('configurable', 'grouped', 'bundle')
     *                                 with lists of parent attributes to inherit.
     */
    private function getParentAttributes(array $extraParameters): array
    {
        $parentAttributes = [
            'configurable' => $extraParameters['behaviour']['configurable']['use_parent_attributes'],
            'grouped' => $extraParameters['behaviour']['grouped']['use_parent_attributes'],
            'bundle' => $extraParameters['behaviour']['bundle']['use_parent_attributes']
        ];

        foreach (['configurable', 'grouped', 'bundle'] as $type) {
            if ($extraParameters['behaviour'][$type]['use_parent_url']) {
                $parentAttributes[$type][] = self::ATTR_URL;
            }
            if ($extraParameters['behaviour'][$type]['use_parent_images']) {
                $parentAttributes[$type][] = self::ATTR_IMAGE;
            }
        }

        return $parentAttributes;
    }

    /**
     * Handle the usage of parent product data for a specific product entity.
     * This method updates arrays tracking which products to exclude,
     * which parent attributes to use, and additional parent products to load.
     *
     * @param int $entityId Current product entity ID.
     * @param int $parentId Parent product entity ID.
     * @param string $parentType Type of the parent product.
     * @param array $extraParameters Additional behavior parameters.
     * @param int[] &$toUnset Reference to array of product IDs to exclude from final results.
     * @param array<string, string[]> $parentAttributes Attributes inherited from parents keyed by product type.
     * @param array<int, array<string, int>> &$parentAttributeToUse Reference to array mapping product IDs to parent
     *                                                              attributes and their parent IDs.
     * @param int[] &$extraProductsToLoad Reference to array of additional parent product IDs to load.
     * @param int[] $entityIds Original list of product entity IDs.
     */
    private function handleParentUsage(
        int $entityId,
        int $parentId,
        string $parentType,
        array $extraParameters,
        array &$toUnset,
        array $parentAttributes,
        array &$parentAttributeToUse,
        array &$extraProductsToLoad,
        array $entityIds
    ): void {
        $behaviour = $extraParameters['behaviour'][$parentType];
        // "No" (an empty value) leaves the parent out, like "only simples"; its linked simples
        // are then handled as standalone products.
        if (empty($behaviour['use']) || $behaviour['use'] === 'simple') {
            $toUnset[] = $parentId;
        } elseif ($behaviour['use'] === 'parent' && in_array($parentId, $entityIds)) {
            $toUnset[] = $entityId;
        }

        if (!$behaviour['use_parent_attributes'] && !$behaviour['use_parent_url'] && !$behaviour['use_parent_images']) {
            return;
        }

        if (!empty($parentAttributes[$parentType])) {
            foreach ($parentAttributes[$parentType] as $parentAttribute) {
                $parentAttributeToUse[$entityId][$parentAttribute] = $parentId;
            }
        }

        if (!in_array($parentId, $entityIds, true) && !in_array($parentId, $extraProductsToLoad, true)) {
            $extraProductsToLoad[] = $parentId;
        }
    }

    /**
     * Process product data by applying parent attributes, checking filters,
     * and setting additional flags.
     *
     * @param array<int, array> &$data Product data keyed by product entity ID.
     * @param array<int, array<int, string>> $parents Parent relationships keyed by product ID.
     * @param array<int, array<string, int>> $parentAttributeToUse Attributes to inherit from parents.
     * @param array $configKeys Configuration keys for URLs.
     * @param array $extraParameters Additional behavior and filter parameters.
     * @param int $storeId Store ID context.
     * @param int[] &$toUnset Reference to array of product IDs to exclude from final results.
     */
    private function processData(
        array &$data,
        array $parents,
        array $parentAttributeToUse,
        array $configKeys,
        array $extraParameters,
        int $storeId,
        array &$toUnset,
        array $rowIds
    ): void {
        $productIds = array_flip($rowIds);
        foreach ($data as $productId => $productData) {
            if (!$this->checkExtraFilters($extraParameters['filters']['custom'], $productData)) {
                $toUnset[] = $productId;
            }

            if (isset($parents[$productId])) {
                $parentRowId = key($parents[$productId]);
                if ($parentRowId !== null && $parentId = $productIds[$parentRowId] ?? null) {
                    $data[$productId]['parent_id'] = $parentId;
                    $data[$productId]['parent_title'] = $data[$parentId]['title'] ?? null;
                }
            }

            if (isset($parentAttributeToUse[$productId])) {
                foreach ($parentAttributeToUse[$productId] as $parentAttribute => $rowId) {
                    $parentId = $productIds[$rowId];
                    if (!isset($data[$parentId][$parentAttribute])) {
                        continue;
                    }

                    $data[$productId][$parentAttribute] = $data[$parentId][$parentAttribute];

                    if ($parentAttribute == self::ATTR_URL) {
                        $this->appendConfigKeyUrl(
                            $data,
                            $productId,
                            $parentId,
                            $configKeys,
                            $extraParameters,
                            $storeId,
                            $rowId
                        );
                    }
                }
            }

            $this->setImageLogic($data, $productId, $extraParameters);
            $this->setCategoryLogic($data, $productId, $extraParameters);
        }
    }

    /**
     * Validate product data against extra custom filters.
     *
     * @param array<int, array<string, mixed>> $filters List of custom filter conditions.
     * @param array<string, mixed> $productData Product data to validate.
     * @return bool True if the product passes all filters, false otherwise.
     */
    private function checkExtraFilters(array $filters, array $productData): bool
    {
        foreach ($filters as $filter) {
            $attribute = $filter['attribute'] === 'entity_id' ? 'product_id' : $filter['attribute'];
            if (!isset($productData[$attribute]) && $filter['condition'] !== 'not-empty') {
                continue;
            }
            if (!$this->filterAppliesToType($filter['product_type'] ?? '', $productData['type_id'] ?? '')) {
                continue;
            }
            if (!$this->validateCondition(
                $filter['condition'],
                $productData[$attribute] ?? null,
                $filter['value'] ?? null
            )) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check whether a filter row's "Apply To" setting covers the given product type.
     * Empty (or '0' for the built-in exclude filter) applies to all products,
     * 'parent' applies to configurable, bundle and grouped products.
     *
     * @param string|null $filterType Value of the filter's product_type column.
     * @param string $typeId Magento product type_id.
     * @return bool
     */
    private function filterAppliesToType(?string $filterType, string $typeId): bool
    {
        if (empty($filterType)) {
            return true;
        }
        if ($filterType === 'parent') {
            return in_array($typeId, self::PARENT_TYPES, true);
        }

        return $typeId === $filterType;
    }

    /**
     * Validate a single filter condition against an attribute value.
     * Supported conditions: eq, neq, gt, gteq, lt, lteg, in, nin, like, empty, not-empty.
     *
     * @param string $condition Condition type.
     * @param mixed $attributeValue Value from product data.
     * @param mixed $filterValue Value to compare against.
     * @return bool True if the condition is met, false otherwise.
     */
    private function validateCondition(string $condition, $attributeValue, $filterValue): bool
    {
        switch ($condition) {
            case 'eq':
                return $attributeValue == $filterValue;
            case 'neq':
                return $attributeValue != $filterValue;
            case 'gt':
                return (float)$attributeValue > (float)$filterValue;
            case 'gteq':
                return (float)$attributeValue >= (float)$filterValue;
            case 'lt':
                return (float)$attributeValue < (float)$filterValue;
            case 'lteg':
                return (float)$attributeValue <= (float)$filterValue;
            case 'in':
                return in_array($attributeValue, explode(',', (string)$filterValue), true);
            case 'nin':
                return !in_array($attributeValue, explode(',', (string)$filterValue), true);
            case 'like':
                // Convert SQL-style wildcards to regex: % => .*, _ => .
                $clean = preg_quote((string)$filterValue, '/');
                $pattern = '/' . str_replace(['%', '_'], ['.*', '.'], $clean) . '/i';
                return preg_match($pattern, $attributeValue) === 1;
            case 'empty':
                return empty($attributeValue);
            case 'not-empty':
                return !empty($attributeValue);
            default:
                return false;
        }
    }

    /**
     * Append configuration key suffix to product URL if required by behavior settings.
     *
     * @param array<int, array<string, mixed>> &$data Product data keyed by product entity ID.
     * @param int $productId Current product ID.
     * @param int $parentId Parent product entity ID.
     * @param array $configKeys Configuration keys indexed by [entityId][parentId][storeId].
     * @param array $extraParameters Additional behavior parameters.
     * @param int $storeId Store ID context.
     */
    private function appendConfigKeyUrl(
        array &$data,
        int $productId,
        int $parentId,
        array $configKeys,
        array $extraParameters,
        int $storeId,
        int $rowId
    ): void {
        $parentType = $data[$parentId]['type_id'] ?? '';
        if (($extraParameters['behaviour'][$parentType]['use_parent_url'] ?? 0) == 1) {
            $suffix = $this->getConfigKeySuffix($configKeys, $productId, $rowId, $storeId);
            if ($suffix !== null) {
                $data[$productId][self::ATTR_URL] .= $suffix;
            }
        }
    }

    /**
     * Resolve the config key suffix for a child/parent/store combination.
     *
     * @param array $configKeys
     * @param int $productId
     * @param int $parentId
     * @param int $storeId
     * @return string|null
     */
    private function getConfigKeySuffix(array $configKeys, int $productId, int $parentId, int $storeId): ?string
    {
        return $configKeys[$productId][$parentId][$storeId]
            ?? $configKeys[$productId][$parentId][0]
            ?? null;
    }

    /**
     * Set the 'image_logic' flag on product data based on parent relationships and behavior.
     *
     * @param array<int, array<string, mixed>> &$data Product data keyed by product entity ID.
     * @param int $productId Current product ID.
     * @param array $extraParameters Additional behavior parameters.
     */
    private function setImageLogic(array &$data, int $productId, array $extraParameters): void
    {
        if (isset($data[$productId]['parent_id'], $data[$data[$productId]['parent_id']])) {
            $parentId = $data[$productId]['parent_id'];
            $parentProduct = $data[$parentId];
            $typeId = $parentProduct['type_id'] ?? '';

            $data[$productId]['image_logic'] = $extraParameters['behaviour'][$typeId]['use_parent_images'] ?? 0;

            switch ($data[$productId]['image_logic']) {
                case 1: // Always use parent images
                    $data[$productId]['image_data'] = $parentProduct['image_data'] ?? [];
                    break;

                case 2: // Use own images if present, otherwise parent
                    $data[$productId]['image_data'] = $data[$productId]['image_data']
                        ?? ($parentProduct['image_data'] ?? []);
                    break;

                case 3: // Merge: simple images first (child first, then parent)
                    $childImages = $data[$productId]['image_data'] ?? [];
                    $parentImages = $parentProduct['image_data'] ?? [];

                    foreach ($parentImages as $storeId => $images) {
                        if (!isset($childImages[$storeId])) {
                            $childImages[$storeId] = $images;
                        } else {
                            // Simple (child) first, then parent
                            $childImages[$storeId] = [...$childImages[$storeId], ...$images];
                        }
                    }
                    $data[$productId]['image_data'] = $childImages;
                    break;

                case 4: // Merge: parent images first (parent first, then child)
                    $childImages = $data[$productId]['image_data'] ?? [];
                    $parentImages = $parentProduct['image_data'] ?? [];

                    foreach ($parentImages as $storeId => $images) {
                        if (!isset($childImages[$storeId])) {
                            $childImages[$storeId] = $images;
                        } else {
                            // Parent first, then simple (child)
                            $childImages[$storeId] = [...$images, ...$childImages[$storeId]];
                        }
                    }
                    $data[$productId]['image_data'] = $childImages;
                    break;
            }
        } else {
            $data[$productId]['image_logic'] = 0;
        }
    }

    /**
     * Set the parent category data on child product when child has no categories.
     *
     * @param array<int, array<string, mixed>> &$data Product data keyed by product entity ID.
     * @param int $productId Current product ID.
     * @param array $extraParameters Additional behavior parameters.
     */
    private function setCategoryLogic(array &$data, int $productId, array $extraParameters): void
    {
        if (!isset($data[$productId]['parent_id'], $data[$data[$productId]['parent_id']])) {
            return;
        }

        $parentId = $data[$productId]['parent_id'];
        $parentProduct = $data[$parentId];
        $typeId = $parentProduct['type_id'] ?? '';
        $useParentCategories = $extraParameters['behaviour'][$typeId]['use_parent_categories'] ?? 0;

        if ($useParentCategories === 1 && empty($data[$productId]['category'])) {
            $data[$productId]['category'] = $parentProduct['category'] ?? [];
        }
    }
}
