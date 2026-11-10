<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Service\ProductData\AttributeCollector\Data;

class Rating extends AbstractDataCollector
{

    public function execute(array $entityIds = [], int $storeId = 0): array
    {
        $aggregateRatings = [];
        foreach ($this->getAggregateRatings($entityIds, $storeId) as $rating) {
            $aggregateRatings[$rating['product_id']] = [
                'rating' => (float)$rating['rating'],
                'review_count' => (int)$rating['review_count'],
            ];
        }

        return $aggregateRatings;
    }

    public function getAggregateRatings(array $entityIds, int $storeId = 0): array
    {
        $table = $this->resource->getTableName('rating_option_vote_aggregated');
        $ratings = $this->resource->getConnection()->select()
            ->from($table, [
                'rating' => 'percent_approved',
                'product_id' => 'entity_pk_value',
                'review_count' => 'vote_count'
            ])
            ->where("entity_pk_value IN (?)", $entityIds)
            ->where("store_id = ?", $storeId);

        return $this->resource->getConnection()->fetchAll($ratings);
    }
}
