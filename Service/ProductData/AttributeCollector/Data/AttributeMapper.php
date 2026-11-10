<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Service\ProductData\AttributeCollector\Data;

use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;

/**
 * Service class for attribute data
 */
class AttributeMapper extends AbstractDataCollector
{

    /**
     * Values table pattern
     */
    public const TABLE_PATTERN = '%s_%s';

    /**
     * Data set to fetch from eav_attribute table
     */
    public const EAV_ATTRIBUTES_DATA_SET = [
        'attribute_id',
        'attribute_code',
        'backend_type',
        'frontend_input'
    ];

    /**
     * Data set to fetch from eav_entity_type table
     */
    public const EAV_ENTITY_TYPE_DATA_SET = [
        'entity_type_code',
        'entity_type_id',
        'entity_table'
    ];

    /**
     * Required attributes
     */
    public const REQUIRE = [
        'entity_ids',
        'store_id',
        'map',
        'entity_type_code'
    ];

    private array $map = [];
    private StoreInterface $store;
    private string $entityTypeCode;
    private int $storeId = 0;
    private array $entityIds;
    private array $result = [];
    private array $attrOptions = [];

    public function execute(
        array $entityIds,
        StoreInterface $store,
        array $map = [],
        string $entityTypeCode = ''
    ): array {
        $this->result = [];
        $this->storeId = (int)$store->getId();
        $this->store = $store;
        $this->attrOptions = $this->collectAttributeOptions();
        $this->map = $map;
        $this->entityIds = $entityIds;
        $this->entityTypeCode  = $entityTypeCode;

        $attributes = $this->getAttributes();
        $this->collectAttributeValues($attributes);
        $this->collectExtraData();
        return $this->result;
    }

    private function collectAttributeOptions(): array
    {
        $attrOptions = [];

        $select = $this->resource->getConnection()
            ->select()
            ->from(
                ['eav_attribute_option' => $this->resource->getTableName('eav_attribute_option')],
                ['attribute_id']
            )->joinLeft(
                ['eav_attribute_option_value' => $this->resource->getTableName('eav_attribute_option_value')],
                'eav_attribute_option_value.option_id = eav_attribute_option.option_id',
                [
                    'option_id',
                    'store_id',
                    'value'
                ]
            )->where(
                'store_id IN (?)',
                [0, $this->storeId]
            )->order(
                'store_id ASC'
            );

        $options = $this->resource->getConnection()->fetchAll($select);
        foreach ($options as $option) {
            $attrOptions[$option['attribute_id']][$option['option_id']] = $option['value'];
        }

        return $attrOptions;
    }

    /**
     * Fetch attributes data from eav_attribute table according provided map
     *
     * @return array[] 'attribute_id', 'entity_type_id', 'attribute_code' and 'backend_type'
     */
    private function getAttributes(): array
    {
        $select = $this->resource->getConnection()
            ->select()
            ->from(
                ['eav_attribute' => $this->resource->getTableName('eav_attribute')],
                self::EAV_ATTRIBUTES_DATA_SET
            )->joinLeft(
                ['eav_entity_type' => $this->resource->getTableName('eav_entity_type')],
                'eav_attribute.entity_type_id = eav_entity_type.entity_type_id',
                ['entity_table']
            )->where(
                'eav_entity_type.entity_type_code = ?',
                $this->entityTypeCode
            )->where(
                'eav_attribute.attribute_code IN (?)',
                $this->map
            );

        return $this->resource->getConnection()->fetchAll($select);
    }

    /**
     * Get specific attribute values for selected entities for all existing stores
     *
     * @param array[] $attributes 'attribute_id', 'attribute_code', 'backend_type', 'entity_table'
     */
    private function collectAttributeValues(array $attributes): void
    {
        if ($attributes === []) {
            return;
        }

        $tablesNonStatic = [];
        $attributeIdsNonStatic = [];
        $attributeStaticCode = array_unique(['entity_id', 'type_id', $this->linkField]);
        $relations = [];
        $withUrl = [];

        foreach ($attributes as $attribute) {
            if ($attribute['frontend_input'] == 'media_image' || $attribute['frontend_input'] == 'image') {
                $withUrl[] = $attribute['attribute_id'];
            }
            $relations[$attribute['attribute_id']] = $attribute['attribute_code'];
            if ($attribute['backend_type'] != 'static') {
                $table = $attribute['entity_table'] . '_' . $attribute['backend_type'];
                $tablesNonStatic[] = $table;
                $attributeIdsNonStatic[$table][] = $attribute['attribute_id'];
            } else {
                $attributeStaticCode[] = $attribute['attribute_code'];
            }
        }
        $tablesNonStatic = array_unique($tablesNonStatic);
        $first = reset($attributes);
        $entityTable = $first['entity_table'];

        foreach ($tablesNonStatic as $table) {
            $fields = ['attribute_id', 'store_id', 'value', 'entity_id' => $this->linkField];
            $select = $this->resource->getConnection()
                ->select()
                ->from(
                    [$table => $this->resource->getTableName($table)],
                    $fields
                )->where(
                    "{$this->linkField} IN (?)",
                    $this->entityIds
                )->where(
                    'attribute_id in (?)',
                    $attributeIdsNonStatic[$table]
                )->where(
                    'store_id in (?)',
                    [0, $this->storeId]
                );

            $result = $this->resource->getConnection()->fetchAll($select);
            foreach ($result as $item) {
                if (array_key_exists($item['attribute_id'], $this->attrOptions)) {
                    $item['value'] = $this->resolveOptionValue($item);
                }
                if (isset($this->result[$relations[$item['attribute_id']]][$item['entity_id']])
                    && $item['store_id'] == 0) {
                    continue;
                }
                if (in_array($item['attribute_id'], $withUrl)) {
                    $item['value'] = $this->getMediaUrl($this->store, $item['value']);
                }
                $this->result[$relations[$item['attribute_id']]][$item['entity_id']] =
                    str_replace(["\r", "\n"], '', (string)$item['value']);
            }
        }

        $this->adjustTaxClassLabels();
        $select = $this->resource->getConnection()
            ->select()
            ->from(
                $this->resource->getTableName($entityTable),
                $attributeStaticCode
            );
        $select->where("{$this->linkField} IN (?)", $this->entityIds);
        $result = $this->resource->getConnection()->fetchAll($select);

        foreach ($result as $item) {
            foreach ($attributeStaticCode as $static) {
                if ($static == 'entity_id') {
                    continue;
                }
                $this->result[$static][$item[$this->linkField]] = $item[$static];
            }
        }
    }

    private function adjustTaxClassLabels(): void
    {
        if (!array_key_exists('tax_class_id', $this->result)) {
            return;
        }
        $connection = $this->resource->getConnection();
        $selectClasses = $connection->select()->from(
            $this->resource->getTableName('tax_class'),
            ['class_id', 'class_name']
        );
        $taxClassLabels = $connection->fetchPairs($selectClasses);
        foreach ($this->result['tax_class_id'] as &$taxClassId) {
            $taxClassId = $taxClassLabels[$taxClassId] ?? 0;
        }
    }

    /**
     * Resolve option label(s) for an EAV attribute value.
     * Fast path for single-value attributes (no explode/implode overhead).
     *
     * @param array $item Row with 'attribute_id' and 'value' keys.
     * @return string Resolved label(s), comma-separated for multi-select.
     */
    private function resolveOptionValue(array $item): string
    {
        $itemValue = (string)$item['value'];
        $attributeId = (string)$item['attribute_id'];

        if (strpos($itemValue, ',') === false) {
            return isset($this->attrOptions[$attributeId][$itemValue])
                ? $this->attrOptions[$attributeId][$itemValue]
                : $itemValue;
        }

        $resolved = [];
        foreach (array_filter(explode(',', $itemValue)) as $attrValue) {
            if (isset($this->attrOptions[$attributeId][$attrValue])) {
                $resolved[] = $this->attrOptions[$attributeId][$attrValue];
            }
        }

        return implode(',', $resolved);
    }

    /**
     * Collect data not related to attributes
     */
    private function collectExtraData(): void
    {
        $fields = array_unique([$this->linkField, 'type_id', 'created_at', 'updated_at', 'entity_id']);
        $select = $this->resource->getConnection()->select()
            ->from(
                ['catalog_product_entity' => $this->resource->getTableName('catalog_product_entity')],
                $fields
            )->where(
                $this->linkField . ' IN (?)',
                $this->entityIds
            );

        // The link field is resolved once per collector and is a column name by then; reading it
        // into a local says so, and null as an array offset is a fatal on PHP 8.5.
        $linkField = (string)$this->linkField;

        $items = $this->resource->getConnection()->fetchAll($select);
        foreach ($items as $item) {
            if (!isset($item[$linkField])) {
                continue;
            }

            foreach ($fields as $field) {
                $this->result[$field][$item[$linkField]] = $item[$field];
            }
        }
    }
}
