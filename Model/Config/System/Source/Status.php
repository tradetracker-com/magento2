<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Model\Config\System\Source;

use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;

/**
 * Class Status
 *
 * Source class for status options
 *
 */
class Status extends AbstractSource
{

    /**
     * @return array
     */
    public function getAllOptions()
    {
        return [
            ['value' => 'No transaction', 'label' => __('No transaction')],
            ['value' => 'Pending', 'label' => __('Pending')],
            ['value' => 'Rejected', 'label' => __('Rejected')],
            ['value' => 'Accepted', 'label' => __('Accepted')]
        ];
    }
}
