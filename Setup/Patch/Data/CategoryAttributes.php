<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace TradeTracker\Connect\Setup\Patch\Data;

use Magento\Catalog\Model\Category;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Model\Entity\Attribute\Source\Boolean;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;
use Zend_Validate_Exception;

/**
 * Class CategoryAttributes
 */
class CategoryAttributes implements DataPatchInterface, PatchRevertableInterface
{

    public const ATTRIBUTE_GROUP = 'TradeTracker';
    public const CATEGORY_DISABLE_ATT = 'tradetracker_disable_export';
    public const CATEGORY_PRODUCT_ID = 'tradetracker_product_id';
    public const CATEGORY_CATERGORY = 'tradetracker_category';

    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;
    /**
     * @var EavSetupFactory
     */
    private $eavSetupFactory;

    /**
     * CategoryAttributes constructor.
     * @param EavSetupFactory $eavSetupFactory
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        EavSetupFactory $eavSetupFactory,
        ModuleDataSetupInterface $moduleDataSetup
    ) {
        $this->eavSetupFactory = $eavSetupFactory;
        $this->moduleDataSetup = $moduleDataSetup;
    }

    /**
     * @return DataPatchInterface|void
     * @throws LocalizedException
     * @throws Zend_Validate_Exception
     */
    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        $this->addExcludeCategoryAttribute();
        $this->moduleDataSetup->getConnection()->endSetup();
    }

    /**
     * @throws LocalizedException
     * @throws Zend_Validate_Exception
     */
    public function addExcludeCategoryAttribute()
    {
        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
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
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function revert()
    {
        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $eavSetup->removeAttribute(Category::ENTITY, self::CATEGORY_CATERGORY);
        $eavSetup->removeAttribute(Category::ENTITY, self::CATEGORY_PRODUCT_ID);
        $eavSetup->removeAttribute(Category::ENTITY, self::CATEGORY_DISABLE_ATT);

        $entityTypeId = $eavSetup->getEntityTypeId('catalog_product');
        $attributeSetIds = $eavSetup->getAllAttributeSetIds($entityTypeId);

        foreach ($attributeSetIds as $attributeSetId) {
            $eavSetup->removeAttributeGroup($entityTypeId, $attributeSetId, 'TradeTracker');
        }

        $this->moduleDataSetup->getConnection()->delete(
            $this->moduleDataSetup->getTable('core_config_data'),
            ['path LIKE ?' => 'tradetracker/%']
        );

        $this->moduleDataSetup->endSetup();
    }
}
