<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace TradeTracker\Connect\Block\Adminhtml\System\Config\Form\Field;

use Exception;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;
use Magento\Framework\View\Element\BlockInterface as ElementBlockInterface;
use TradeTracker\Connect\Api\Log\RepositoryInterface as LogRepository;

/**
 * Represents a table for extra product fields in the admin configuration
 */
class ExtraFields extends AbstractFieldArray
{

    const OPTION_PATTERN = 'option_%s';
    const SELECTED = 'selected="selected"';
    const RENDERERS = [
        'attribute' => Renderer\Attributes::class,
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
        $this->addColumn('name', [
            'label' => (string)__('Fieldname'),
            'class' => 'required-entry',
        ]);
        $this->addColumn('attribute', [
            'label' => (string)__('Attribute'),
            'class' => 'required-entry',
            'renderer' => $this->getRenderer('attribute')
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
