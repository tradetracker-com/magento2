<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Logger;

use Magento\Framework\Serialize\Serializer\Json;
use Monolog\Logger as MonologLogger;

/**
 * Wrapper around Monolog\Logger to log error-level messages for module.
 * Automatically serializes array or object input using Magento's JSON serializer.
 *
 * Example usage:
 * $logger->addLog('API Error', ['message' => 'Invalid response', 'code' => 500]);
 */
class ErrorLogger
{
    private MonologLogger $logger;
    private Json $json;

    public function __construct(
        MonologLogger $logger,
        Json $json
    ) {
        $this->logger = $logger;
        $this->json = $json;
    }

    public function addLog(string $type, $data): void
    {
        if (is_array($data) || is_object($data)) {
            $this->logger->error( $type . ': ' . $this->json->serialize($data));
        } else {
            $this->logger->error( $type . ': ' . $data);
        }
    }
}
