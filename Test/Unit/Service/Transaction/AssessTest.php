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
use TradeTracker\Connect\Exception\ApiException;
use TradeTracker\Connect\Exception\ApiUnavailableException;
use TradeTracker\Connect\Service\Api\Adapter;
use TradeTracker\Connect\Service\Transaction\Assess;
use TradeTracker\Connect\Service\Transaction\Lookup;
use TradeTracker\Connect\Service\Transaction\StatusWriter;

/**
 * An assessment is sent in the shape the 1.9.0 observers sent it: a rejection with its reason, an
 * acceptance with the amount that stands. Only a pending transaction is assessed; the rest is
 * reported and left alone, so a queue row never fights TradeTracker over a decision already made.
 */
#[CoversClass(Assess::class)]
class AssessTest extends TestCase
{
    private const CREDENTIALS = ['customer_id' => 1, 'passphrase' => 'p', 'sandbox' => false, 'locale' => 'en_GB', 'demo' => false];

    private Adapter&MockObject $adapter;
    private Lookup&MockObject $lookup;
    private StatusWriter&MockObject $statusWriter;

    protected function setUp(): void
    {
        $this->adapter = $this->createMock(Adapter::class);
        $this->lookup = $this->createMock(Lookup::class);
        $this->statusWriter = $this->createMock(StatusWriter::class);
    }

    public function testARejectionCarriesItsReason(): void
    {
        $this->lookup->method('find')->with(3, '100000002')->willReturn($this->transaction('pending'));
        $this->adapter->expects(self::once())->method('call')
            ->with(self::CREDENTIALS, 'assessConversionTransaction', [99, 'rejected', 'order_canceled']);
        $this->statusWriter->expects(self::once())->method('write')->with('100000002', 'rejected');

        self::assertSame('Transaction 99 rejected', $this->assess()->execute(3, '100000002', Assess::REJECTED, 'order_canceled'));
    }

    public function testAnAcceptanceCarriesTheAmountThatStands(): void
    {
        $this->lookup->method('find')->willReturn($this->transaction('pending'));
        $this->adapter->expects(self::once())->method('call')
            ->with(self::CREDENTIALS, 'assessConversionTransaction', [99, 'accepted', false, 80.5]);
        $this->statusWriter->expects(self::once())->method('write')->with('100000002', 'accepted');

        self::assertSame('Transaction 99 accepted', $this->assess()->execute(3, '100000002', Assess::ACCEPTED, null, 80.5));
    }

    public function testATransactionThatIsNotPendingIsLeftAlone(): void
    {
        $this->lookup->method('find')->willReturn($this->transaction('accepted'));
        $this->adapter->expects(self::never())->method('call');
        $this->statusWriter->expects(self::never())->method('write');

        self::assertSame(
            'Transaction 99 is accepted, not pending; left as it is',
            $this->assess()->execute(3, '100000002', Assess::REJECTED, 'order_canceled')
        );
    }

    public function testNoTransactionIsReportedNotSent(): void
    {
        $this->lookup->method('find')->willReturn(null);
        $this->adapter->expects(self::never())->method('call');

        self::assertSame('No transaction found for order 100000002', $this->assess()->execute(3, '100000002', Assess::REJECTED, 'order_canceled'));
    }

    public function testALookupTradeTrackerRefusesIsNotAVerdictOnTheRow(): void
    {
        // A campaign the account may not use is the shop's configuration, like wrong credentials;
        // counted as refusals such rows were dropped after five cron runs.
        $this->lookup->method('find')->willThrowException(new ApiException('getConversionTransactions: Not permitted to use this campaign.'));
        $this->adapter->expects(self::never())->method('call');

        $this->expectException(ApiUnavailableException::class);
        $this->expectExceptionMessage('Not permitted to use this campaign.');
        $this->assess()->execute(3, '100000002', Assess::REJECTED, 'order_canceled');
    }

    public function testARefusedAssessmentIsTradeTrackersVerdict(): void
    {
        $this->lookup->method('find')->willReturn($this->transaction('pending'));
        $this->adapter->method('call')->willThrowException(new ApiException('assessConversionTransaction: Invalid reason'));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Invalid reason');
        $this->assess()->execute(3, '100000002', Assess::REJECTED, 'bogus');
    }

    private function transaction(string $status): \stdClass
    {
        return (object)['ID' => 99, 'characteristic' => '100000002', 'transactionStatus' => $status];
    }

    private function assess(): Assess
    {
        $config = $this->createMock(PixelInterface::class);
        $config->method('getApiCredentials')->with(3)->willReturn(self::CREDENTIALS);

        return new Assess($this->adapter, $this->lookup, $config, $this->statusWriter);
    }
}
