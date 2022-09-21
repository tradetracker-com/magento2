<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace TradeTracker\Connect\Block\Adminhtml\System\Config\Form\Field\Renderer;

use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Element\Html\Select;
use TradeTracker\Connect\Model\Config\System\Source\ProductTypes as ProductTypesSource;

/**
 * HTML select for Product Types
 */
class ProductTypes extends Select
{

    /**
     * @var ProductTypesSource
     */
    private $source;

    /**
     * ProductTypes constructor.
     *
     * @param Context $context
     * @param ProductTypesSource $source
     * @param array $data
     */
    public function __construct(
        Context $context,
        ProductTypesSource $source,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->source = $source;
    }

    /**
     * @inheritDoc
     */
    public function _toHtml()
    {
        if (!$this->getOptions()) {
            foreach ($this->source->toOptionArray() as $type) {
                $this->addOption($type['value'], $type['label']);
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
