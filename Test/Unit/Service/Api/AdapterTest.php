<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Service\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TradeTracker\Connect\Api\Config\System\AdvancedInterface;
use TradeTracker\Connect\Exception\ApiAuthenticationException;
use TradeTracker\Connect\Exception\ApiException;
use TradeTracker\Connect\Exception\ApiUnavailableException;
use TradeTracker\Connect\Service\Api\Adapter;
use TradeTracker\Connect\Service\Api\Client;
use TradeTracker\Connect\Service\Api\ClientFactory;
use TradeTracker\Connect\Service\Api\Wsdl;

/**
 * The Adapter authenticates once per set of credentials, hands every call the configured timeout
 * and the local WSDL, and turns a SOAP fault into an ApiException so no caller touches the client.
 */
#[CoversClass(Adapter::class)]
class AdapterTest extends TestCase
{
    private const CREDENTIALS = ['customer_id' => 42, 'passphrase' => 'secret', 'sandbox' => false, 'locale' => 'en_GB', 'demo' => false];

    private Client&MockObject $client;
    /** @var array<int, array<string, mixed>> what the factory was asked to build */
    private array $created = [];

    protected function setUp(): void
    {
        $this->client = $this->createMock(Client::class);
    }

    public function testACallAuthenticatesFirstAndPassesTheArgumentsOn(): void
    {
        $calls = [];
        $this->client->method('__soapCall')->willReturnCallback(function (string $method, array $arguments) use (&$calls) {
            $calls[] = [$method, $arguments];
            return $method === 'authenticate' ? true : ['answer'];
        });

        $result = $this->adapter()->call(self::CREDENTIALS, 'getConversionTransactions', [1234, ['Characteristics' => '100000001']]);

        self::assertSame(['answer'], $result);
        self::assertSame([
            ['authenticate', [42, 'secret', false, 'en_GB', false]],
            ['getConversionTransactions', [1234, ['Characteristics' => '100000001']]],
        ], $calls);
    }

    public function testTheClientIsBuiltWithTheTimeoutAndTheLocalWsdl(): void
    {
        $this->adapter(7)->authenticate(self::CREDENTIALS);

        self::assertCount(1, $this->created);
        self::assertSame(7, $this->created[0]['timeout']);
        self::assertSame('/var/tradetracker/merchant.wsdl', $this->created[0]['wsdl']);
        self::assertSame(7, $this->created[0]['options']['connection_timeout']);
        self::assertTrue($this->created[0]['options']['exceptions']);
    }

    public function testOneSetOfCredentialsAuthenticatesOncePerRequest(): void
    {
        $this->client->expects(self::exactly(3))->method('__soapCall')->willReturnCallback(
            static fn (string $method) => $method === 'authenticate' ? true : []
        );
        $adapter = $this->adapter();

        $adapter->call(self::CREDENTIALS, 'a');
        $adapter->call(self::CREDENTIALS, 'b');

        self::assertCount(1, $this->created);
    }

    public function testOtherCredentialsGetTheirOwnClient(): void
    {
        $this->client->method('__soapCall')->willReturn(true);
        $adapter = $this->adapter();

        $adapter->authenticate(self::CREDENTIALS);
        $adapter->authenticate(['passphrase' => 'other'] + self::CREDENTIALS);

        self::assertCount(2, $this->created);
    }

    public function testRefusedCredentialsAreAnAuthenticationFailureWhichTheQueueKeepsWaitingOn(): void
    {
        $this->client->method('__soapCall')->willThrowException(new \SoapFault('Client', 'Failed to authenticate.'));

        $this->expectException(ApiAuthenticationException::class);
        $this->expectExceptionMessage('authenticate: Failed to authenticate.');
        $this->adapter()->authenticate(self::CREDENTIALS);
    }

    public function testAFaultOnTheCallNamesTheMethod(): void
    {
        $this->client->method('__soapCall')->willReturnCallback(static function (string $method) {
            if ($method === 'authenticate') {
                return true;
            }
            throw new \SoapFault('Client', 'Transaction is not pending');
        });

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('assessConversionTransaction: Transaction is not pending');
        $this->adapter()->call(self::CREDENTIALS, 'assessConversionTransaction', [1, 'rejected', 'order_canceled']);
    }

    public function testNoAnswerFromTheTransportPassesThroughUntouched(): void
    {
        $this->client->method('__soapCall')->willThrowException(new ApiUnavailableException('No answer within 3 seconds'));

        $this->expectException(ApiUnavailableException::class);
        $this->adapter()->call(self::CREDENTIALS, 'getConversionTransactions', [1]);
    }

    private function adapter(int $timeout = 10): Adapter
    {
        $factory = $this->createMock(ClientFactory::class);
        $factory->method('create')->willReturnCallback(function (array $data) {
            $this->created[] = $data;
            return $this->client;
        });
        $wsdl = $this->createMock(Wsdl::class);
        $wsdl->method('path')->with($timeout)->willReturn('/var/tradetracker/merchant.wsdl');
        $config = $this->createMock(AdvancedInterface::class);
        $config->method('getApiTimeout')->willReturn($timeout);

        return new Adapter($factory, $wsdl, $config);
    }
}
