<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace TradeTracker\Connect\Setup;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Model\Entity\Attribute\Source\Boolean;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

/**
 * Install data script
 */
class InstallData implements InstallDataInterface
{

    const ATTRIBUTE_GROUP = 'TradeTracker';

    /** Category Attributes **/
    const CATEGORY_DISABLE_ATT = 'tradetracker_disable_export';
    const CATEGORY_PRODUCT_ID = 'tradetracker_product_id';
    const CATEGORY_CATERGORY = 'tradetracker_category';

    /** Product Attributes **/
    const PRODUCT_EXCLUDE = 'tradetracker_exclude';
    const PRODUCT_PRODUCT_ID = 'tradetracker_product_id';

    /**
     * @var EavSetupFactory
     */
    private $eavSetupFactory;

    /**
     * InstallData constructor.
     *
     * @param EavSetupFactory $eavSetupFactory
     */
    public function __construct(
        EavSetupFactory $eavSetupFactory
    ) {
        $this->eavSetupFactory = $eavSetupFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function install(
        ModuleDataSetupInterface $setup,
        ModuleContextInterface $context
    ) {
        $setup->startSetup();

        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);

        $this->createCategoryAttributes($eavSetup);
        $this->createProductAttributes($eavSetup);

        $setup->endSetup();
    }

    /**
     * @param EavSetup $eavSetup
     * @throws LocalizedException
     * @throws \Zend_Validate_Exception
     */
    private function createCategoryAttributes(EavSetup $eavSetup)
    {
        $eavSetup->addAttribute(
            Category::ENTITY,
            self::CATEGORY_DISABLE_ATT,
            [
                'type' => 'int',
                'label' => 'Disable Export',
                'input' => 'select',
                'source' => Boolean::class,
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'required' => false,
                'user_defined' => false,
                'default' => 0,
                'group' => self::ATTRIBUTE_GROUP,
                'sort_order' => 100,
            ]
        );

        $eavSetup->addAttribute(
            Category::ENTITY,
            self::CATEGORY_PRODUCT_ID,
            [
                'type' => 'varchar',
                'label' => 'Product Category (conversion)',
                'input' => 'text',
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'required' => false,
                'user_defined' => false,
                'group' => self::ATTRIBUTE_GROUP,
                'sort_order' => 101,
            ]
        );

        $eavSetup->addAttribute(
            Category::ENTITY,
            self::CATEGORY_CATERGORY,
            [
                'type' => 'varchar',
                'label' => 'Category (feed)',
                'input' => 'text',
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'required' => false,
                'user_defined' => false,
                'group' => self::ATTRIBUTE_GROUP,
                'sort_order' => 102,
            ]
        );
    }

    /**
     * @param EavSetup $eavSetup
     * @throws LocalizedException
     * @throws \Zend_Validate_Exception
     */
    private function createProductAttributes(EavSetup $eavSetup)
    {
        $attributeSetIds = $eavSetup->getAllAttributeSetIds(Product::ENTITY);
        foreach ($attributeSetIds as $attributeSetId) {
            $eavSetup->addAttributeGroup(Product::ENTITY, $attributeSetId, self::ATTRIBUTE_GROUP, 1001);
        }

        $eavSetup->addAttribute(
            Product::ENTITY,
            self::PRODUCT_EXCLUDE,
            [
                'group' => self::ATTRIBUTE_GROUP,
                'type' => 'int',
                'label' => 'Exclude for TradeTracker',
                'input' => 'boolean',
                'source' => Boolean::class,
                'global' => ScopedAttributeInterface::SCOPE_STORE,
                'default' => '0',
                'user_defined' => true,
                'required' => false,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => false,
                'unique' => false,
                'apply_to' => 'simple,configurable,virtual,bundle,downloadable'
            ]
        );

        $eavSetup->addAttribute(
            Product::ENTITY,
            self::PRODUCT_PRODUCT_ID,
            [
                'group' => self::ATTRIBUTE_GROUP,
                'type' => 'varchar',
                'label' => 'TradeTracker ProductID (conversion)',
                'input' => 'text',
                'source' => '',
                'global' => ScopedAttributeInterface::SCOPE_WEBSITE,
                'visible' => true,
                'required' => false,
                'user_defined' => true,
                'default' => '',
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => true,
                'unique' => false,
            ]
        );

        foreach ([self::PRODUCT_EXCLUDE, self::PRODUCT_PRODUCT_ID] as $attributeId) {
            $attribute = $eavSetup->getAttribute(Product::ENTITY, $attributeId);
            foreach ($attributeSetIds as $attributeSetId) {
                $eavSetup->addAttributeToGroup(
                    Product::ENTITY,
                    $attributeSetId,
                    self::ATTRIBUTE_GROUP,
                    $attribute['attribute_id'],
                    112
                );
            }
        }
    }
}
