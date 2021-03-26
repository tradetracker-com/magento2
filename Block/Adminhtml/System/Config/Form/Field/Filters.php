<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Block\Adminhtml\System\Config\Form\Field;

use Exception;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;
use Magento\Framework\View\Element\BlockInterface as ElementBlockInterface;
use TradeTracker\Connect\Api\Log\RepositoryInterface as LogRepository;

/**
 * Represents a table for collection filters in the admin configuration
 */
class Filters extends AbstractFieldArray
{

    const OPTION_PATTERN = 'option_%s';
    const SELECTED = 'selected="selected"';
    const RENDERERS = [
        'attribute' => Renderer\Attributes::class,
        'condition' => Renderer\Conditions::class,
        'product_type' => Renderer\ProductTypes::class,
    ];

    /**
     * @var array
     */
    private $renderers;
    /**
     * @var LogRepository
     */
    private $logger;

    /**
     * ExtraFields constructor.
     * @param Context $context
     * @param LogRepository $logger
     * @param array $data
     */
    public function __construct(
        Context $context,
        LogRepository $logger,
        array $data = []
    ) {
        $this->logger = $logger;
        parent::__construct($context, $data);
    }

    /**
     * @inheritDoc
     */
    public function _prepareToRender()
    {
        $this->addColumn('attribute', [
            'label' => (string)__('Attribute'),
            'class' => 'required-entry',
            'renderer' => $this->getRenderer('attribute')
        ]);
        $this->addColumn('condition', [
            'label' => (string)__('Condition'),
            'class' => 'required-entry',
            'renderer' => $this->getRenderer('condition')
        ]);
        $this->addColumn('value', [
            'label' => (string)__('Value'),
            'class' => 'required-entry'
        ]);
        $this->addColumn('product_type', [
            'label' => (string)__('Apply To'),
            'class' => 'required-entry',
            'renderer' => $this->getRenderer('product_type')
        ]);
        $this->_addAfter = false;
        $this->_addButtonLabel = (string)__('Add');
    }

    /**
     * Returns render according defined type.
     *
     * @param string $type
     * @return ElementBlockInterface
     */
    public function getRenderer(string $type)
    {
        if (!isset($this->renderers[$type])) {
            try {
                $this->renderers[$type] = $this->getLayout()->createBlock(
                    self::RENDERERS[$type],
                    '',
                    ['data' => ['is_render_to_js_template' => true]]
                );
            } catch (Exception $e) {
                $this->logger->addErrorLog('LocalizedException', $e->getMessage());
            }
        }
        return $this->renderers[$type];
    }

    /**
     * @inheritDoc
     */
    public function _prepareArrayRow(DataObject $row)
    {
        $options = [];
        foreach (array_keys(self::RENDERERS) as $element) {
            if ($elementData = $row->getData($element)) {
                $options[sprintf(
                    self::OPTION_PATTERN,
                    $this->getRenderer($element)->calcOptionHash($elementData)
                )] = self::SELECTED;
            }
        }
        $row->setData('option_extra_attrs', $options);
    }
}
