<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Block\Adminhtml\System\Config\Form\Field;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use TradeTracker\Connect\Api\Config\RepositoryInterface as ConfigRepository;

class Header extends Field
{
    protected $_template = 'TradeTracker_Connect::system/config/fieldset/header.phtml';

    public function __construct(
        Context $context,
        private readonly ConfigRepository $configRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function render(AbstractElement $element)
    {
        $element->addClass('magmodules');
        return $this->toHtml();
    }

    public function getSupportLink(): string
    {
        return $this->configRepository->getSupportLink();
    }

    public function getVersion(): string
    {
        return $this->configRepository->getExtensionVersion();
    }

    public function getLogoUrl(): string
    {
        return sprintf(
            'https://cdn.magmodules.eu/assets/%s/%s/logo.svg',
            ConfigRepository::EXTENSION_CODE,
            trim($this->configRepository->getExtensionVersion(), 'v')
        );
    }
}
