<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Block\Adminhtml\System\Config\Button;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Credentials extends Field
{
    protected $_template = 'TradeTracker_Connect::system/config/button/credentials.phtml';

    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    public function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    public function getApiCheckUrl(): string
    {
        return $this->getUrl('tradetracker/credentials/check');
    }
}
