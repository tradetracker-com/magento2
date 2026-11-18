<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Service\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TradeTracker\Connect\Api\Config\System\PixelInterface;
use TradeTracker\Connect\Service\Api\Adapter;
use TradeTracker\Connect\Service\Transaction\Lookup;
use TradeTracker\Connect\Service\Transaction\StatusWriter;

/**
 * One order, one transaction: found by the order's increment id among what TradeTracker returns
 * for it, and whatever is found lands in the order's status column.
 */
#[CoversClass(Lookup::class)]
class LookupTest extends TestCase
{
    private const CREDENTIALS = ['customer_id' => 1, 'passphrase' => 'p', 'sandbox' => false, 'locale' => 'en_GB', 'demo' => false];

    private Adapter&MockObject $adapter;
    private StatusWriter&MockObject $statusWriter;

    protected function setUp(): void
    {
        $this->adapter = $this->createMock(Adapter::class);
        $this->statusWriter = $this->createMock(StatusWriter::class);
    }

    public function testTheTransactionOfTheOrderIsReturnedAndItsStatusWritten(): void
    {
        $this->adapter->expects(self::once())->method('call')
            ->with(self::CREDENTIALS, 'getConversionTransactions', ['1234', ['Characteristics' => '100000002']])
            ->willReturn([$this->transaction('100000001', 'accepted'), $this->transaction('100000002', 'pending')]);
        $this->statusWriter->expects(self::once())->method('write')->with('100000002', 'pending');

        $found = $this->lookup()->find(3, '100000002');

        self::assertNotNull($found);
        self::assertSame('100000002', $found->characteristic);
    }

    public function testASingleTransactionArrivesAsAnObject(): void
    {
        $this->adapter->method('call')->willReturn($this->transaction('100000002', 'rejected'));
        $this->statusWriter->expects(self::once())->method('write')->with('100000002', 'rejected');

        self::assertNotNull($this->lookup()->find(3, '100000002'));
    }

    public function testNoTransactionWritesNoTransaction(): void
    {
        $this->adapter->method('call')->willReturn([$this->transaction('100000009', 'pending')]);
        $this->statusWriter->expects(self::once())->method('write')->with('100000002', Lookup::NO_TRANSACTION);

        self::assertNull($this->lookup()->find(3, '100000002'));
    }

    public function testAnEmptyAnswerIsNoTransaction(): void
    {
        $this->adapter->method('call')->willReturn(null);
        $this->statusWriter->expects(self::once())->method('write')->with('100000002', Lookup::NO_TRANSACTION);

        self::assertNull($this->lookup()->find(3, '100000002'));
    }

    private function transaction(string $incrementId, string $status): \stdClass
    {
        return (object)['ID' => 99, 'characteristic' => $incrementId, 'transactionStatus' => $status];
    }

    private function lookup(): Lookup
    {
        $config = $this->createMock(PixelInterface::class);
        $config->method('getApiCredentials')->with(3)->willReturn(self::CREDENTIALS);
        $config->method('getCampaignId')->with(3)->willReturn('1234');

        return new Lookup($this->adapter, $config, $this->statusWriter);
    }
}
