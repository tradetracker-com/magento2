<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Service\ProductData\AttributeCollector\Data;

/**
 * Collects Google's "related_product" data from Magento's own related-products links.
 *
 * Each entry names a relationship type, how the other product is identified and the identifier
 * itself. The identifier is the product id the feed exports as <g:id>, so a channel can resolve
 * the pointer against the same feed. Related products that are not in the feed are simply ignored
 * by the channel, which is why they are not filtered here: the collector sees one batch at a time
 * and cannot know what the rest of the run will contain.
 *
 * @see https://support.google.com/merchants/answer/17085370
 */
class RelatedProduct extends AbstractDataCollector
{
    /**
     * Magento's link type for "Related Products".
     */
    private const LINK_TYPE_RELATED = 1;

    /**
     * Google accepts no more than this many per product.
     */
    public const MAX_PER_PRODUCT = 30;

    /**
     * Related products per product id, in the position the merchant arranged them.
     *
     * @param array $entityIds Product ids in the current batch.
     * @param string $relationshipType The relationship every link is exported as.
     * @param string $identifierType How the linked product is identified.
     * @return array<int, array<int, array{relationship_type: string, identifier_type: string, identifier: string}>>
     */
    public function execute(
        array $entityIds = [],
        string $relationshipType = 'accessory',
        string $identifierType = 'id'
    ): array {
        if (empty($entityIds)) {
            return [];
        }

        $connection = $this->resource->getConnection();
        $select = $connection->select()->from(
            ['link' => $this->resource->getTableName('catalog_product_link')],
            ['product_id' => 'link.product_id', 'linked_product_id' => 'link.linked_product_id']
        )->joinLeft(
            ['position' => $this->resource->getTableName('catalog_product_link_attribute_int')],
            'position.link_id = link.link_id',
            ['position' => 'position.value']
        )->where('link.link_type_id = ?', self::LINK_TYPE_RELATED)
            ->where('link.product_id IN (?)', $entityIds)
            ->order(['link.product_id ASC', 'position ASC', 'link.linked_product_id ASC']);

        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $productId = (int)$row['product_id'];
            if (count($result[$productId] ?? []) >= self::MAX_PER_PRODUCT) {
                continue;
            }

            $result[$productId][] = [
                'relationship_type' => $relationshipType,
                'identifier_type' => $identifierType,
                'identifier' => (string)$row['linked_product_id'],
            ];
        }

        return $result;
    }
}
