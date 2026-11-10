<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Service\ProductData\AttributeCollector\Data;

use Magento\Catalog\Model\Product\Attribute\Source\Status;

/**
 * Service class for retrieving Item Group ID (parent SKU) and Item Group Title (parent name).
 */
class ItemGroup extends AbstractDataCollector
{

    /**
     * @param array $productIds Child product IDs.
     * @param bool $excludeDisabled Exclude disabled parents.
     * @param int $storeId Store view context.
     * @return array<int, array{item_group_id: string, item_group_title: string|null}>
     */
    public function execute(array $productIds, bool $excludeDisabled, int $storeId): array
    {
        return $this->collectItemGroupData($productIds, $excludeDisabled, $storeId);
    }

    /**
     * Collects parent product data (item group) for the specified child product IDs.
     *
     * Returns both the parent SKU (item_group_id) and the parent name (item_group_title)
     * for each child. The name is resolved with store-level fallback via COALESCE.
     *
     * @param array $productIds List of child product IDs to find parent relationships for.
     * @param bool $excludeDisabled Whether to exclude parent products that are disabled.
     * @param int $storeId The store ID context to determine website and visibility filters.
     * @return array<int, array{item_group_id: string, item_group_title: string|null}>
     */
    private function collectItemGroupData(array $productIds, bool $excludeDisabled, int $storeId): array
    {
        $connection = $this->resource->getConnection();
        $result = [];

        $cprTable = $this->resource->getTableName('catalog_product_relation');
        $cpeTable = $this->resource->getTableName('catalog_product_entity');
        $cpeiTable = $this->resource->getTableName('catalog_product_entity_int');
        $cpwTable = $this->resource->getTableName('catalog_product_website');
        $cpevTable = $this->resource->getTableName('catalog_product_entity_varchar');
        $websiteId = ($store = $this->getStoreById($storeId)) ? $store->getWebsiteId() : 0;
        $notVisibleIds = $this->getNotVisibleProductIds($storeId);
        $nameAttributeId = $this->getNameAttributeId();

        $select = $connection->select()
            ->from(['cpr' => $cprTable], ['child_id' => 'cpr.child_id', 'parent_id'])
            ->join(
                ['cpe' => $cpeTable],
                "cpe.{$this->linkField} = cpr.parent_id",
                ['sku']
            )
            // catalog_product_relation also links bundle and grouped parents to their children. Those
            // are not variants: a yoga brick in a bundle grouped under the bundle's SKU is one product
            // hidden behind another in the catalog. Only a configurable's children share a group.
            ->where('cpe.type_id = ?', 'configurable')
            ->join(
                ['cpw' => $cpwTable],
                "cpw.product_id = cpe.{$this->linkField}",
                []
            )->where('cpw.website_id = ?', $websiteId);

        // Join parent name with store-level fallback
        if ($nameAttributeId) {
            $select->joinLeft(
                ['cpev_default' => $cpevTable],
                sprintf(
                    'cpev_default.%1$s = cpe.%1$s AND cpev_default.attribute_id = %2$d AND cpev_default.store_id = 0',
                    $this->linkField,
                    $nameAttributeId
                ),
                []
            )->joinLeft(
                ['cpev_store' => $cpevTable],
                sprintf(
                    'cpev_store.%1$s = cpe.%1$s AND cpev_store.attribute_id = %2$d AND cpev_store.store_id = %3$d',
                    $this->linkField,
                    $nameAttributeId,
                    $storeId
                ),
                ['parent_name' => new \Zend_Db_Expr('COALESCE(cpev_store.value, cpev_default.value)')]
            );
        }

        $select->where('cpr.child_id IN (?)', $productIds);

        if (!empty($notVisibleIds)) {
            $select->where('cpr.parent_id NOT IN (?)', $notVisibleIds);
        }

        if ($excludeDisabled && $this->getStatusAttributeId()) {
            $select->join(
                ['cpei' => $cpeiTable],
                sprintf(
                    'cpei.%1$s = cpe.%1$s AND cpei.attribute_id = %2$d',
                    $this->linkField,
                    $this->getStatusAttributeId()
                ),
                []
            )->where('cpei.value = ?', Status::STATUS_ENABLED);
        }

        foreach ($connection->fetchAll($select) as $item) {
            $result[$item['child_id']] = [
                'item_group_id' => $item['sku'],
                'item_group_title' => $item['parent_name'] ?? null,
            ];
        }

        return $result;
    }
}
