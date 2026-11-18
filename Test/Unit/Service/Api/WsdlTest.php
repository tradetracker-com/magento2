<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Service\Api;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TradeTracker\Connect\Api\Log\RepositoryInterface as LogRepository;
use TradeTracker\Connect\Exception\ApiUnavailableException;
use TradeTracker\Connect\Service\Api\Response;
use TradeTracker\Connect\Service\Api\Transport;
use TradeTracker\Connect\Service\Api\Wsdl;

/**
 * The SOAP client reads the WSDL from a file in var/, never from ws.tradetracker.com directly: that
 * fetch would run on the SOAP client's own socket, outside the timeout, on every host that has
 * PHP's WSDL cache off. The file is refreshed once a day and kept when the refresh fails.
 */
#[CoversClass(Wsdl::class)]
class WsdlTest extends TestCase
{
    private const NOW = 1_700_000_000;

    private WriteInterface&MockObject $directory;
    private Transport&MockObject $transport;
    private LogRepository&MockObject $logger;

    protected function setUp(): void
    {
        $this->directory = $this->createMock(WriteInterface::class);
        $this->directory->method('getAbsolutePath')->with(Wsdl::FILE)->willReturn('/var/tradetracker/merchant.wsdl');
        $this->transport = $this->createMock(Transport::class);
        $this->logger = $this->createMock(LogRepository::class);
    }

    public function testAFreshCopyIsUsedWithoutAskingTradeTracker(): void
    {
        $this->copy(self::NOW - 100);
        $this->transport->expects(self::never())->method('get');
        $this->directory->expects(self::never())->method('writeFile');

        self::assertSame('/var/tradetracker/merchant.wsdl', $this->wsdl()->path(3));
    }

    public function testAStaleCopyIsRefreshedWithTheTimeout(): void
    {
        $this->copy(self::NOW - Wsdl::TTL - 1);
        $this->transport->expects(self::once())->method('get')->with(Wsdl::URL, 3)->willReturn(new Response('<wsdl:definitions/>'));
        $this->directory->expects(self::once())->method('writeFile')->with(Wsdl::FILE, '<wsdl:definitions/>');

        self::assertSame('/var/tradetracker/merchant.wsdl', $this->wsdl()->path(3));
    }

    public function testAFailedRefreshKeepsTheStaleCopy(): void
    {
        $this->copy(self::NOW - Wsdl::TTL - 1);
        $this->transport->method('get')->willThrowException(new ApiUnavailableException('timed out'));
        $this->directory->expects(self::never())->method('writeFile');
        $this->logger->expects(self::once())->method('addDebugLog')->with('Api', self::stringContains('timed out'));

        self::assertSame('/var/tradetracker/merchant.wsdl', $this->wsdl()->path(3));
    }

    public function testAnAnswerThatIsNotAWsdlIsNotCached(): void
    {
        $this->copy(self::NOW - Wsdl::TTL - 1);
        $this->transport->method('get')->willReturn(new Response('<html>maintenance</html>'));
        $this->directory->expects(self::never())->method('writeFile');

        self::assertSame('/var/tradetracker/merchant.wsdl', $this->wsdl()->path(3));
    }

    public function testNoCopyAndNoAnswerIsUnavailable(): void
    {
        $this->directory->method('isFile')->willReturn(false);
        $this->transport->method('get')->willThrowException(new ApiUnavailableException('timed out'));

        $this->expectException(ApiUnavailableException::class);
        $this->expectExceptionMessage('Could not fetch the TradeTracker WSDL: timed out');
        $this->wsdl()->path(3);
    }

    public function testTheFirstCopyIsFetchedAndWritten(): void
    {
        $this->directory->method('isFile')->willReturn(false);
        $this->transport->method('get')->willReturn(new Response('<definitions/>'));
        $this->directory->expects(self::once())->method('writeFile')->with(Wsdl::FILE, '<definitions/>');

        self::assertSame('/var/tradetracker/merchant.wsdl', $this->wsdl()->path(3));
    }

    private function copy(int $mtime): void
    {
        $this->directory->method('isFile')->with(Wsdl::FILE)->willReturn(true);
        $this->directory->method('stat')->with(Wsdl::FILE)->willReturn(['mtime' => $mtime]);
    }

    private function wsdl(): Wsdl
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('getDirectoryWrite')->with(DirectoryList::VAR_DIR)->willReturn($this->directory);
        $dateTime = $this->createMock(DateTime::class);
        $dateTime->method('gmtTimestamp')->willReturn(self::NOW);

        return new Wsdl($filesystem, $this->transport, $dateTime, $this->logger);
    }
}
