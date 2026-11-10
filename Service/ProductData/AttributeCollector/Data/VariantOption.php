<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Service\ProductData\AttributeCollector\Data;

/**
 * Collects Google Shopping "variant_option" data per child product.
 *
 * For every simple product that is part of a configurable product, this builds the
 * name/value pairs that identify the variant (e.g. name="Color" value="Red",
 * name="Size" value="M"). The names come from the configurable's super attributes
 * (catalog_product_super_attribute), so every child of the same parent shares an
 * identical, consistently ordered set of names — a requirement for the attribute.
 *
 * @see https://support.google.com/merchants/answer/17085214
 */
class VariantOption extends AbstractDataCollector
{

    /**
     * Build variant option pairs keyed by child product id.
     *
     * @param array $entityIds Product ids in the current batch (children and parents).
     * @param int $storeId Store view context for label resolution.
     * @return array<int, array<int, array{name: string, value: string}>>
     */
    public function execute(array $entityIds = [], int $storeId = 0): array
    {
        if (empty($entityIds)) {
            return [];
        }

        $connection = $this->resource->getConnection();
        $select = $connection->select()->from(
            ['catalog_product_relation' => $this->resource->getTableName('catalog_product_relation')],
            ['child_id' => 'catalog_product_relation.child_id']
        )->join(
            ['catalog_product_super_attribute' => $this->resource->getTableName('catalog_product_super_attribute')],
            'catalog_product_super_attribute.product_id = catalog_product_relation.parent_id',
            ['attribute_id', 'position']
        )->join(
            ['eav_attribute' => $this->resource->getTableName('eav_attribute')],
            'eav_attribute.attribute_id = catalog_product_super_attribute.attribute_id',
            ['frontend_label']
        )->join(
            ['cpe' => $this->resource->getTableName('catalog_product_entity')],
            'cpe.entity_id = catalog_product_relation.child_id',
            []
        )->join(
            ['catalog_product_entity_int' => $this->resource->getTableName('catalog_product_entity_int')],
            'catalog_product_entity_int.attribute_id = catalog_product_super_attribute.attribute_id'
            . ' AND catalog_product_entity_int.' . $this->linkField . ' = cpe.' . $this->linkField
            . ' AND catalog_product_entity_int.store_id IN (0, ' . (int)$storeId . ')',
            []
        )->joinLeft(
            ['eal_store' => $this->resource->getTableName('eav_attribute_label')],
            'eal_store.attribute_id = catalog_product_super_attribute.attribute_id'
            . ' AND eal_store.store_id = ' . (int)$storeId,
            ['name' => new \Zend_Db_Expr('COALESCE(eal_store.value, eav_attribute.frontend_label)')]
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
            ['value' => new \Zend_Db_Expr('COALESCE(aov_store.value, aov_default.value)')]
        )->where(
            'catalog_product_relation.child_id IN (?)',
            $entityIds
        )->where(
            'catalog_product_relation.parent_id IN (?)',
            $entityIds
        )->order('catalog_product_relation.child_id')
            ->order('catalog_product_super_attribute.position')
            ->order('catalog_product_super_attribute.attribute_id')
            // The join on catalog_product_entity_int matches both store_id 0 (default) and the
            // current store view, which can produce two rows per child+attribute. Ordering by
            // store_id DESC ensures the store-specific row (higher id) comes first. The PHP
            // dedup below ($seen) keeps only the first row per child+attribute, so the store
            // override wins and the default fallback is discarded.
            ->order('catalog_product_entity_int.store_id DESC');

        $result = [];
        $seen = [];
        foreach ($connection->fetchAll($select) as $item) {
            $name = (string)($item['name'] ?? '');
            $value = (string)($item['value'] ?? '');
            if ($name === '' || $value === '') {
                continue;
            }

            $childId = (int)$item['child_id'];
            // Keep one pair per child+attribute. A child may appear through multiple parent
            // relations, and the store_id ordering above may produce duplicate rows — the
            // first occurrence is the most specific (store-level) value.
            if (isset($seen[$childId][$item['attribute_id']])) {
                continue;
            }
            $seen[$childId][$item['attribute_id']] = true;

            $result[$childId][] = ['name' => $name, 'value' => $value];
        }

        return $result;
    }
}
