<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Service\Transaction;

use Magento\Framework\App\ResourceConnection;

/**
 * The TradeTracker status column of an order, on the order and on the order grid.
 */
class StatusWriter
{
    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    public function write(string $incrementId, string $status): void
    {
        $connection = $this->resource->getConnection();
        foreach (['sales_order', 'sales_order_grid'] as $table) {
            $connection->update(
                $this->resource->getTableName($table),
                ['tt_status' => $status],
                ['increment_id = ?' => $incrementId]
            );
        }
    }
}
