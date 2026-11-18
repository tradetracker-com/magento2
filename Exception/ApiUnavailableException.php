<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Exception;

/**
 * TradeTracker did not answer: a timeout, a refused connection, a gateway page instead of XML. The
 * call can be repeated later, which is why the transaction queue leaves such a row pending.
 */
class ApiUnavailableException extends ApiException
{
}
