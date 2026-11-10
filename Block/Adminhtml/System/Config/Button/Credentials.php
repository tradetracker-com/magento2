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

    /**
     * AJAX url for the credentials check, carrying the scope currently selected in the configuration.
     *
     * @return string
     */
    public function getApiCheckUrl(): string
    {
        $params = [];
        foreach (['store', 'website'] as $scope) {
            if ($scopeId = (int)$this->getRequest()->getParam($scope)) {
                $params[$scope] = $scopeId;
            }
        }
        return $this->getUrl('tradetracker/credentials/check', $params);
    }
}
