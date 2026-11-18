<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Exception;

/**
 * TradeTracker answered, and the answer is a refusal: wrong credentials, an unknown transaction, a
 * transaction that is no longer pending. Retrying the same call gives the same answer.
 */
class ApiException extends \RuntimeException
{
}
