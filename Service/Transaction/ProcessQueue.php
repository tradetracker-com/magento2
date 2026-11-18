<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Service\Transaction;

use TradeTracker\Connect\Api\Log\RepositoryInterface as LogRepository;
use TradeTracker\Connect\Exception\ApiException;
use TradeTracker\Connect\Exception\ApiUnavailableException;

/**
 * Send the queued assessments to TradeTracker; the cron job and the CLI command both run this.
 *
 * A refusal counts against the row; no answer at all ends the run, because the rows behind it would
 * each wait out the same timeout for the same silence.
 */
class ProcessQueue
{
    public const BATCH_SIZE = 50;

    public function __construct(
        private readonly Queue $queue,
        private readonly Assess $assess,
        private readonly LogRepository $logger
    ) {
    }

    /**
     * @return array{processed: int, done: int, refused: int, failed: int, postponed: int}
     */
    public function execute(): array
    {
        $result = ['processed' => 0, 'done' => 0, 'refused' => 0, 'failed' => 0, 'postponed' => 0];

        foreach ($this->queue->pending(self::BATCH_SIZE) as $row) {
            $entityId = (int)$row['entity_id'];
            $label = sprintf('#%d order %s %s', $entityId, (string)$row['increment_id'], (string)$row['action']);
            $result['processed']++;
            try {
                $message = $this->assess->execute(
                    (int)$row['store_id'],
                    (string)$row['increment_id'],
                    (string)$row['action'],
                    $row['reason'] === null ? null : (string)$row['reason'],
                    $row['amount'] === null ? null : (float)$row['amount']
                );
                $this->queue->finish($entityId, $message);
                $this->logger->addDebugLog('Transaction queue', $label . ': ' . $message);
                $result['done']++;
            } catch (ApiUnavailableException $e) {
                $this->queue->postpone($entityId, $e->getMessage());
                $this->logger->addDebugLog('Transaction queue', $label . ' postponed: ' . $e->getMessage());
                $result['postponed']++;
                break;
            } catch (ApiException $e) {
                $attempts = (int)$row['attempts'] + 1;
                if ($this->queue->refused($entityId, $attempts, $e->getMessage())) {
                    $this->logger->addErrorLog('Transaction queue', $label . ' failed for good: ' . $e->getMessage());
                    $result['failed']++;
                } else {
                    $this->logger->addDebugLog(
                        'Transaction queue',
                        sprintf('%s refused (attempt %d): %s', $label, $attempts, $e->getMessage())
                    );
                    $result['refused']++;
                }
            }
        }

        return $result;
    }
}
