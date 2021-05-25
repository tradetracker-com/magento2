<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Service\Api;

use Google\Exception;
use Magento\Framework\Webapi\Soap\ClientFactory;
use SoapClient;

/**
 * Service class for API adapter
 */
class Adapter
{

    const WSDL = 'http://ws.tradetracker.com/soap/merchant?wsdl';

    const COMPRESSION = ['compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP];

    /**
     * @var ClientFactory
     */
    private $soap;

    /**
     * Adapter constructor.
     *
     * @param ClientFactory $soap
     */
    public function __construct(
        ClientFactory $soap
    ) {
        $this->soap = $soap;
    }

    /**
     * @param array $data
     * @return array
     */
    public function execute(array $data): array
    {
        $client = $this->soap->create(self::WSDL, self::COMPRESSION);
        try {
            $client->authenticate(
                $data['customer_id'],
                $data['passphrase'],
                $data['sandbox'],
                $data['locale'],
                $data['demo']
            );
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'client' => false
            ];
        }
        return [
            'success' => true,
            'error' => false,
            'client' => $client
        ];
    }
}
