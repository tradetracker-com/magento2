<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace TradeTracker\Connect\Model\Config\System\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Conditions Option Source model
 */
class Conditions implements OptionSourceInterface
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
                    'label' => __('')
                ],
                [
                    'value' => 'eq',
                    'label' => __('Equal')
                ],
                [
                    'value' => 'neq',
                    'label' => __('Not equal')
                ],
                [
                    'value' => 'gt',
                    'label' => __('Greater than')
                ],
                [
                    'value' => 'gteq',
                    'label' => __('Greater than or equal to')
                ],
                [
                    'value' => 'lt',
                    'label' => __('Less than')
                ],
                [
                    'value' => 'lteg',
                    'label' => __('Less than or equal to')
                ],
                [
                    'value' => 'in',
                    'label' => __('In')
                ],
                [
                    'value' => 'nin',
                    'label' => __('Not in')
                ],
                [
                    'value' => 'like',
                    'label' => __('Like')
                ],
                [
                    'value' => 'empty',
                    'label' => __('Empty')
                ],
                [
                    'value' => 'not-empty',
                    'label' => __('Not Empty')
                ],
            ];
        }
        return $this->options;
    }
}
