<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Block\Adminhtml\System\Config\Button;

use Exception;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Button;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use TradeTracker\Connect\Api\Log\RepositoryInterface as LogRepositoryInterface;
use TradeTracker\Connect\Model\Config\Repository as ConfigRepositoryInterface;

/**
 * Version check button class
 */
class VersionCheck extends Field
{

    /**
     * Template file name
     *
     * @var string
     */
    protected $_template = 'TradeTracker_Connect::system/config/button/version.phtml';

    /**
     * @var ConfigRepositoryInterface
     */
    private $configRepository;
    /**
     * @var RequestInterface
     */
    private $request;
    /**
     * @var LogRepositoryInterface
     */
    private $logger;

    /**
     * VersionCheck constructor.
     * @param Context $context
     * @param ConfigRepositoryInterface $configRepository
     * @param LogRepositoryInterface $logger
     * @param array $data
     */
    public function __construct(
        Context $context,
        ConfigRepositoryInterface $configRepository,
        LogRepositoryInterface $logger,
        array $data = []
    ) {
        $this->configRepository = $configRepository;
        $this->request = $context->getRequest();
        $this->logger = $logger;
        parent::__construct($context, $data);
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return $this->configRepository->getExtensionVersion();
    }

    /**
     * @inheritDoc
     */
    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * @inheritDoc
     */
    public function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    /**
     * @return string
     */
    public function getVersionCheckUrl(): string
    {
        return $this->getUrl('tradetracker/versioncheck/index');
    }

    /**
     * @return string
     */
    public function getChangeLogUrl(): string
    {
        return $this->getUrl('tradetracker/versioncheck/changelog');
    }

    /**
     * @return mixed
     */
    public function getButtonHtml()
    {
        $buttonData = ['id' => 'mm-tradetracker-button_version', 'label' => __('Check for latest versions')];
        try {
            $button = $this->getLayout()->createBlock(
                Button::class
            )->setData($buttonData);
            return $button->toHtml();
        } catch (Exception $e) {
            $this->logger->addErrorLog('LocalizedException', $e->getMessage());
            return false;
        }
    }
}
