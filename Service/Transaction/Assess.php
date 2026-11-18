<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Service\Transaction;

use TradeTracker\Connect\Api\Config\System\PixelInterface;
use TradeTracker\Connect\Exception\ApiException;
use TradeTracker\Connect\Exception\ApiUnavailableException;
use TradeTracker\Connect\Service\Api\Adapter;

/**
 * Assess an order's TradeTracker transaction: reject it, or accept it for an amount.
 *
 * Only a pending transaction can be assessed. The outcome is a sentence for the log and the queue;
 * an answer TradeTracker refuses, or no answer at all, is an exception for the caller to decide on.
 *
 * Only a refusal of the assessment itself is TradeTracker's verdict on the row. A lookup that fails
 * (wrong credentials, a campaign the account may not use) is the shop's configuration, so it is
 * reported as "unavailable" and the queue keeps the row until the configuration is right.
 */
class Assess
{
    public const REJECTED = 'rejected';
    public const ACCEPTED = 'accepted';

    public function __construct(
        private readonly Adapter $adapter,
        private readonly Lookup $lookup,
        private readonly PixelInterface $config,
        private readonly StatusWriter $statusWriter
    ) {
    }

    /**
     * @param string $action self::REJECTED or self::ACCEPTED
     * @param string|null $reason TradeTracker's rejection reason code, for a rejection
     * @param float|null $amount the order amount that stands, for an acceptance after a partial refund
     * @return string what happened
     * @throws ApiException
     */
    public function execute(
        int $storeId,
        string $incrementId,
        string $action,
        ?string $reason = null,
        ?float $amount = null
    ): string {
        try {
            $transaction = $this->lookup->find($storeId, $incrementId);
        } catch (ApiUnavailableException $e) {
            throw $e;
        } catch (ApiException $e) {
            throw new ApiUnavailableException($e->getMessage(), 0, $e);
        }
        if ($transaction === null) {
            return sprintf('No transaction found for order %s', $incrementId);
        }

        $status = (string)($transaction->transactionStatus ?? '');
        if ($status !== 'pending') {
            return sprintf(
                'Transaction %s is %s, not pending; left as it is',
                (string)($transaction->ID ?? '?'),
                $status
            );
        }

        $arguments = [$transaction->ID ?? 0, $action, $reason ?? false];
        if ($amount !== null) {
            $arguments[] = $amount;
        }
        $this->adapter->call($this->config->getApiCredentials($storeId), 'assessConversionTransaction', $arguments);
        $this->statusWriter->write($incrementId, $action);

        return sprintf('Transaction %s %s', (string)($transaction->ID ?? '?'), $action);
    }
}
