<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Model\Config\System\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Product Types Option Source model
 */
class ProductTypes implements OptionSourceInterface
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
                [
                    'value' => '',
                    'label' => __('Simple & Parent Products')
                ],
                [
                    'value' => 'simple',
                    'label' => __('Only Simple Products')
                ],
                [
                    'value' => 'parent',
                    'label' => __('Only Parent Products')
                ]
            ];
        }
        return $this->options;
    }
}
