<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace TradeTracker\Connect\Block\Adminhtml\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * HTML Renderer for Module Heading in system config
 */
class Heading extends Field
{

    /**
     * Styles heading sperator
     *
     * @param AbstractElement $element
     *
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $html = '<tr id="row_' . $element->getHtmlId() . '">';
        $html .= '  <td class="label"></td>';
        $html .= '  <td class="value">';
        $html .= '     <div class="tradetracker-heading-block">' . $element->getData('label') . '</div>';
        $html .= '     <div class="tradetracker-heading-comment">' . $element->getData('comment') . '</div>';
        $html .= '  </td>';
        $html .= '  <td></td>';
        $html .= '</tr>';

        return $html;
    }
}
