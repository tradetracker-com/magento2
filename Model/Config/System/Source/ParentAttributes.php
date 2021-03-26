<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Model\Config\System\Source;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Data\OptionSourceInterface;
use TradeTracker\Connect\Api\Config\System\FeedInterface as FeedConfigRepository;
use TradeTracker\Connect\Api\ProductData\RepositoryInterface as ProductDataRepository;

/**
 * Atrributes Option Source model
 */
class ParentAttributes implements OptionSourceInterface
{

    /**
     * Options array
     *
     * @var array
     */
    public $options = [];
    /**
     * @var array
     */
    public $skipAttributes = [
        'entity_id',
        'sku',
        'visibility',
        'type_id',
        'url',
        'price',
        'image'
    ];

    /**
     * @var FeedConfigRepository
     */
    private $feedConfigRepository;
    /**
     * @var Http
     */
    private $request;
    /**
     * @var ProductDataRepository
     */
    private $productDataRepository;

    /**
     * ParentAttributes constructor.
     * @param Http $request
     * @param FeedConfigRepository $feedConfigRepository
     * @param ProductDataRepository $productDataRepository
     */
    public function __construct(
        Http $request,
        FeedConfigRepository $feedConfigRepository,
        ProductDataRepository $productDataRepository
    ) {
        $this->feedConfigRepository = $feedConfigRepository;
        $this->request = $request;
        $this->productDataRepository = $productDataRepository;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        if (!$this->options) {
            foreach ($this->getAttributes() as $key => $attribute) {
                if (!in_array($key, $this->skipAttributes)) {
                    $this->options[$attribute] = [
                        'value' => $attribute,
                        'label' => $attribute
                    ];
                }
            }

            array_multisort(
                array_column($this->options, 'value'),
                SORT_ASC,
                $this->options
            );
        }

        return $this->options;
    }

    /**
     * @return array
     */
    private function getAttributes(): array
    {
        $storeId = (int)$this->request->getParam('store', 0);
        return $this->productDataRepository->getProductAttributes($storeId);
    }
}
