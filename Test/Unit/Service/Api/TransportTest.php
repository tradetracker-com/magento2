<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Service\Api;

use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\Client\CurlFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TradeTracker\Connect\Exception\ApiUnavailableException;
use TradeTracker\Connect\Service\Api\Response;
use TradeTracker\Connect\Service\Api\Transport;

/**
 * Every request to TradeTracker is capped by the configured timeout, in curl. A merchant's admin
 * stood still for up to 60 seconds per order page while ws.tradetracker.com was slow, because the
 * SOAP client's own connection_timeout does not cover the wait for the answer.
 */
#[CoversClass(Transport::class)]
class TransportTest extends TestCase
{
    private const FAULT = '<?xml version="1.0"?><SOAP-ENV:Envelope><SOAP-ENV:Body><SOAP-ENV:Fault>'
        . '<faultstring>Failed to authenticate.</faultstring></SOAP-ENV:Fault></SOAP-ENV:Body></SOAP-ENV:Envelope>';

    public function testTheTimeoutCapsTheWholeRequestAndTheConnect(): void
    {
        $curl = $this->curl(200, '<Envelope/>');
        $curl->expects(self::once())->method('setTimeout')->with(3);
        $curl->expects(self::once())->method('setOptions')->with(self::callback(
            static fn (array $options) => $options[CURLOPT_CONNECTTIMEOUT] === 3 && $options[CURLOPT_ENCODING] === ''
        ));
        $curl->expects(self::once())->method('setHeaders')->with(['SOAPAction' => '"x"']);
        $curl->expects(self::once())->method('post')->with('http://ws/', '<req/>');

        self::assertSame('<Envelope/>', $this->transport($curl)->post('http://ws/', '<req/>', ['SOAPAction' => '"x"'], 3)->body);
    }

    public function testCookiesGoOutWithTheRequestAndComeBackFromTheAnswer(): void
    {
        $curl = $this->curl(200, '<Envelope/>', ['PHPSESSID' => 'abc123', 'other' => 'x']);
        $curl->expects(self::once())->method('setCookies')->with(['PHPSESSID' => 'abc123']);

        $response = $this->transport($curl)->post('http://ws/', '<req/>', [], 3, ['PHPSESSID' => 'abc123']);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(['PHPSESSID' => 'abc123', 'other' => 'x'], $response->cookies);
    }

    public function testTheConnectTimeoutStaysAtFiveSecondsForALongerTotal(): void
    {
        $curl = $this->curl(200, '<Envelope/>');
        $curl->expects(self::once())->method('setOptions')->with(self::callback(
            static fn (array $options) => $options[CURLOPT_CONNECTTIMEOUT] === 5
        ));

        $this->transport($curl)->post('http://ws/', '', [], 30);
    }

    public function testNoAnswerWithinTheTimeoutIsUnavailable(): void
    {
        $curl = $this->curl(0, '');
        $curl->method('post')->willThrowException(new \Exception('Operation timed out after 3001 milliseconds'));

        $this->expectException(ApiUnavailableException::class);
        $this->expectExceptionMessage('within 3 seconds: Operation timed out');
        $this->transport($curl)->post('http://ws/', '', [], 3);
    }

    public function testASoapFaultOnHttp400IsHandedToTheSoapClient(): void
    {
        self::assertSame(self::FAULT, $this->transport($this->curl(400, self::FAULT))->post('http://ws/', '', [], 3)->body);
    }

    public function testAGatewayPageInsteadOfXmlIsUnavailable(): void
    {
        $this->expectException(ApiUnavailableException::class);
        $this->expectExceptionMessage('HTTP 502 without a SOAP document');
        $this->transport($this->curl(502, '<html><body>Bad Gateway</body></html>'))->post('http://ws/', '', [], 3);
    }

    public function testGetFetchesTheWsdl(): void
    {
        $curl = $this->curl(200, '<definitions/>');
        $curl->expects(self::once())->method('get')->with('http://ws/?wsdl');
        $curl->expects(self::never())->method('post');

        self::assertSame('<definitions/>', $this->transport($curl)->get('http://ws/?wsdl', 3)->body);
    }

    /**
     * @param array<string, string> $cookies what the answer sets
     */
    private function curl(int $status, string $body, array $cookies = []): Curl&\PHPUnit\Framework\MockObject\MockObject
    {
        $curl = $this->createMock(Curl::class);
        $curl->method('getStatus')->willReturn($status);
        $curl->method('getBody')->willReturn($body);
        $curl->method('getCookies')->willReturn($cookies);

        return $curl;
    }

    private function transport(Curl $curl): Transport
    {
        $factory = $this->createMock(CurlFactory::class);
        $factory->method('create')->willReturn($curl);

        return new Transport($factory);
    }
}
