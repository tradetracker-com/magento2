<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace TradeTracker\Connect\Model\Config\System\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Product Visibility Option Source model
 */
class ProductVisibility implements OptionSourceInterface
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
                ['value' => '1', 'label' => __('Not Visible Individually')],
                ['value' => '2', 'label' => __('Catalog')],
                ['value' => '3', 'label' => __('Search')],
                ['value' => '4', 'label' => __('Catalog, Search')]
            ];
        }
        return $this->options;
    }
}
