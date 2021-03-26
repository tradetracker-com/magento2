<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace TradeTracker\Connect\Model\Config\System\Source\Grouped;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Grouped Option Source model
 */
class Option implements OptionSourceInterface
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
                ['value' => '', 'label' => __('No')],
                ['value' => 'parent', 'label' => __('Only Grouped Product (Recommended)')],
                ['value' => 'simple', 'label' => __('Only Linked Simple Products')],
                ['value' => 'both', 'label' => __('Grouped and Linked Simple Products')]
            ];
        }
        return $this->options;
    }
}
