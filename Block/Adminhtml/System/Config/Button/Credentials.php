<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Block\Adminhtml\System\Config\Button;

use Exception;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use TradeTracker\Connect\Api\Log\RepositoryInterface as LogRepository;

/**
 * Class Credentials
 *
 * Credentials validation
 */
class Credentials extends Field
{

    /**
     * @var string
     */
    protected $_template = 'TradeTracker_Connect::system/config/button/credentials.phtml';

    /**
     * @var RequestInterface
     */
    private $request;
    /**
     * @var LogRepository
     */
    private $logger;

    /**
     * Credentials constructor.
     *
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        Context $context,
        LogRepository $logger,
        array $data = []
    ) {
        $this->request = $context->getRequest();
        $this->logger = $logger;
        parent::__construct($context, $data);
    }

    /**
     * @param AbstractElement $element
     *
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * @param AbstractElement $element
     *
     * @return string
     */
    public function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    /**
     * @return string
     */
    public function getApiCheckUrl()
    {
        return $this->getUrl('tradetracker/credentials/check');
    }

    /**
     * @return mixed
     */
    public function getButtonHtml()
    {
        $buttonData = ['id' => 'tt-button_credentials', 'label' => __('Check Credentials')];
        try {
            $button = $this->getLayout()->createBlock(
                \Magento\Backend\Block\Widget\Button::class
            )->setData($buttonData);
            return $button->toHtml();
        } catch (Exception $e) {
            $this->logger->addErrorLog('LocalizedException', $e->getMessage());
            return false;
        }
    }
}
