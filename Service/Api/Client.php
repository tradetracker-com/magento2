<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Service\Api;

/**
 * SoapClient whose HTTP requests go through the Transport, so every call has a timeout.
 *
 * TradeTracker keeps the login in a session cookie: authenticate() sets it and every later call has
 * to send it back, or the answer is "Not yet authenticated". PHP's own transport did that unasked;
 * this one keeps the cookies of every answer and sends them with the next request.
 *
 * Built through its generated factory: the Adapter passes the WSDL path, the timeout and the SOAP
 * options; the Transport comes from DI.
 */
class Client extends \SoapClient
{
    /** @var array<string, string> cookies TradeTracker set so far, sent with every next request */
    private array $cookies = [];

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly Transport $transport,
        private readonly int $timeout,
        string $wsdl,
        array $options = []
    ) {
        parent::__construct($wsdl, $options);
    }

    /**
     * The cookies this client sends with its next request.
     *
     * @return array<string, string>
     */
    public function cookies(): array
    {
        return $this->cookies;
    }

    /**
     * @inheritDoc
     *
     * PHP 8.5 added the $uriParserClass parameter; declaring it keeps the signature compatible on
     * every supported PHP version, and this transport has no use for it.
     */
    public function __doRequest(
        string $request,
        string $location,
        string $action,
        int $version,
        bool $oneWay = false,
        ?string $uriParserClass = null
    ): ?string {
        $headers = [
            'Content-Type' => $version === SOAP_1_2
                ? 'application/soap+xml; charset=utf-8'
                : 'text/xml; charset=utf-8',
            'SOAPAction' => '"' . $action . '"',
        ];
        $response = $this->transport->post($location, $request, $headers, $this->timeout, $this->cookies);
        $this->cookies = array_merge($this->cookies, $response->cookies);

        return $oneWay ? null : $response->body;
    }
}
