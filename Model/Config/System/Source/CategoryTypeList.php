<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace TradeTracker\Connect\Model\Config\System\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Category Type List Option Source model
 */
class CategoryTypeList implements OptionSourceInterface
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
                ['value' => 'in', 'label' => __('Include by Category')],
                ['value' => 'nin', 'label' => __('Exclude by Category')],
            ];
        }
        return $this->options;
    }
}
