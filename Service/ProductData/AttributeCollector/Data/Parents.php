<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Service\ProductData\AttributeCollector\Data;

use Magento\Catalog\Model\Product\Attribute\Source\Status;

/**
 * Collects parent product data (such as configurable or grouped products) for given child products.
 */
class Parents extends AbstractDataCollector
{

    /**
     * @param array $productIds
     * @param bool $excludeDisabled
     * @param int $storeId
     * @return array
     */
    public function execute(array $productIds, bool $excludeDisabled, int $storeId): array
    {
        return $this->collectParentsData($productIds, $excludeDisabled, $storeId);
    }

    /**
     * Collect parent product data for given child product IDs.
     *
     * Includes website filtering, visibility filtering (exclude Not Visible Individually parents),
     * and optional disabled parent filtering.
     *
     * @param array<int> $productIds List of child product IDs to search for.
     * @param bool $excludeDisabled Whether to exclude disabled parent products.
     * @param int $storeId Store ID used for visibility filtering.
     * @return array<int, array<int, string>> Mapping of child IDs to parent IDs and their type_id.
     */
    private function collectParentsData(array $productIds, bool $excludeDisabled, int $storeId): array
    {
        $result = [];

        $connection = $this->resource->getConnection();
        $cprTable = $this->resource->getTableName('catalog_product_relation');
        $cpeTable = $this->resource->getTableName('catalog_product_entity');
        $cpeiTable = $this->resource->getTableName('catalog_product_entity_int');
        $cpwTable = $this->resource->getTableName('catalog_product_website');
        $websiteId = ($store = $this->getStoreById($storeId)) ? $store->getWebsiteId() : 0;

        $notVisibleIds = $this->getNotVisibleProductIds($storeId);

        $select = $connection->select()
            ->from(['cpr' => $cprTable], ['child_id' => 'cpr.child_id', 'parent_id'])
            ->join(
                ['cpe' => $cpeTable],
                "cpe.{$this->linkField} = cpr.parent_id",
                ['type_id' => new \Zend_Db_Expr("COALESCE(cpe.type_id, 'simple')")]
            )->join(
                ['cpw' => $cpwTable],
                "cpw.product_id = cpe.{$this->linkField}",
                []
            )->where('cpw.website_id = ?', $websiteId);

        if ($productIds !== null) {
            $select->where('cpr.child_id IN (?)', $productIds);
        }

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
            $childId = $item['child_id'];
            $parentId = $item['parent_id'];
            if ($parentId !== null && $childId !== null) {
                $result[$childId][$parentId] = $item['type_id'];
            }
        }

        return $result;
    }
}
