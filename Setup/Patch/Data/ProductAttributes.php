<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace TradeTracker\Connect\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Model\Entity\Attribute\Source\Boolean;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;

/**
 * Class ProductAttributes
 */
class ProductAttributes implements DataPatchInterface, PatchRevertableInterface
{
    public const ATTRIBUTE_GROUP = 'TradeTracker';
    public const PRODUCT_EXCLUDE = 'tradetracker_exclude';
    public const PRODUCT_PRODUCT_ID = 'tradetracker_product_id';

    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;
    /**
     * @var EavSetupFactory
     */
    private $eavSetupFactory;

    /**
     * ProductAttributes constructor.
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
     * @inheritDoc
     */
    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        $this->addProductAttribute();
        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    /**
     * @throws LocalizedException
     */
    public function addProductAttribute()
    {
        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

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

        $eavSetup->removeAttribute(Product::ENTITY, self::PRODUCT_PRODUCT_ID);
        $eavSetup->removeAttribute(Product::ENTITY, self::PRODUCT_EXCLUDE);

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
