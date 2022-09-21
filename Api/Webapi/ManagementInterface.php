<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Api\Webapi;

/**
 * Interface ManagementInterface
 */
interface ManagementInterface
{
    /**
     * GET module settings
     *
     * @return mixed[]
     */
    public function getModuleSettings(int $storeId): array;
}
