<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace TradeTracker\Connect\Block\Adminhtml\System\Config\Form\Field\Renderer;

use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Element\Html\Select;
use TradeTracker\Connect\Model\Config\System\Source\Conditions as ConditionsSource;

/**
 * HTML select for Conditions
 */
class Conditions extends Select
{

    /**
     * @var ConditionsSource
     */
    private $conditions;

    /**
     * Conditions constructor.
     *
     * @param Context $context
     * @param ConditionsSource $conditions
     * @param array $data
     */
    public function __construct(
        Context $context,
        ConditionsSource $conditions,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->conditions = $conditions;
    }

    /**
     * @inheritDoc
     */
    public function _toHtml()
    {
        if (!$this->getOptions()) {
            foreach ($this->conditions->toOptionArray() as $condition) {
                $this->addOption($condition['value'], $condition['label']);
            }
        }

        return parent::_toHtml();
    }

    /**
     * Sets name for input element.
     *
     * @param string $value
     *
     * @return mixed
     */
    public function setInputName($value)
    {
        return $this->setData('name', $value);
    }
}
