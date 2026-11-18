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
 * Built through its generated factory: the Adapter passes the WSDL path, the timeout and the SOAP
 * options; the Transport comes from DI.
 */
class Client extends \SoapClient
{
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
        $response = $this->transport->post($location, $request, $headers, $this->timeout);

        return $oneWay ? null : $response;
    }
}
