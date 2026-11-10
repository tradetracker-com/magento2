<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Service\ProductData\AttributeCollector\Data;

/**
 * Collects Google's "popularity_rank": how well a product sells against the rest of the catalogue,
 * as a percentage, computed from the orders of the last N days.
 *
 * Google defines the value as a rank "as a percentage of total inventory", so it is a percentile
 * over everything that sold, not a share of revenue: the best seller is 100 and a product that
 * outsells nothing is at the bottom. Products with no sales in the window carry no rank at all —
 * a zero would claim they were measured and found to sell nothing, which is a different statement
 * from a product that is too new to have a history.
 *
 * The percentile is a property of the whole catalogue, not of the batch being written, so the map
 * is built once per store on the first call and reused for the rest of the run.
 *
 * A variant inherits its parent's rank. An order for a configurable product is recorded against
 * the configurable, never against the simple that was chosen, while the feed usually exports the
 * simples; without the roll-down every variant in such a catalogue would go out unranked.
 *
 * @see https://support.google.com/merchants/answer/17085370
 */
class PopularityRank extends AbstractDataCollector
{
    /**
     * Percentile per product id, per store, for the lifetime of this run.
     *
     * @var array<int, array<int, float>>
     */
    private array $ranks = [];

    /**
     * The percentile of every product in the batch that sold at all in the window.
     *
     * @param array $entityIds Product ids in the current batch.
     * @param int $storeId Store view whose orders count.
     * @param int $days How far back the sales history reaches.
     * @return array<int, float>
     */
    public function execute(array $entityIds = [], int $storeId = 0, int $days = 90): array
    {
        if (empty($entityIds)) {
            return [];
        }

        $ranks = $this->ranksForStore($storeId, $days);
        if ($ranks === []) {
            return [];
        }

        $result = [];
        $unranked = [];
        foreach (array_map('intval', $entityIds) as $entityId) {
            if (isset($ranks[$entityId])) {
                $result[$entityId] = $ranks[$entityId];
                continue;
            }
            $unranked[] = $entityId;
        }

        foreach ($this->parentRanks($unranked, $ranks) as $entityId => $rank) {
            $result[$entityId] = $rank;
        }

        return $result;
    }

    /**
     * The rank a variant inherits from the product it is sold as.
     *
     * A simple that belongs to more than one parent takes the best of them: it is on offer as the
     * better-selling product too, and the rank describes the offer.
     *
     * @param array<int, int> $entityIds products without a rank of their own
     * @param array<int, float> $ranks
     * @return array<int, float>
     */
    private function parentRanks(array $entityIds, array $ranks): array
    {
        if ($entityIds === []) {
            return [];
        }

        $connection = $this->resource->getConnection();
        $select = $connection->select()->from(
            ['relation' => $this->resource->getTableName('catalog_product_relation')],
            ['child_id' => 'relation.child_id', 'parent_id' => 'relation.parent_id']
        )->where('relation.child_id IN (?)', $entityIds);

        $inherited = [];
        foreach ($connection->fetchAll($select) as $row) {
            $parentRank = $ranks[(int)$row['parent_id']] ?? null;
            if ($parentRank === null) {
                continue;
            }

            $childId = (int)$row['child_id'];
            $inherited[$childId] = max($parentRank, $inherited[$childId] ?? 0.0);
        }

        return $inherited;
    }

    /**
     * @param int $storeId
     * @param int $days
     * @return array<int, float>
     */
    private function ranksForStore(int $storeId, int $days): array
    {
        if (isset($this->ranks[$storeId])) {
            return $this->ranks[$storeId];
        }

        $this->ranks[$storeId] = $this->rank($this->soldQuantities($storeId, $days));

        return $this->ranks[$storeId];
    }

    /**
     * Quantity ordered per product over the window, highest first.
     *
     * Ordered items are counted on the parent they were bought as, which is the row a storefront
     * order carries; a configurable's child never appears on its own.
     *
     * @param int $storeId
     * @param int $days
     * @return array<int, float>
     */
    private function soldQuantities(int $storeId, int $days): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()->from(
            ['item' => $this->resource->getTableName('sales_order_item')],
            ['product_id' => 'item.product_id', 'sold' => 'SUM(item.qty_ordered)']
        )->where('item.store_id = ?', $storeId)
            ->where('item.parent_item_id IS NULL')
            ->where('item.created_at >= ?', $this->windowStart($days))
            ->group('item.product_id');

        $sold = [];
        foreach ($connection->fetchAll($select) as $row) {
            $quantity = (float)$row['sold'];
            if ($quantity > 0) {
                $sold[(int)$row['product_id']] = $quantity;
            }
        }

        return $sold;
    }

    /**
     * Turn quantities into percentiles: the share of the measured catalogue a product outsells,
     * rounded to one decimal the way Google's own example is written.
     *
     * Products that sold the same amount share a rank; nothing is ranked above the best seller.
     *
     * @param array<int, float> $sold
     * @return array<int, float>
     */
    private function rank(array $sold): array
    {
        $total = count($sold);
        if ($total === 0) {
            return [];
        }

        if ($total === 1) {
            return [array_key_first($sold) => 100.0];
        }

        arsort($sold);

        $ranks = [];
        $position = 0;
        $previousQuantity = null;
        $previousRank = 100.0;

        foreach ($sold as $productId => $quantity) {
            if ($quantity === $previousQuantity) {
                $ranks[$productId] = $previousRank;
                $position++;
                continue;
            }

            $outsold = $total - $position - 1;
            $previousRank = round(($outsold / ($total - 1)) * 100, 1);
            $previousQuantity = $quantity;
            $ranks[$productId] = $previousRank;
            $position++;
        }

        return $ranks;
    }

    /**
     * @param int $days
     * @return string
     */
    private function windowStart(int $days): string
    {
        return gmdate('Y-m-d H:i:s', strtotime(sprintf('-%d days', max(1, $days))) ?: time());
    }
}
