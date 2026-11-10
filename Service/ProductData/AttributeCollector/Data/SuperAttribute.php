<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Service\ProductData\AttributeCollector\Data;

class SuperAttribute extends AbstractDataCollector
{

    public function execute(array $entityIds = [], int $storeId = 0): array
    {
        $result = [];
        $select = $this->resource->getConnection()
            ->select()->from(
                ['catalog_product_relation' => $this->resource->getTableName('catalog_product_relation')],
                ['parent_id' => 'catalog_product_relation.parent_id']
            )->joinLeft(
                ['catalog_product_super_attribute' => $this->resource->getTableName('catalog_product_super_attribute')],
                'catalog_product_super_attribute.product_id = catalog_product_relation.parent_id',
                ['attribute_id']
            )->joinLeft(
                ['eav_attribute' => $this->resource->getTableName('eav_attribute')],
                'eav_attribute.attribute_id = catalog_product_super_attribute.attribute_id',
                ['attribute_code']
            )->joinLeft(
                ['cpe' => $this->resource->getTableName('catalog_product_entity')],
                'cpe.entity_id = catalog_product_relation.child_id',
                []
            )->joinLeft(
                ['catalog_product_entity_int' => $this->resource->getTableName('catalog_product_entity_int')],
                'catalog_product_entity_int.attribute_id = catalog_product_super_attribute.attribute_id'
                . ' AND catalog_product_entity_int.' . $this->linkField . ' = cpe.' . $this->linkField,
                []
            )->joinLeft(
                ['ao' => $this->resource->getTableName('eav_attribute_option')],
                'ao.option_id = catalog_product_entity_int.value',
                []
            )->joinLeft(
                ['aov_default' => $this->resource->getTableName('eav_attribute_option_value')],
                'aov_default.option_id = ao.option_id AND aov_default.store_id = 0',
                []
            )->joinLeft(
                ['aov_store' => $this->resource->getTableName('eav_attribute_option_value')],
                'aov_store.option_id = ao.option_id AND aov_store.store_id = ' . (int)$storeId,
                ['label' => new \Zend_Db_Expr('COALESCE(aov_store.value, aov_default.value)')]
            )->where(
                'catalog_product_relation.child_id IN (?)',
                $entityIds
            )->where(
                'catalog_product_relation.parent_id IN (?)',
                $entityIds
            );

        $keysData = $this->resource->getConnection()->fetchAll($select);
        foreach ($keysData as $item) {
            if (empty($item['label']) || !$item['attribute_code']) {
                continue;
            }

            $parentId = $item['parent_id'];
            $attributeCode = 'config_options_' . $item['attribute_code'];
            $value = $item['label'] ?? $item['value'];

            if (!isset($result[$parentId])) {
                $result[$parentId] = [];
            }
            if (!isset($result[$parentId][$attributeCode])) {
                $result[$parentId][$attributeCode] = [];
            }
            if (!in_array($value, $result[$parentId][$attributeCode], true)) {
                $result[$parentId][$attributeCode][] = $value;
            }
        }

        return $result;
    }
}
