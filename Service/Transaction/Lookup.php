<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Service\Transaction;

use TradeTracker\Connect\Api\Config\System\PixelInterface;
use TradeTracker\Connect\Exception\ApiException;
use TradeTracker\Connect\Service\Api\Adapter;

/**
 * The TradeTracker transaction that belongs to one order, by the order's increment id.
 *
 * Every lookup also writes what it found to the order's status column, so the order grid shows
 * the same thing the tab does.
 */
class Lookup
{
    public const NO_TRANSACTION = 'No transaction';

    public function __construct(
        private readonly Adapter $adapter,
        private readonly PixelInterface $config,
        private readonly StatusWriter $statusWriter
    ) {
    }

    /**
     * @return \stdClass|null the transaction as TradeTracker returns it, null when there is none
     * @throws ApiException
     */
    public function find(int $storeId, string $incrementId): ?\stdClass
    {
        $result = $this->adapter->call(
            $this->config->getApiCredentials($storeId),
            'getConversionTransactions',
            [$this->config->getCampaignId($storeId), ['Characteristics' => $incrementId]]
        );

        foreach ($this->asList($result) as $transaction) {
            if ((string)($transaction->characteristic ?? '') === $incrementId) {
                $this->statusWriter->write($incrementId, (string)($transaction->transactionStatus ?? ''));
                return $transaction;
            }
        }

        $this->statusWriter->write($incrementId, self::NO_TRANSACTION);
        return null;
    }

    /**
     * One transaction arrives as an object, several as an array of them.
     *
     * @param mixed $result
     * @return \stdClass[]
     */
    private function asList($result): array
    {
        if ($result instanceof \stdClass) {
            return [$result];
        }
        if (!is_array($result)) {
            return [];
        }

        return array_values(array_filter($result, static fn ($item) => $item instanceof \stdClass));
    }
}
