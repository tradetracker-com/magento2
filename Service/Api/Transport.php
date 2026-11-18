<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Service\Api;

use Magento\Framework\HTTP\Client\CurlFactory;
use TradeTracker\Connect\Exception\ApiUnavailableException;

/**
 * HTTP transport of the merchant API: every request carries a connect timeout and a total timeout.
 *
 * PHP's SoapClient enforces neither its connection_timeout option nor a stream-context timeout once
 * the connection is open; a server that accepts and never answers holds the request for
 * default_socket_timeout, 60 seconds by default. curl caps the whole exchange with CURLOPT_TIMEOUT,
 * so the SOAP client hands its requests to this class instead of opening its own sockets.
 */
class Transport
{
    /** Seconds to wait for the TCP connection; the total timeout caps it when that is shorter. */
    private const CONNECT_TIMEOUT = 5;

    public function __construct(
        private readonly CurlFactory $curlFactory
    ) {
    }

    /**
     * POST a request body and return the response.
     *
     * A 4xx with an XML body is returned as it is: TradeTracker answers a SOAP fault with HTTP 400,
     * and the SOAP client turns that body into the fault the caller needs to see.
     *
     * @param array<string, string> $headers
     * @param array<string, string> $cookies the session TradeTracker handed out with authenticate()
     * @throws ApiUnavailableException when nothing usable came back within the timeout
     */
    public function post(string $url, string $body, array $headers, int $timeout, array $cookies = []): Response
    {
        return $this->request('POST', $url, $body, $headers, $timeout, $cookies);
    }

    /**
     * @throws ApiUnavailableException
     */
    public function get(string $url, int $timeout): Response
    {
        return $this->request('GET', $url, null, [], $timeout, []);
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, string> $cookies
     * @throws ApiUnavailableException
     */
    private function request(
        string $method,
        string $url,
        ?string $body,
        array $headers,
        int $timeout,
        array $cookies
    ): Response {
        $curl = $this->curlFactory->create();
        $curl->setTimeout($timeout);
        $curl->setCookies($cookies);
        $curl->setOptions([
            CURLOPT_CONNECTTIMEOUT => min(self::CONNECT_TIMEOUT, $timeout),
            // Accept every encoding curl knows and let it decompress; this replaces the gzip
            // compression the SOAP client used to negotiate itself.
            CURLOPT_ENCODING => '',
        ]);
        $curl->setHeaders($headers);

        try {
            if ($method === 'POST') {
                $curl->post($url, (string)$body);
            } else {
                $curl->get($url);
            }
        } catch (\Throwable $e) {
            throw new ApiUnavailableException(
                sprintf('No answer from %s within %d seconds: %s', $url, $timeout, $e->getMessage()),
                0,
                $e
            );
        }

        $response = (string)$curl->getBody();
        if ($curl->getStatus() >= 400 && !$this->isXml($response)) {
            throw new ApiUnavailableException(
                sprintf('%s answered HTTP %d without a SOAP document', $url, $curl->getStatus())
            );
        }

        return new Response($response, $curl->getCookies());
    }

    /**
     * A SOAP envelope or a WSDL; a proxy's HTML error page is neither.
     */
    private function isXml(string $response): bool
    {
        return str_contains($response, 'Envelope') || str_contains($response, 'definitions');
    }
}
