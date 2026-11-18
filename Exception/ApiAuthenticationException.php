<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Exception;

/**
 * TradeTracker refused the credentials. That is a configuration problem of the shop, not a verdict
 * on the call that was about to be made: once the credentials are right the call can go ahead, so
 * the transaction queue treats it like no answer and keeps its rows.
 */
class ApiAuthenticationException extends ApiUnavailableException
{
}
