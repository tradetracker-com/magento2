<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Service\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TradeTracker\Connect\Service\Api\Client;
use TradeTracker\Connect\Service\Api\Response;
use TradeTracker\Connect\Service\Api\Transport;

/**
 * TradeTracker keeps the login in a session cookie. The first 1.10.0 build sent every SOAP request
 * through a fresh curl handle, so the cookie authenticate() set never came back and every later
 * call was answered with "Not yet authenticated". The client has to carry the cookies of one
 * answer into the next request, the way PHP's own SOAP transport does.
 */
#[CoversClass(Client::class)]
class ClientTest extends TestCase
{
    private const ENVELOPE = '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="urn:fixture">'
        . '<SOAP-ENV:Body>%s</SOAP-ENV:Body></SOAP-ENV:Envelope>';

    /** @var list<array{url: string, action: string, cookies: array<string, string>, timeout: int}> */
    private array $requests = [];

    public function testTheCookiesOfOneAnswerTravelWithTheNextRequest(): void
    {
        $client = $this->client([
            new Response(sprintf(self::ENVELOPE, '<ns1:authenticateResponse/>'), ['PHPSESSID' => 'abc123']),
            new Response(sprintf(self::ENVELOPE, '<ns1:pingResponse><return>pong</return></ns1:pingResponse>'), []),
        ]);

        $client->__soapCall('authenticate', [42, 'secret']);
        $answer = $client->__soapCall('ping', []);

        self::assertSame('pong', $answer);
        self::assertSame([], $this->requests[0]['cookies'], 'nothing to send before the login');
        self::assertSame(['PHPSESSID' => 'abc123'], $this->requests[1]['cookies'], 'the session travels with the next call');
        self::assertSame(['PHPSESSID' => 'abc123'], $client->cookies());
    }

    public function testEveryRequestGoesToTheWsdlLocationWithTheActionAndTheTimeout(): void
    {
        $client = $this->client([new Response(sprintf(self::ENVELOPE, '<ns1:authenticateResponse/>'))]);

        $client->__soapCall('authenticate', [42, 'secret']);

        self::assertSame('http://fixture.test/soap', $this->requests[0]['url']);
        self::assertSame('"urn:fixture#authenticate"', $this->requests[0]['action']);
        self::assertSame(7, $this->requests[0]['timeout']);
    }

    public function testAFaultInTheAnswerIsASoapFault(): void
    {
        $fault = '<SOAP-ENV:Fault><faultcode>Client</faultcode><faultstring>Failed to authenticate.</faultstring></SOAP-ENV:Fault>';
        $client = $this->client([new Response(sprintf(self::ENVELOPE, $fault))]);

        $this->expectException(\SoapFault::class);
        $this->expectExceptionMessage('Failed to authenticate.');
        $client->__soapCall('authenticate', [42, 'wrong']);
    }

    /**
     * @param Response[] $answers one per request, in order
     */
    private function client(array $answers): Client
    {
        $transport = $this->createMock(Transport::class);
        $transport->method('post')->willReturnCallback(
            function (string $url, string $body, array $headers, int $timeout, array $cookies) use (&$answers): Response {
                $this->requests[] = ['url' => $url, 'action' => $headers['SOAPAction'], 'cookies' => $cookies, 'timeout' => $timeout];
                self::assertStringContainsString('<SOAP-ENV:Envelope', $body);
                return array_shift($answers);
            }
        );

        return new Client($transport, 7, __DIR__ . '/fixtures/fixture.wsdl', ['exceptions' => true, 'cache_wsdl' => WSDL_CACHE_NONE]);
    }
}
