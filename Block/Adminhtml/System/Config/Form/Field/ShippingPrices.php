<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace TradeTracker\Connect\Block\Adminhtml\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;

/**
 * Represents a table for shipping prices in the admin configuration
 */
class ShippingPrices extends AbstractFieldArray
{

    /**
     * Render block
     */
    public function _prepareToRender()
    {
        $this->addColumn('price_from', [
            'label' => __('From'),
        ]);
        $this->addColumn('price_to', [
            'label' => __('To'),
        ]);
        $this->addColumn('price', [
            'label' => __('Price'),
        ]);
        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add');
    }
}
