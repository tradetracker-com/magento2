<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Service\ProductData\AttributeCollector\Data;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use TradeTracker\Connect\Api\Log\RepositoryInterface as LogRepository;

abstract class AbstractDataCollector
{

    public ?int $statusAttributeId = null;
    public ?int $visibilityAttributeId = null;
    public ?int $nameAttributeId = null;
    private array $storeCache = [];

    /** @var array<int, string> */
    public array $mediaUrls = [];
    public ?string $linkField;

    public ResourceConnection $resource;
    public LogRepository $logRepository;
    public WebsiteRepositoryInterface $websiteRepository;
    public StoreRepositoryInterface $storeRepository;
    private ModuleManager $moduleManager;

    public function __construct(
        ResourceConnection $resource,
        ModuleManager $moduleManager,
        WebsiteRepositoryInterface $websiteRepository,
        StoreRepositoryInterface $storeRepository,
        LogRepository $logRepository,
        MetadataPool $metadataPool
    ) {
        $this->websiteRepository = $websiteRepository;
        $this->storeRepository = $storeRepository;
        $this->resource = $resource;
        $this->logRepository = $logRepository;
        $this->moduleManager = $moduleManager;
        $this->linkField = $metadataPool->getMetadata(ProductInterface::class)->getLinkField();
    }

    public function getStoreById(int $storeId): ?StoreInterface
    {
        if (array_key_exists($storeId, $this->storeCache)) {
            return $this->storeCache[$storeId];
        }

        try {
            $store = $this->storeRepository->getById($storeId);
            $this->storeCache[$storeId] = $store;
            return $store;
        } catch (\Throwable $e) {
            $this->storeCache[$storeId] = null;
            return null;
        }
    }

    public function isMsiEnabled(): bool
    {
        return $this->moduleManager->isEnabled('Magento_Inventory');
    }

    public function getStatusAttributeId(): ?int
    {
        if (!$this->statusAttributeId) {
            $this->prefetchAttributeIds();
        }

        return $this->statusAttributeId;
    }

    /**
     * Prefetch and cache attribute IDs for `status` and `visibility`
     */
    private function prefetchAttributeIds(): void
    {
        $connection = $this->resource->getConnection();

        $entityTypeTable = $this->resource->getTableName('eav_entity_type');
        $eavTable = $this->resource->getTableName('eav_attribute');

        $attributes = $connection->fetchPairs(
            $connection->select()
                ->from(['ea' => $eavTable], ['attribute_code', 'attribute_id'])
                ->join(
                    ['et' => $entityTypeTable],
                    'ea.entity_type_id = et.entity_type_id',
                    []
                )
                ->where('et.entity_type_code = ?', 'catalog_product')
                ->where('ea.attribute_code IN (?)', ['status', 'visibility', 'name'])
        );

        $this->statusAttributeId = (int)($attributes['status'] ?? 0);
        $this->visibilityAttributeId = (int)($attributes['visibility'] ?? 0);
        $this->nameAttributeId = (int)($attributes['name'] ?? 0);
    }

    /**
     * Get product IDs that are set to "Not Visible Individually", accounting for store fallback.
     *
     * @param int $storeId
     * @return array
     */
    public function getNotVisibleProductIds(int $storeId): array
    {
        if (!$this->getVisibilityAttributeId()) {
            return [];
        }

        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('catalog_product_entity_int');

        $selectBase = $connection->select()
            ->distinct()
            ->from(['cpei' => $table], [$this->linkField])
            ->where('cpei.attribute_id = ?', $this->getVisibilityAttributeId())
            ->where('cpei.value = ?', Visibility::VISIBILITY_NOT_VISIBLE)
            ->where('cpei.store_id = ?', 0);

        $selectStore = $connection->select()
            ->distinct()
            ->from(['cpei' => $table], [$this->linkField])
            ->where('cpei.attribute_id = ?', $this->getVisibilityAttributeId())
            ->where('cpei.value != ?', Visibility::VISIBILITY_NOT_VISIBLE)
            ->where('cpei.store_id = ?', $storeId);

        return array_diff(
            $connection->fetchCol($selectBase),
            $connection->fetchCol($selectStore)
        );
    }

    public function getVisibilityAttributeId(): ?int
    {
        if (!$this->visibilityAttributeId) {
            $this->prefetchAttributeIds();
        }

        return $this->visibilityAttributeId;
    }

    public function getNameAttributeId(): ?int
    {
        if (!$this->nameAttributeId) {
            $this->prefetchAttributeIds();
        }

        return $this->nameAttributeId;
    }

    public function tableExistsAndNotEmpty(string $tableName): bool
    {
        $connection = $this->resource->getConnection();
        if (!$connection->isTableExists($tableName)) {
            return false;
        }

        $select = $connection->select()
            ->from($tableName, new \Zend_Db_Expr('COUNT(*)'));

        $count = (int)$connection->fetchOne($select);

        return $count > 0;
    }

    public function getMediaUrl(StoreInterface $store, ?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
            return $path;
        }

        $storeId = (int)$store->getId();
        if (!isset($this->mediaUrls[$storeId])) {
            try {
                $this->mediaUrls[$storeId] = $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) . 'catalog/product';
            } catch (\Exception $exception) {
                $this->mediaUrls[$storeId] = '';
            }
        }

        return $this->mediaUrls[$storeId] . '/' . ltrim(str_replace('\\', '/', $path), '/');
    }
}
