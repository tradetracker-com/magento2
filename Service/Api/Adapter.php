<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Service\Api;

use TradeTracker\Connect\Api\Config\System\AdvancedInterface;
use TradeTracker\Connect\Exception\ApiException;

/**
 * The one way into the merchant API: authenticated, with the configured timeout on every request.
 *
 * Callers never see the SOAP client. An answer from TradeTracker that is a refusal arrives as an
 * ApiException; no answer at all as an ApiUnavailableException, straight from the Transport.
 */
class Adapter
{
    /** @var array<string, Client> one authenticated client per set of credentials, per request */
    private array $clients = [];

    public function __construct(
        private readonly ClientFactory $clientFactory,
        private readonly Wsdl $wsdl,
        private readonly AdvancedInterface $config
    ) {
    }

    /**
     * Call one method of the merchant API.
     *
     * @param array<string, mixed> $credentials as the configuration hands them out (customer_id, passphrase, ...)
     * @param array<int, mixed> $arguments positional, as the WSDL declares them
     * @return mixed what TradeTracker returned
     * @throws ApiException
     */
    public function call(array $credentials, string $method, array $arguments = [])
    {
        $client = $this->client($credentials);
        try {
            return $client->__soapCall($method, $arguments);
        } catch (\SoapFault $e) {
            throw new ApiException(sprintf('%s: %s', $method, $e->getMessage()), 0, $e);
        }
    }

    /**
     * Authenticate and nothing else; the Check Credentials button.
     *
     * @param array<string, mixed> $credentials as the configuration hands them out (customer_id, passphrase, ...)
     * @throws ApiException
     */
    public function authenticate(array $credentials): void
    {
        $this->client($credentials);
    }

    /**
     * @param array<string, mixed> $credentials as the configuration hands them out (customer_id, passphrase, ...)
     * @throws ApiException
     */
    private function client(array $credentials): Client
    {
        $key = implode('|', [
            $credentials['customer_id'],
            $credentials['passphrase'],
            (int)$credentials['sandbox'],
            $credentials['locale'],
            (int)$credentials['demo'],
        ]);
        if (isset($this->clients[$key])) {
            return $this->clients[$key];
        }

        $timeout = $this->config->getApiTimeout();
        try {
            $client = $this->clientFactory->create([
                'timeout' => $timeout,
                'wsdl' => $this->wsdl->path($timeout),
                'options' => [
                    'exceptions' => true,
                    'cache_wsdl' => WSDL_CACHE_BOTH,
                    'connection_timeout' => $timeout,
                ],
            ]);
            $client->__soapCall('authenticate', [
                $credentials['customer_id'],
                $credentials['passphrase'],
                $credentials['sandbox'],
                $credentials['locale'],
                $credentials['demo'],
            ]);
        } catch (\SoapFault $e) {
            throw new ApiException('authenticate: ' . $e->getMessage(), 0, $e);
        }

        return $this->clients[$key] = $client;
    }
}
