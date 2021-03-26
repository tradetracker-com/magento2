<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace TradeTracker\Connect\Model\Config\System\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Category Source Option Source model
 */
class CategorySource implements OptionSourceInterface
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
                ['value' => '', 'label' => __('Magento Category Tree')],
                ['value' => 'custom', 'label' => __('Custom Category Value')],
                ['value' => 'attribute', 'label' => __('Use Attribute')],
            ];
        }
        return $this->options;
    }
}
