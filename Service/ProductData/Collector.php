<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Service\ProductData;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\EntityManager\MetadataPool;
use TradeTracker\Connect\Service\ProductData\AttributeCollector\Data\ConfigurableKey;
use TradeTracker\Connect\Service\ProductData\AttributeCollector\Data\Parents;

class Collector
{
    private const ATTR_URL = 'url';
    private const ATTR_IMAGE = 'image';
    private string $linkField;

    private Data $data;
    private ConfigurableKey $configurableKey;
    private Parents $parents;
    private ResourceConnection $resourceConnection;

    public function __construct(
        Data $data,
        ConfigurableKey $configurableKey,
        Parents $parents,
        ResourceConnection $resourceConnection,
        MetadataPool $metadataPool
    ) {
        $this->data = $data;
        $this->configurableKey = $configurableKey;
        $this->parents = $parents;
        $this->resourceConnection = $resourceConnection;
        $this->linkField = $metadataPool->getMetadata(ProductInterface::class)->getLinkField();
    }

    /**
     * Process product data for given entity IDs, handling parent-child relationships,
     * attribute inheritance, and applying filters.
     *
     * @param array $entityIds
     * @param array $attributeMap
     * @param array $extraParameters
     * @param int $storeId
     * @return array
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

        $data = $this->data->execute(
            $allIds,
            $attributeMap,
            $extraParameters,
            $storeId
        );

        $configKeys = $this->configurableKey->execute(array_merge($entityIds, $extraProductsToLoad));
        $this->processData($data, $parents, $parentAttributeToUse, $configKeys, $extraParameters, $storeId, $toUnset);

        $this->unsetReplacedParentTypes($toUnset, $allIds, $extraParameters);

        return array_intersect_key(
            array_diff_key($data, array_flip($toUnset)),
            array_flip($entityIds)
        );
    }

    /**
     * For any behaviour with use='simple', remove products of that type from the result.
     * The per-child unset in handleParentUsage misses parents that the Parents lookup drops
     * (e.g. Not Visible Individually), so we also remove by type_id directly.
     */
    private function unsetReplacedParentTypes(array &$toUnset, array $allIds, array $extraParameters): void
    {
        if (empty($allIds)) {
            return;
        }

        $typesToReplace = [];
        foreach ($extraParameters['behaviour'] ?? [] as $type => $behaviour) {
            if (($behaviour['use'] ?? null) === 'simple') {
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
     * @param int[] $entityIds
     * @return array<int, int>
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
     * @param array $extraParameters
     * @return array<string, string[]>
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
     *
     * @param int $entityId
     * @param int $parentId
     * @param string $parentType
     * @param array $extraParameters
     * @param int[] &$toUnset
     * @param array $parentAttributes
     * @param array &$parentAttributeToUse
     * @param int[] &$extraProductsToLoad
     * @param int[] $entityIds
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
        if ($behaviour['use'] === 'simple') {
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
     * and setting image logic flags.
     *
     * @param array &$data
     * @param array $parents
     * @param array $parentAttributeToUse
     * @param array $configKeys
     * @param array $extraParameters
     * @param int $storeId
     * @param int[] &$toUnset
     */
    private function processData(
        array &$data,
        array $parents,
        array $parentAttributeToUse,
        array $configKeys,
        array $extraParameters,
        int $storeId,
        array &$toUnset
    ): void {
        foreach ($data as $entityId => $productData) {
            if (!$this->checkExtraFilters($extraParameters['filters']['custom'], $productData)) {
                $toUnset[] = $entityId;
            }

            if (isset($parents[$entityId])) {
                $keys = array_keys($parents[$entityId]);
                $data[$entityId]['parent_id'] = reset($keys);
            }

            if (isset($parentAttributeToUse[$entityId])) {
                foreach ($parentAttributeToUse[$entityId] as $parentAttribute => $parentId) {
                    if (!isset($data[$parentId][$parentAttribute])) {
                        continue;
                    }

                    $data[$entityId][$parentAttribute] = $data[$parentId][$parentAttribute];

                    if ($parentAttribute == self::ATTR_URL) {
                        $this->appendConfigKeyUrl(
                            $data,
                            $entityId,
                            $parentId,
                            $configKeys,
                            $extraParameters,
                            $storeId
                        );
                    }
                }
            }

            $this->setImageLogic($data, $entityId, $extraParameters);
        }
    }

    /**
     * Validate product data against extra custom filters.
     * Iterates ALL filters (unlike the old Type.php which returned after first).
     *
     * @param array $filters
     * @param array $productData
     * @return bool
     */
    private function checkExtraFilters(array $filters, array $productData): bool
    {
        foreach ($filters as $filter) {
            $attribute = $filter['attribute'] === 'entity_id' ? 'product_id' : $filter['attribute'];
            if (!isset($productData[$attribute]) && $filter['condition'] !== 'not-empty') {
                continue;
            }
            if (!empty($filter['product_type']) && $productData['type_id'] !== $filter['product_type']) {
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
     * Validate a single filter condition against an attribute value.
     *
     * @param string $condition
     * @param mixed $attributeValue
     * @param mixed $filterValue
     * @return bool
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
     * @param array &$data
     * @param int $productId
     * @param int $parentId
     * @param array $configKeys
     * @param array $extraParameters
     * @param int $storeId
     */
    private function appendConfigKeyUrl(
        array &$data,
        $productId,
        $parentId,
        array $configKeys,
        array $extraParameters,
        int $storeId
    ): void {
        $parentType = $data[$parentId]['type_id'] ?? '';
        if (($extraParameters['behaviour'][$parentType]['use_parent_url'] ?? 0) == 2) {
            if (!array_key_exists($productId, $configKeys)) {
                return;
            }
            if (array_key_exists($storeId, $configKeys[$productId][$parentId] ?? [])) {
                $data[$productId][self::ATTR_URL] .= $configKeys[$productId][$parentId][$storeId];
            } elseif (array_key_exists(0, $configKeys[$productId][$parentId] ?? [])) {
                $data[$productId][self::ATTR_URL] .= $configKeys[$productId][$parentId][0];
            }
        }
    }

    /**
     * Set the 'image_logic' flag on product data based on parent relationships and behavior.
     * Implements 4-level image merge logic.
     *
     * @param array &$data
     * @param int $productId
     * @param array $extraParameters
     */
    private function setImageLogic(array &$data, $productId, array $extraParameters): void
    {
        if (isset($data[$productId]['parent_id'], $data[$data[$productId]['parent_id']])) {
            $parentId = $data[$productId]['parent_id'];
            $parentProduct = $data[$parentId];
            $typeId = $parentProduct['type_id'] ?? '';

            $data[$productId]['image_logic'] = $extraParameters['behaviour'][$typeId]['use_parent_images'] ?? 0;

            switch ($data[$productId]['image_logic']) {
                case 1: // Always use parent images
                    if (isset($parentProduct['image_data'])) {
                        $data[$productId]['image_data'] = $parentProduct['image_data'];
                    }
                    break;

                case 2: // Use own images if present, otherwise parent
                    if (empty($data[$productId]['image_data']) && isset($parentProduct['image_data'])) {
                        $data[$productId]['image_data'] = $parentProduct['image_data'];
                    }
                    break;

                case 3: // Merge: child images first, then parent
                    $childImages = $data[$productId]['image_data'] ?? [];
                    $parentImages = $parentProduct['image_data'] ?? [];

                    foreach ($parentImages as $sid => $images) {
                        if (!isset($childImages[$sid])) {
                            $childImages[$sid] = $images;
                        } else {
                            $childImages[$sid] = array_merge($childImages[$sid], $images);
                        }
                    }
                    $data[$productId]['image_data'] = $childImages;
                    break;

                case 4: // Merge: parent images first, then child
                    $childImages = $data[$productId]['image_data'] ?? [];
                    $parentImages = $parentProduct['image_data'] ?? [];

                    foreach ($parentImages as $sid => $images) {
                        if (!isset($childImages[$sid])) {
                            $childImages[$sid] = $images;
                        } else {
                            $childImages[$sid] = array_merge($images, $childImages[$sid]);
                        }
                    }
                    $data[$productId]['image_data'] = $childImages;
                    break;
            }
        } else {
            $data[$productId]['image_logic'] = 0;
        }
    }
}
