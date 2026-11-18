<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Service\Api;

/**
 * What came back from one HTTP request: the body, and the cookies the server set with it.
 */
class Response
{
    /**
     * @param array<string, string> $cookies
     */
    public function __construct(
        public readonly string $body,
        public readonly array $cookies = []
    ) {
    }
}
