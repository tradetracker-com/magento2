<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Service\ProductData\AttributeCollector\Data;

use Magento\Store\Api\Data\StoreInterface;

class Image extends AbstractDataCollector
{

    private array $entityIds;
    private array $productIds;
    private bool $includeHidden;
    private StoreInterface $store;

    public function execute(array $entityIds, StoreInterface $store, bool $includeHidden, array $productIds): array
    {
        $this->entityIds = $entityIds;
        $this->store = $store;
        $this->includeHidden = $includeHidden;
        $this->productIds = $productIds;

        return $this->combineData(
            $this->collectImages(),
            $this->collectTypes()
        );
    }

    /**
     * Collect product images efficiently.
     *
     * @return array
     */
    private function collectImages(): array
    {
        if (empty($this->entityIds)) {
            return [];
        }

        $connection = $this->resource->getConnection();
        $mediaGalleryTable = $this->resource->getTableName('catalog_product_entity_media_gallery');
        $mediaGalleryValueTable = $this->resource->getTableName('catalog_product_entity_media_gallery_value');

        $select = $connection->select()
            ->from(['mg' => $mediaGalleryTable], ['value'])
            ->join(
                ['mgv' => $mediaGalleryValueTable],
                'mg.value_id = mgv.value_id',
                ['entity_id' => $this->linkField, 'store_id', 'position']
            )
            ->where('mgv.store_id IN (?)', [0, (int)$this->store->getId()])
            ->where('mgv.' . $this->linkField . ' IN (?)', $this->entityIds);

        if (!$this->includeHidden) {
            $select->where('mgv.disabled = 0');
        }

        return $connection->fetchAll($select);
    }

    /**
     * Collect product types efficiently.
     *
     * @return array
     */
    private function collectTypes(): array
    {
        if (empty($this->entityIds)) {
            return [];
        }

        $connection = $this->resource->getConnection();
        $eavTable = $this->resource->getTableName('eav_attribute');
        $cpevTable = $this->resource->getTableName('catalog_product_entity_varchar');

        // Define only required fields
        $fields = ['entity_id' => $this->linkField, 'store_id', 'value'];

        $select = $connection->select()
            ->from(['ea' => $eavTable], ['attribute_code'])
            ->join(
                ['cpev' => $cpevTable],
                'cpev.attribute_id = ea.attribute_id',
                $fields
            )
            ->where('ea.frontend_input = ?', 'media_image')
            ->where('cpev.store_id IN (?)', [0, (int)$this->store->getId()])
            ->where('cpev.' . $this->linkField . ' IN (?)', $this->entityIds);

        $data = [];
        foreach ($connection->fetchAll($select) as $item) {
            $data[$item['entity_id']][$item['value']][$item['store_id']][] = $item['attribute_code'];
        }

        return $data;
    }

    /**
     * Combine image and type data efficiently.
     *
     * @param array $imagesData
     * @param array $typesData
     * @return array
     */
    private function combineData(array $imagesData, array $typesData): array
    {
        $result = [];

        foreach ($imagesData as $imageData) {
            $rowId = $imageData['entity_id'];
            $productId = $this->productIds[$rowId] ?? null;
            if ($productId === null) {
                continue;
            }

            $storeId = (int)$imageData['store_id'];
            $position = (int)$imageData['position'];
            $imageValue = $imageData['value'];

            $result[$productId][$storeId][] = [
                'file' => $this->getMediaUrl($this->store, $imageValue),
                'position' => $position,
                'types' => $typesData[$rowId][$imageValue][$storeId] ?? []
            ];
        }

        // Sort by position ascending
        foreach ($result as &$stores) {
            foreach ($stores as &$images) {
                usort($images, function ($a, $b) {
                    return (int)$a['position'] <=> (int)$b['position'];
                });
            }
        }

        return $result;
    }
}
