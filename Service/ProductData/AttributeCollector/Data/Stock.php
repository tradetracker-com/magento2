<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Service\ProductData\AttributeCollector\Data;

use Magento\Store\Api\Data\StoreInterface;

class Stock extends AbstractDataCollector
{

    private array $entityIds;
    private StoreInterface $store;

    public function execute(array $productIds, StoreInterface $store, bool $allChannels = false): array
    {
        $this->store = $store;
        $this->entityIds = $productIds;

        return ($this->isMsiEnabled())
            ? $this->getMsiStock($allChannels)
            : $this->getNoMsiStock();
    }

    /**
     * Get stock qty for specified products if MSI enabled.
     *
     * The top-level figures are those of the sales channel the store belongs to, which is the feed
     * this run is building. With $allChannels the other channels are reported alongside, so a
     * caller that sells the same catalogue through several websites can see all of them at once.
     *
     * [product_id] => [
     *      qty
     *      is_in_stock
     *      is_salable
     *      availability
     *      salable_qty
     *      ["msi"] => [                     // only with $allChannels
     *          stock_id => [
     *              qty
     *              availability
     *              is_salable
     *              salable_qty
     *              own_channel              // true for the store's own channel
     *          ]
     *      ]
     * ]
     *
     * @param bool $allChannels Also report every other sales channel under ["msi"].
     * @return array[]
     */
    private function getMsiStock(bool $allChannels = false): array
    {
        $storeId = (int)$this->store->getId();
        $stockId = $this->getStockId($storeId);
        $stockData = $this->collectMsi($stockId);
        $reservations = $this->collectMsiReservations($stockId);
        $result = $this->getNoMsiStock();

        foreach ($stockData as $value) {
            if (!array_key_exists($value['product_id'], $result)) {
                continue;
            }

            $quantity = (int)($value['quantity'] ?? 0);
            // Keep the reservations map intact: assigning the product's own figure back over it
            // left every product after the first one reading an int offset, so only the first
            // had its reservations subtracted and the rest published a salable_qty that was too
            // high by exactly the reserved amount.
            $reserved = max((int)($reservations[$value['product_id']] ?? 0), 0);

            $result[$value['product_id']]['qty'] = $quantity;
            $result[$value['product_id']]['is_salable'] = $value['is_salable'] ?? 0;
            $result[$value['product_id']]['availability'] = $value['is_salable'] ?? 0;
            $result[$value['product_id']]['is_in_stock'] = $value['is_salable'] ?? 0;
            $result[$value['product_id']]['salable_qty'] = $quantity - $reserved;
        }

        if ($allChannels) {
            $result = $this->addChannelStock($result, $stockId);
        }

        return $result;
    }

    /**
     * Report every website sales channel per product, the store's own included.
     *
     * The top-level figures stay the ones of the channel this feed run is for; a caller that needs
     * to know what the other channels hold reads them here, keyed by stock id, instead of the feed
     * carrying a column per channel.
     *
     * @param array[] $result
     * @param int $ownStockId
     * @return array[]
     */
    private function addChannelStock(array $result, int $ownStockId): array
    {
        foreach ($this->getChannelStockIds() as $stockId) {
            $reservations = $this->collectMsiReservations($stockId);
            foreach ($this->collectMsi($stockId) as $value) {
                $productId = $value['product_id'];
                if (!array_key_exists($productId, $result)) {
                    continue;
                }

                $quantity = (int)($value['quantity'] ?? 0);
                $reserved = max((int)($reservations[$productId] ?? 0), 0);
                $salable = $value['is_salable'] ?? 0;

                $result[$productId]['msi'][$stockId] = [
                    'qty' => $quantity,
                    'availability' => $salable,
                    'is_salable' => $salable,
                    'salable_qty' => $quantity - $reserved,
                    'own_channel' => $stockId === $ownStockId,
                ];
            }
        }

        return $result;
    }

    /**
     * Every stock a website sells from.
     *
     * @return array<int, int>
     */
    private function getChannelStockIds(): array
    {
        try {
            $select = $this->resource->getConnection()->select()
                ->from($this->resource->getTableName('inventory_stock_sales_channel'), ['stock_id'])
                ->where('type = ?', 'website');

            return array_map('intval', array_unique($this->resource->getConnection()->fetchCol($select)));
        } catch (\Exception $exception) {
            $this->logRepository->addErrorLog('MSI getChannelStockIds', $exception->getMessage());
            return [];
        }
    }

    /**
     * Get MSI stock channels
     *
     * @param int $storeId
     * @return int
     */
    private function getStockId(int $storeId): int
    {
        try {
            $website = $this->websiteRepository->getById($this->store->getWebsiteId());
            $select = $this->resource->getConnection()->select()
                ->from(
                    $this->resource->getTableName('inventory_stock_sales_channel'),
                    ['stock_id']
                )
                ->where('type = ?', 'website')
                ->where('code = ?', $website->getCode())
                ->limit(1);
            return (int)$this->resource->getConnection()->fetchOne($select);
        } catch (\Exception $exception) {
            $this->logRepository->addErrorLog('MSI getStockId', $exception->getMessage());
            return 1;
        }
    }

    /**
     * Collect MSI stock data
     *
     * @param int $stockId
     * @return array[]
     */
    private function collectMsi(int $stockId): array
    {
        $connection = $this->resource->getConnection();
        $stockView = sprintf('inventory_stock_%s', (int)$stockId);

        if ($this->tableExistsAndNotEmpty($stockView)) {
            $select = $connection->select()
                ->from(
                    ['cpe' => $this->resource->getTableName('catalog_product_entity')],
                    ['product_id' => 'entity_id', $this->linkField]
                )
                ->where('cpe.entity_id IN (?)', $this->entityIds)
                ->joinLeft(
                    ['inv_stock' => $this->resource->getTableName($stockView)],
                    'cpe.sku = inv_stock.sku',
                    ['quantity', 'is_salable']
                );

            return $connection->fetchAll($select);
        }

        // Fallback: sum quantity across all sources (legacy)
        $select = $connection->select()
            ->from(
                ['cpe' => $this->resource->getTableName('catalog_product_entity')],
                ['product_id' => 'entity_id', $this->linkField, 'sku']
            )
            ->where('cpe.entity_id IN (?)', $this->entityIds)
            ->joinLeft(
                ['source_item' => $this->resource->getTableName('inventory_source_item')],
                'cpe.sku = source_item.sku',
                [
                    'quantity' => new \Zend_Db_Expr('SUM(source_item.quantity)'),
                    'is_salable' => new \Zend_Db_Expr('IF(SUM(source_item.quantity) > 0, 1, 0)')
                ]
            )
            ->group('cpe.entity_id');

        return $connection->fetchAll($select);
    }

    /**
     * Collects reservation quantities for given products and stock ID.
     *
     * @param int $stockId
     * @return array<int, float> Keyed by product_id
     */
    private function collectMsiReservations(int $stockId): array
    {
        $reservationsTable = $this->resource->getTableName('inventory_reservation');

        if (!$this->tableExistsAndNotEmpty($reservationsTable)) {
            return [];
        }

        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(
                ['cpe' => $this->resource->getTableName('catalog_product_entity')],
                ['product_id' => 'entity_id']
            )->where(
                'cpe.entity_id IN (?)',
                $this->entityIds
            )->joinLeft(
                ['inv_res' => $reservationsTable],
                $connection->quoteInto('inv_res.sku = cpe.sku AND inv_res.stock_id = ?', $stockId),
                ['reserved' => 'SUM(COALESCE(inv_res.quantity, 0))']
            )->group('cpe.entity_id');

        return $connection->fetchPairs($select);
    }

    /**
     * Get stock qty for products without MSI
     * Structure of response
     * [product_id] => [
     *      qty
     *      is_in_stock
     *      reserved
     *      salable_qty
     *      manage_stock
     *      qty_increments
     *      min_sale_qty
     * ]
     *
     * @return array[]
     */
    private function getNoMsiStock(): array
    {
        $result = [];
        $select = $this->resource->getConnection()
            ->select()
            ->from(
                ['cataloginventory_stock_item' => $this->resource->getTableName('cataloginventory_stock_item')],
                [
                    'product_id',
                    'qty',
                    'is_in_stock',
                    'manage_stock',
                    'qty_increments',
                    'min_sale_qty'
                ]
            )->joinLeft(
                ['catalog_product_entity' => $this->resource->getTableName('catalog_product_entity')],
                "catalog_product_entity.entity_id = cataloginventory_stock_item.product_id",
                ['sku']
            )->joinLeft(
                ['css' => $this->resource->getTableName('cataloginventory_stock_status')],
                'css.product_id = catalog_product_entity.entity_id',
                ['stock_status']
            )->where(
                'cataloginventory_stock_item.product_id IN (?)',
                $this->entityIds
            )->group(
                'product_id'
            );
        $values = $this->resource->getConnection()->fetchAll($select);

        foreach ($values as $value) {
            $result[$value['product_id']] = [
                'qty' => (int)$value['qty'],
                'is_in_stock' => (int)$value['stock_status'],
                'availability' => (int)$value['stock_status'],
                'manage_stock' => (int)$value['manage_stock'],
                'qty_increments' => (int)$value['qty_increments'],
                'min_sale_qty' => (int)$value['min_sale_qty']
            ];
        }

        return $result;
    }
}
