<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Block\Adminhtml\System\Config\Button;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Log extends Field
{
    protected $_template = 'TradeTracker_Connect::system/config/button/log.phtml';

    public function render(AbstractElement $element): string
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    public function _getElementHtml(AbstractElement $element): string
    {
        return $this->_toHtml();
    }

    public function getDownloadUrl(string $type): string
    {
        return $this->getUrl('tradetracker/log/stream', ['type' => $type]);
    }
}
