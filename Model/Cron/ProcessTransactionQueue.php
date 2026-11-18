<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Model\Cron;

use TradeTracker\Connect\Api\Config\RepositoryInterface as ConfigRepository;
use TradeTracker\Connect\Service\Transaction\ProcessQueue;

/**
 * Cron job that sends the queued transaction assessments to TradeTracker.
 */
class ProcessTransactionQueue
{
    public function __construct(
        private readonly ConfigRepository $configRepository,
        private readonly ProcessQueue $processQueue
    ) {
    }

    public function execute(): void
    {
        if (!$this->configRepository->isEnabled()) {
            return;
        }
        $this->processQueue->execute();
    }
}
