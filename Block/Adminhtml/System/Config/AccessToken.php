<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use TradeTracker\Connect\Service\WebApi\Integration;

class AccessToken extends Field
{
    /**
     * @var Integration
     */
    private $integration;

    /**
     * @param Context $context
     * @param Integration $integration
     * @param array $data
     */
    public function __construct(
        Context $context,
        Integration $integration,
        array $data = []
    ) {
        $this->integration = $integration;
        parent::__construct($context, $data);
    }

    /**
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $element->setData('readonly', 1);
        $element->setData('value', $this->integration->getToken());
        return $element->getElementHtml();
    }
}
