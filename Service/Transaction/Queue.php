<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Service\Transaction;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * Assessments waiting to be sent to TradeTracker, in tradetracker_transaction_queue.
 *
 * An order cancellation or a credit memo puts a row here from inside the save; the cron sends it.
 * A row is unique by what it asks for, so the many saves of a cancelled order add it once.
 */
class Queue
{
    public const TABLE = 'tradetracker_transaction_queue';
    public const STATUS_PENDING = 'pending';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';

    /** Refusals TradeTracker repeats before a row is given up on; an outage does not count */
    public const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly DateTime $dateTime
    ) {
    }

    /**
     * @return bool false when the same assessment is already queued or done
     */
    public function add(
        int $orderId,
        string $incrementId,
        int $storeId,
        string $action,
        ?string $reason,
        ?float $amount
    ): bool {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->table(), 'entity_id')
            ->where('order_id = ?', $orderId)
            ->where('action = ?', $action)
            ->where($reason === null ? 'reason IS NULL' : $connection->quoteInto('reason = ?', $reason))
            ->where($amount === null ? 'amount IS NULL' : $connection->quoteInto('amount = ?', $amount))
            ->limit(1);
        if ($connection->fetchOne($select)) {
            return false;
        }

        $connection->insert($this->table(), [
            'order_id' => $orderId,
            'increment_id' => $incrementId,
            'store_id' => $storeId,
            'action' => $action,
            'reason' => $reason,
            'amount' => $amount,
            'status' => self::STATUS_PENDING,
            'attempts' => 0,
            'created_at' => $this->dateTime->gmtDate(),
        ]);

        return true;
    }

    /**
     * The oldest pending rows.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pending(int $limit): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->table())
            ->where('status = ?', self::STATUS_PENDING)
            ->order('entity_id ASC')
            ->limit($limit);

        return $connection->fetchAll($select);
    }

    public function finish(int $entityId, string $message): void
    {
        $this->update($entityId, [
            'status' => self::STATUS_DONE,
            'message' => $message,
            'processed_at' => $this->dateTime->gmtDate(),
        ]);
    }

    /**
     * TradeTracker refused the call: count the attempt, give up after MAX_ATTEMPTS.
     *
     * @return bool true when the row is now failed for good
     */
    public function refused(int $entityId, int $attempts, string $message): bool
    {
        $failed = $attempts >= self::MAX_ATTEMPTS;
        $this->update($entityId, [
            'attempts' => $attempts,
            'message' => $message,
            'status' => $failed ? self::STATUS_FAILED : self::STATUS_PENDING,
            'processed_at' => $failed ? $this->dateTime->gmtDate() : null,
        ]);

        return $failed;
    }

    /**
     * TradeTracker did not answer: keep the row pending, note why, and try again next run.
     */
    public function postpone(int $entityId, string $message): void
    {
        $this->update($entityId, ['message' => $message]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function update(int $entityId, array $data): void
    {
        $this->resource->getConnection()->update($this->table(), $data, ['entity_id = ?' => $entityId]);
    }

    private function table(): string
    {
        return $this->resource->getTableName(self::TABLE);
    }
}
