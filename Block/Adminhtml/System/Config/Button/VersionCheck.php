<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Block\Adminhtml\System\Config\Button;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use TradeTracker\Connect\Model\Config\Repository as ConfigRepositoryInterface;

class VersionCheck extends Field
{
    public function __construct(
        Context $context,
        private readonly ConfigRepositoryInterface $configRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        $element->setValue($this->configRepository->getExtensionVersion());
        return parent::render($element);
    }
}
