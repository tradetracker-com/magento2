<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace TradeTracker\Connect\Model\Config\System\Source\Grouped;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Grouped Image Option Source model
 */
class Image implements OptionSourceInterface
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
                ['value' => '0', 'label' => __('No')],
                ['value' => '1', 'label' => __('Yes')],
                ['value' => '2', 'label' => __('Only if Empty (Recommended)')],
                ['value' => '3', 'label' => __('Combine, simple images first')],
                ['value' => '4', 'label' => __('Combine, parent images first')]
            ];
        }
        return $this->options;
    }
}
