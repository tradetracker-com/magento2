<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Service\ProductData;

use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Store\Model\StoreManagerInterface;

class Filter
{

    private ResourceConnection $resourceConnection;
    private StoreManagerInterface $storeManager;
    private string $entityId;
    private ?int $statusAttributeId = null;
    private ?int $visibilityAttributeId = null;

    public function __construct(
        ResourceConnection $resourceConnection,
        StoreManagerInterface $storeManager,
        MetadataPool $metadataPool
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->storeManager = $storeManager;
        $this->entityId = $metadataPool->getMetadata(ProductInterface::class)->getLinkField();
    }

    /**
     * Execute filters and return product entity ids
     *
     * @param array $filter
     * @param int $storeId
     * @return array
     */
    public function execute(array $filter, int $storeId = 0): array
    {
        $this->prefetchAttributeIds();

        $entityIds = $this->filterVisibility($filter, $storeId);
        $entityIds = $this->filterStatus($entityIds, $filter['add_disabled_products'], $storeId);

        return $this->filterByWebsiteAndCategory(
            $entityIds,
            $storeId
                ? $this->getWebsiteId($storeId)
                : null,
            $filter['restrict_by_category']
                ? $filter['category_restriction_behaviour']
                : null,
            $filter['category']
        );
    }

    /**
     * Prefetch and cache attribute IDs for `status` and `visibility`
     */
    private function prefetchAttributeIds(): void
    {
        if ($this->statusAttributeId !== null) {
            return;
        }

        $connection = $this->resourceConnection->getConnection();

        $entityTypeTable = $this->resourceConnection->getTableName('eav_entity_type');
        $eavTable = $this->resourceConnection->getTableName('eav_attribute');

        $attributes = $connection->fetchPairs(
            $connection->select()
                ->from(['ea' => $eavTable], ['attribute_code', 'attribute_id'])
                ->join(
                    ['et' => $entityTypeTable],
                    'ea.entity_type_id = et.entity_type_id',
                    []
                )
                ->where('et.entity_type_code = ?', 'catalog_product')
                ->where('ea.attribute_code IN (?)', ['status', 'visibility'])
        );

        $this->statusAttributeId = (int)($attributes['status'] ?? 0);
        $this->visibilityAttributeId = (int)($attributes['visibility'] ?? 0);
    }

    /**
     * Filter entity IDs to exclude products based on visibility.
     * Uses COALESCE pattern: LEFT JOIN store override, only include if effective value matches.
     *
     * @param array $filter
     * @param int $storeId
     * @return array
     */
    private function filterVisibility(array $filter, int $storeId = 0): array
    {
        if (!$this->visibilityAttributeId) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('catalog_product_entity_int');

        $visibility = $filter['filter_by_visibility']
            ? (is_array($filter['visibility']) ? $filter['visibility'] : explode(',', $filter['visibility']))
            : [
                Visibility::VISIBILITY_NOT_VISIBLE,
                Visibility::VISIBILITY_IN_CATALOG,
                Visibility::VISIBILITY_IN_SEARCH,
                Visibility::VISIBILITY_BOTH,
            ];

        $visibility = array_map('intval', $visibility);

        $select = $connection->select()
            ->distinct()
            ->from(['base' => $table], [$this->entityId])
            ->joinLeft(
                ['override' => $table],
                sprintf(
                    'base.%1$s = override.%1$s AND base.attribute_id = override.attribute_id AND override.store_id = %2$d',
                    $this->entityId,
                    $storeId
                ),
                []
            )
            ->where('base.attribute_id = ?', $this->visibilityAttributeId)
            ->where('base.store_id = ?', 0)
            ->where('base.value IN (?)', $visibility)
            ->where(sprintf(
                '(override.value IS NULL OR override.value IN (%s))',
                implode(',', array_fill(0, count($visibility), '?'))
            ), $visibility);

        return $connection->fetchCol($select);
    }

    /**
     * Filter entity IDs to exclude disabled products using COALESCE store override.
     *
     * @param array $entityIds
     * @param bool $addDisabled
     * @param int $storeId
     * @return array
     */
    private function filterStatus(array $entityIds, bool $addDisabled = false, int $storeId = 0): array
    {
        if (empty($entityIds) || $addDisabled || !$this->statusAttributeId) {
            return $entityIds;
        }

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('catalog_product_entity_int');

        $select = $connection->select()
            ->distinct()
            ->from(['base' => $table], [$this->entityId])
            ->joinLeft(
                ['override' => $table],
                sprintf(
                    'base.%1$s = override.%1$s AND base.attribute_id = override.attribute_id AND override.store_id = %2$d',
                    $this->entityId,
                    $storeId
                ),
                []
            )
            ->where('base.attribute_id = ?', $this->statusAttributeId)
            ->where('base.store_id = ?', 0)
            ->where('base.' . $this->entityId . ' IN (?)', $entityIds)
            ->where(sprintf(
                '(COALESCE(override.value, base.value) = %d)',
                Status::STATUS_ENABLED
            ));

        return $connection->fetchCol($select);
    }

    /**
     * @param int $storeId
     * @return int
     */
    private function getWebsiteId(int $storeId = 0): int
    {
        try {
            return (int)$this->storeManager->getStore($storeId)->getWebsiteId();
        } catch (Exception $exception) {
            return 0;
        }
    }

    /**
     * Filter entity IDs based on website and category restrictions in a single query.
     *
     * @param array $entityIds
     * @param int|null $websiteId
     * @param string|null $categoryBehaviour
     * @param array|null $categoryIds
     * @return array
     */
    private function filterByWebsiteAndCategory(
        array $entityIds,
        ?int $websiteId,
        ?string $categoryBehaviour = null,
        ?array $categoryIds = null
    ): array {
        if (empty($entityIds)) {
            return [];
        }

        if ($websiteId === null) {
            return $entityIds;
        }

        $connection = $this->resourceConnection->getConnection();
        $cpeTable = $this->resourceConnection->getTableName('catalog_product_entity');
        $cpwTable = $this->resourceConnection->getTableName('catalog_product_website');
        $ccpTable = $this->resourceConnection->getTableName('catalog_category_product');

        $select = $connection->select()
            ->distinct()
            ->from(['cpe' => $cpeTable], [$this->entityId])
            ->join(['cpw' => $cpwTable], 'cpe.entity_id = cpw.product_id', [])
            ->where('cpw.website_id = ?', $websiteId)
            ->where("cpe.{$this->entityId} IN (?)", $entityIds);

        if (!empty($categoryIds) && $categoryBehaviour !== null) {
            $select->join(['ccp' => $ccpTable], 'cpe.entity_id = ccp.product_id', []);
            if ($categoryBehaviour === 'in') {
                $select->where('ccp.category_id IN (?)', $categoryIds);
            } else {
                $select->where('ccp.category_id NOT IN (?)', $categoryIds);
            }
        }

        return $connection->fetchCol($select);
    }
}
