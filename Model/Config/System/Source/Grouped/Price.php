<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace TradeTracker\Connect\Model\Config\System\Source\Grouped;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Grouped Price Option Source model
 */
class Price implements OptionSourceInterface
{

    /**
     * Options array
     *
     * @var array
     */
    public $options = null;

    /**
     * @return array
     */
    public function toOptionArray()
    {
        if (!$this->options) {
            $this->options = [
                ['value' => '', 'label' => __('Minimum Price (Recommended)')],
                ['value' => 'max', 'label' => __('Maximum Price')],
                ['value' => 'total', 'label' => __('Total Price')]
            ];
        }
        return $this->options;
    }
}
