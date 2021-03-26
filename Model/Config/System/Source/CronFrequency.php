<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace TradeTracker\Connect\Model\Config\System\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Cron Frequency Option Source model
 */
class CronFrequency implements OptionSourceInterface
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
                    'value' => null,
                    'label' => __('- Disabled')
                ],
                [
                    'value' => '0 0 * * *',
                    'label' => __('Daily at 0:00')
                ],
                [
                    'value' => '0 */6 * * *',
                    'label' => __('Every 6 hours')
                ],
                [
                    'value' => '0 */4 * * *',
                    'label' => __('Every 4 hours')
                ],
                [
                    'value' => '0 */2 * * *',
                    'label' => __('Every 2 hours')
                ],
                [
                    'value' => '0 * * * *',
                    'label' => __('Every hour')
                ],
                [
                    'value' => 'custom',
                    'label' => __('Custom')
                ],
            ];
        }
        return $this->options;
    }
}
