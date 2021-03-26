<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace TradeTracker\Connect\Model\Config\System\Source;

use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Catalog\Model\Product\Attribute\Repository;
use Magento\Framework\Api\SearchCriteriaBuilder;

/**
 * Main Image Option Source model
 */
class MainImage implements OptionSourceInterface
{

    /**
     * Options array
     *
     * @var array
     */
    public $options = null;
    /**
     * @var Repository
     */
    private $attributeRepository;
    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * Attributes constructor.
     *
     * @param Repository $attributeRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        Repository $attributeRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->attributeRepository = $attributeRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        if (!$this->options) {
            $options[] = $this->getPositionSource();
            $options[] = $this->getMediaImageTypesSource();
            $this->options = $options;
        }

        return $this->options;
    }

    /**
     * @return array
     */
    public function getPositionSource()
    {
        $imageSource = [];
        $imageSource[] = ['value' => '', 'label' => __('First Image (default)')];
        $imageSource[] = ['value' => 'last', 'label' => __('Last Image')];
        return ['label' => __('By position'), 'value' => $imageSource, 'optgroup-name' => __('position')];
    }

    /**
     * @return array
     */
    public function getMediaImageTypesSource()
    {
        $imageSource = [];
        $searchCriteria = $this->searchCriteriaBuilder->addFilter('frontend_input', 'media_image')->create();
        /** @var AbstractAttribute $attribute */
        foreach ($this->attributeRepository->getList($searchCriteria)->getItems() as $attribute) {
            if ($attribute->getIsVisible()) {
                $imageSource[] = [
                    'value' => $attribute->getAttributeCode(),
                    'label' => $this->getLabel($attribute)
                ];
            }
        }
        usort($imageSource, function ($a, $b) {
            return strcmp($a["label"], $b["label"]);
        });

        return ['label' => __('Media Image Types'), 'value' => $imageSource, 'optgroup-name' => __('image-types')];
    }

    /**
     * @param AbstractAttribute $attribute
     *
     * @return mixed
     */
    public function getLabel($attribute)
    {
        return str_replace("'", '', $attribute->getFrontendLabel());
    }
}
