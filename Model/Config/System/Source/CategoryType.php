<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Model\Config\System\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Category Type Option Source model
 */
class CategoryType implements OptionSourceInterface
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
                ['value' => 'include', 'label' => __('Include by Category')],
                ['value' => 'exclude', 'label' => __('Exclude by Category')]
            ];
        }
        return $this->options;
    }
}
