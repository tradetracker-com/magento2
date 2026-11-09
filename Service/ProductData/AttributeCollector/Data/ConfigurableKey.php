<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Service\ProductData\AttributeCollector\Data;

use Magento\Catalog\Model\Product\Attribute\Source\Status;

/**
 * Service class to collect configurable keys for simple products
 */
class ConfigurableKey extends AbstractDataCollector
{

    public function execute(array $productIds, bool $excludeDisabled, int $storeId): array
    {
        return $this->collectKeys($productIds, $excludeDisabled, $storeId);
    }

    /**
     * Collect configurable product keys for child–parent combinations.
     * This method queries product relations, super attributes, and attribute values
     * to generate a mapping of super attribute selections per child–parent–store combination.
     * The SQL joins performed are:
     *   - Joins catalog_product_relation to catalog_product_super_attribute on parent_id
     *   - Joins to catalog_product_entity for child link field
     *   - Joins to catalog_product_entity_int for attribute values per store
     * The result is formatted as a nested array mapping:
     *   [childProductId][parentProductId][storeId] = "#<attribute_id>=<value>&<attribute_id>=<value>"
     * This string mimics the query string of configurable product selections.
     *
     * @return array<int, array<int, array<int, string>>> Nested array structure:
     *   [childProductId][parentProductId][storeId] = "#<attribute_id>=<value>&<attribute_id>=<value>"
     */
    private function collectKeys(array $productIds, bool $excludeDisabled, int $storeId): array
    {
        $result = [];
        $connection = $this->resource->getConnection();

        $cprTable = $this->resource->getTableName('catalog_product_relation');
        $cpeTable = $this->resource->getTableName('catalog_product_entity');
        $cpsaTable = $this->resource->getTableName('catalog_product_super_attribute');
        $cpeiTable = $this->resource->getTableName('catalog_product_entity_int');
        $notVisibleIds = $this->getNotVisibleProductIds($storeId);

        $select = $connection->select()
            ->from(['cpr' => $cprTable], ['child_id' => 'cpr.child_id', 'parent_id'])
            ->joinLeft(
                ['cpsa' => $cpsaTable],
                'cpsa.product_id = cpr.parent_id',
                'attribute_id'
            )
            ->joinLeft(
                ['cpe' => $cpeTable],
                'cpe.entity_id = cpr.child_id',
                []
            )
            ->joinLeft(
                ['cpei' => $cpeiTable],
                'cpei.attribute_id = cpsa.attribute_id'
                . ' AND cpei.' . $this->linkField . ' = cpe.' . $this->linkField,
                ['value', 'store_id']
            );

        $select->where('cpr.child_id IN (?)', $productIds);

        if (!empty($notVisibleIds)) {
            $select->where('cpr.parent_id NOT IN (?)', $notVisibleIds);
        }

        foreach ($connection->fetchAll($select) as $item) {
            $pPid = $item['parent_id'];
            $cPid = $item['child_id'];
            // A child without a value for the super attribute joins no entity_int row: store_id and value are
            // null and there is no key to build (null as an array offset is deprecated as of PHP 8.5).
            if ($pPid !== null && $cPid !== null && $item['store_id'] !== null) {
                $result[$cPid][$pPid][(int)$item['store_id']][] = $item['attribute_id'] . '=' . $item['value'];
            }
        }

        // Convert arrays to strings with "#" prefix and no trailing "&", into their own array:
        // writing the string back over the list it was built from gives the result two shapes.
        $keys = [];
        foreach ($result as $childId => $parents) {
            foreach ($parents as $parentId => $stores) {
                foreach ($stores as $storeId => $values) {
                    $keys[$childId][$parentId][$storeId] = '#' . implode('&', $values);
                }
            }
        }

        return $keys;
    }
}
