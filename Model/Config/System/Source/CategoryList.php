<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace TradeTracker\Connect\Model\Config\System\Source;

use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Category List Option Source model
 */
class CategoryList implements OptionSourceInterface
{

    /**
     * Options array
     *
     * @var array
     */
    public $options = null;
    /**
     * @var CategoryFactory
     */
    private $categoryFactory;
    /**
     * @var CollectionFactory
     */
    private $categoryCollectionFactory;

    /**
     * CategoryList constructor.
     *
     * @param CategoryFactory $categoryFactory
     * @param CollectionFactory $categoryCollectionFactory
     */
    public function __construct(
        CategoryFactory $categoryFactory,
        CollectionFactory $categoryCollectionFactory
    ) {
        $this->categoryFactory = $categoryFactory;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        if (!$this->options) {
            foreach ($this->toArray() as $key => $value) {
                $this->options[] = [
                    'value' => $key,
                    'label' => $value
                ];
            }
        }
        return $this->options;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $categories = $this->getCategoryCollection();

        $categoryList = [];
        foreach ($categories as $category) {
            $categoryList[$category->getEntityId()] = [
                'name' => $category->getName(),
                'path' => $category->getPath()
            ];
        }

        $catagoryArray = [];
        foreach ($categoryList as $k => $v) {
            if ($path = $this->getCategoryPath($v['path'], $categoryList)) {
                $catagoryArray[$k] = $path;
            }
        }

        asort($catagoryArray);

        return $catagoryArray;
    }

    /**
     * @return \Magento\Catalog\Model\ResourceModel\Category\Collection
     */
    public function getCategoryCollection()
    {
        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect(['path', 'name']);

        return $collection;
    }

    /**
     * @param string $path
     * @param array $categoryList
     *
     * @return string
     */
    public function getCategoryPath($path, $categoryList)
    {
        $categoryPath = [];
        $rootCats = [1, 2];
        $path = explode('/', $path);

        foreach ($path as $catId) {
            if ($catId && !in_array($catId, $rootCats)) {
                if (!empty($categoryList[$catId]['name'])) {
                    $categoryPath[] = $categoryList[$catId]['name'];
                }
            }
        }

        if (!empty($categoryPath)) {
            return implode(' » ', $categoryPath);
        }

        return '';
    }
}
