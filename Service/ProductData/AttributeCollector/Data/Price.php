<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Service\ProductData\AttributeCollector\Data;

use Exception;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\CatalogPrice;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogRule\Model\ResourceModel\RuleFactory;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;

/**
 * Service class for price data
 */
class Price
{

    public const REQUIRE = [
        'products',
        'grouped_price_type',
        'bundle_price_type',
        'configurable_price_type'
    ];

    /**
     * The price columns come out of the index as numeric strings as often as floats; they are cast
     * where they are read, so everything downstream can count on a float.
     */
    private ?float $price = null;

    private ?float $finalPrice = null;

    private ?float $specialPrice = null;

    private ?float $salesPrice = null;

    /** @var float|false|null getRulePrice() answers false when no catalog rule applies */
    private $rulePrice = null;

    private ?float $minPrice = null;

    private ?float $maxPrice = null;

    private ?float $totalPrice = null;

    private ?int $websiteId = null;

    private ?string $currecy = null;

    /** @var array<int|string, float> */
    private array $taxClasses = [];

    private ?string $bundlePriceType = null;

    private ?string $groupedPriceType = null;

    private ?string $configurablePriceType = null;

    private ?Collection $products = null;

    public function __construct(
        private readonly CatalogPrice $commonPriceModel,
        private readonly RuleFactory $resourceRuleFactory,
        private readonly CatalogHelper $catalogHelper,
        private readonly TimezoneInterface $localeDate,
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * @param array $productIds
     * @param StoreInterface $store
     * @param string $groupedPriceType options: min, max, total
     * @param string $bundlePriceType options: min, max, total
     * @param string $configurablePriceType options: min, max
     * @return array
     */
    public function execute(
        array $productIds,
        StoreInterface $store,
        string $groupedPriceType = '',
        string $bundlePriceType = '',
        string $configurablePriceType = ''
    ): array {
        $this->currecy = $store->getDefaultCurrencyCode();
        $this->websiteId = (int)$store->getWebsiteId();
        $this->taxClasses = [];
        $this->products = $this->getProductData($productIds, (int)$store->getId());
        $this->groupedPriceType = $groupedPriceType;
        $this->bundlePriceType = $bundlePriceType;
        $this->configurablePriceType = $configurablePriceType;
        // Fallback to 1 when no exchange rate is set, otherwise every price becomes 0.
        $rate = $store->getBaseCurrency()->getRate($this->currecy) ?: 1;

        foreach ($this->products as $product) {
            // Every price lives in a property of this class, and the per-type setters return early
            // when a product has no price to resolve (an unsalable configurable, for one). Without
            // this reset those properties still hold the previous product's figures, so such a
            // product was published with the price of whichever product came before it in the batch.
            $this->resetPrices();
            $this->setPrices($product, $this->groupedPriceType, $this->bundlePriceType);
            $percent = $this->getPercentage($product, $store);

            $result[$product->getId()] = [
                'price' => $this->round($percent * $this->price),
                'price_ex' => $this->round($this->price * $rate),
                'final_price' => $this->round($this->finalPrice ? $percent * $this->finalPrice : null),
                'final_price_ex' => $this->round($this->finalPrice * $rate),
                'sales_price' => $this->round($this->salesPrice ? $percent * $this->salesPrice : null),
                'min_price' => $this->round($this->minPrice ? $percent * $this->minPrice : null),
                'max_price' => $this->round($this->maxPrice ? $percent * $this->maxPrice : null),
                'special_price' => $this->round($this->specialPrice ? $percent * $this->specialPrice : null),
                'total_price' => $this->round($this->totalPrice ? $percent * $this->totalPrice : null),
                'sales_date_range' => $this->getSpecialPriceDateRang($product),
                'discount_perc' => $this->getDiscountPercentage(),
                'tax' => abs(1 - ($percent / $rate)) * 100,
                'currency' => $this->currecy
            ];
        }

        return $result ?? [];
    }

    /**
     * Clear the per-product price state, so nothing carries over from the previous product.
     */
    private function resetPrices(): void
    {
        $this->price = null;
        $this->finalPrice = null;
        $this->specialPrice = null;
        $this->salesPrice = null;
        $this->rulePrice = null;
        $this->minPrice = null;
        $this->maxPrice = null;
        $this->totalPrice = null;
    }

    /**
     * @param array $productIds
     * @param int $storeId
     * @return Collection
     */
    private function getProductData(array $productIds = [], int $storeId = 0)
    {
        // Step by step rather than chained: the collection's own methods are declared to return
        // the abstract parent, which does not know addStoreFilter().
        $products = $this->collectionFactory->create();
        $products->addFieldToSelect(['price', 'special_price', 'tax_class_id', 'special_from_date', 'special_to_date']);
        $products->addFieldToFilter('entity_id', ['in' => $productIds]);
        $products->addStoreFilter($storeId);
        $products->setStoreId($storeId);

        // Join the price attributes
        $products->getSelect()->joinLeft(
            ['price_index' => $products->getTable('catalog_product_index_price')],
            join(
                ' AND ',
                [
                    'price_index.entity_id = e.entity_id',
                    'price_index.website_id = ' . $this->websiteId,
                    'price_index.customer_group_id = 0'
                ]
            ),
            ['final_price', 'min_price', 'max_price', 'price']
        );

        return $products;
    }

    /**
     * @param Product $product
     * @param string|null $groupedPriceType
     * @param string|null $bundlePriceType
     */
    private function setPrices(Product $product, ?string $groupedPriceType, ?string $bundlePriceType): void
    {
        switch ($product->getTypeId()) {
            case 'configurable':
                $this->setConfigurablePrices($product, $this->configurablePriceType);
                break;
            case 'grouped':
                $this->setGroupedPrices($product, $groupedPriceType);
                break;
            case 'bundle':
                $this->setBundlePrices($product, $bundlePriceType);
                break;
            default:
                $this->setSimplePrices($product);
                break;
        }

        $this->price = $this->round($this->price);
        $this->finalPrice = $this->round($this->finalPrice);
        $this->salesPrice = $this->round($this->salesPrice);
        $this->minPrice = $this->round($this->minPrice);

        $this->rulePrice = $this->getRulePrice($product);

        if ($this->finalPrice == '0.0000' && $this->minPrice > 0) {
            $this->finalPrice = $this->minPrice;
        }

        if ($this->finalPrice !== null && $this->finalPrice < $this->minPrice) {
            $this->minPrice = $this->finalPrice;
        }

        if ($this->finalPrice == null && $this->specialPrice !== null) {
            $this->finalPrice = $this->specialPrice;
        }

        if ($this->minPrice !== null && $this->price == null) {
            $this->price = $this->minPrice;
        }

        $this->salesPrice = null;
        if ($this->finalPrice !== null && ($this->price > $this->finalPrice)) {
            $this->salesPrice = $this->finalPrice;
        }

        if ($this->finalPrice === null && $this->price !== null) {
            $this->finalPrice = $this->price;
        }

        if ($this->price == '0.0000' && $this->finalPrice > 0) {
            $this->price = $this->finalPrice;
        }
    }

    /**
     * @param Product $product
     * @param string|null $configurablePriceType
     */
    private function setConfigurablePrices(Product $product, ?string $configurablePriceType = null): void
    {
        /**
         * Check if config has a final_price (data catalog_product_index_price)
         * If final_price === null product is not salable (out of stock)
         */
        if ($product->getData('final_price') === null) {
            return;
        }

        $this->price = $this->toPrice($product->getData('price'));
        $this->finalPrice = $this->toPrice($product->getData('final_price'));
        $this->specialPrice = $this->toPrice($product->getData('special_price'));
        $this->minPrice = $product['min_price'] >= 0 ? $this->toPrice($product['min_price']) : null;
        $this->maxPrice = $product['max_price'] >= 0 ? $this->toPrice($product['max_price']) : null;

        if ($configurablePriceType === 'min' && $this->minPrice !== null) {
            $this->price = $this->minPrice;
            $this->finalPrice = $this->minPrice;
            return;
        }

        if ($configurablePriceType === 'max' && $this->maxPrice !== null) {
            $this->price = $this->maxPrice;
            $this->finalPrice = $this->maxPrice;
            return;
        }

        if ($this->minPrice && $this->minPrice < $this->finalPrice) {
            $this->finalPrice = $this->minPrice;
        }
    }

    /**
     * @param Product $product
     * @param string|null $groupedPriceType
     */
    private function setGroupedPrices(Product $product, ?string $groupedPriceType)
    {
        $minPrice = null;
        $maxPrice = null;
        $totalPrice = null;

        /** @var Grouped $typeInstance */
        $typeInstance = $product->getTypeInstance();
        $subProducts = $typeInstance->getAssociatedProducts($product);

        /** @var Product $subProduct */
        foreach ($subProducts as $subProduct) {
            $subProduct->setWebsiteId($this->websiteId);
            if ($subProduct->isSalable()) {
                $price = $this->commonPriceModel->getCatalogPrice($subProduct);
                if ($price < $minPrice || $minPrice === null) {
                    $minPrice = $this->commonPriceModel->getCatalogPrice($subProduct);
                    $product->setTaxClassId($subProduct->getTaxClassId());
                }
                if ($price > $maxPrice || $maxPrice === null) {
                    $maxPrice = $this->commonPriceModel->getCatalogPrice($subProduct);
                    $product->setTaxClassId($subProduct->getTaxClassId());
                }
                if ($subProduct->getQty() > 0) {
                    $totalPrice += $price * $subProduct->getQty();
                } else {
                    $totalPrice += $price;
                }
            }
        }

        $this->minPrice = $this->toPrice($product['min_price'] >= 0 ? $product['min_price'] : $minPrice);
        $this->maxPrice = $this->toPrice($product['max_price'] >= 0 ? $product['max_price'] : $maxPrice);
        $this->totalPrice = $this->toPrice($totalPrice);

        if ($groupedPriceType == 'max') {
            $this->price = $this->maxPrice;
            $this->finalPrice = $this->maxPrice;
            return;
        }

        if ($groupedPriceType == 'total') {
            $this->price = $totalPrice;
            $this->finalPrice = $totalPrice;
            return;
        }

        $this->price = $this->minPrice;
        $this->finalPrice = $this->minPrice;
    }

    /**
     * @param Product $product
     * @param string|null $bundlePriceType
     */
    private function setBundlePrices(Product $product, ?string $bundlePriceType): void
    {
        $this->setSimplePrices($product);

        if ($bundlePriceType == 'max') {
            $this->price = $this->maxPrice;
            $this->finalPrice = $this->maxPrice;
        }

        if ($bundlePriceType == 'min') {
            $this->price = $this->minPrice;
            $this->finalPrice = $this->minPrice;
        }
    }

    /**
     * @param Product $product
     */
    private function setSimplePrices(Product $product)
    {
        $this->price = (float)$product->getData('price') !== 0.0
            ? $this->toPrice($product->getData('price'))
            : null;

        $this->finalPrice = (float)$product->getData('final_price') !== 0.0
            ? $this->toPrice($product->getData('final_price'))
            : null;

        $this->specialPrice = $product->getData('special_price')
            ? $this->toPrice($product->getData('special_price'))
            : null;

        $this->minPrice = $product['min_price'] >= 0 ? $this->toPrice($product['min_price']) : null;
        $this->maxPrice = $product['max_price'] >= 0 ? $this->toPrice($product['max_price']) : null;
    }

    private function round($price)
    {
        return $price ? round((float)$price, 2) : null;
    }

    /**
     * A price column as a float, or null when there is no value to speak of.
     *
     * @param mixed $value
     * @return float|null
     */
    private function toPrice($value): ?float
    {
        return $value === null || $value === '' ? null : (float)$value;
    }

    /**
     * Get special rule price from product
     *
     * @param Product $product
     * @return float
     */
    private function getRulePrice(Product $product): float
    {
        try {
            $this->rulePrice = $this->resourceRuleFactory->create()->getRulePrice(
                $this->localeDate->scopeDate(),
                (int)$this->websiteId,
                0,
                $product->getId()
            );
        } catch (Exception $exception) {
            return 0.0;
        }

        if ($this->rulePrice !== null && $this->rulePrice !== false) {
            // getRulePrice() answers with whatever the adapter returns, which is a numeric string.
            $this->rulePrice = (float)$this->rulePrice;
            $this->finalPrice = $this->finalPrice === null
                ? $this->rulePrice
                : min($this->finalPrice, $this->rulePrice);
        }

        return (float)$this->rulePrice;
    }

    /**
     * Calculate price multiplier including tax adjustments and currency rate differences.
     * Returns a float multiplier based on the processed price (incl. tax) and original price,
     * potentially adjusted for base-to-store currency conversion.
     *
     * @param Product $product The product for which to calculate the price multiplier.
     * @param StoreInterface $store The store context used to resolve tax and currency.
     * @return float The price multiplier relative to base price.
     */
    private function getPercentage(Product $product, StoreInterface $store): float
    {
        if ($this->price == 0.0) {
            return 0;
        }

        $taxClassId = (int)$product->getTaxClassId();
        if (isset($this->taxClasses[$taxClassId])) {
            return $this->taxClasses[$taxClassId];
        }

        $processed = $this->processPrice($product, (float)$this->price, $store);
        $percent = round($processed / $this->price, 2);

        // Adjust for currency difference
        if ($store->getBaseCurrencyCode() !== $this->currecy) {
            $rate = $store->getBaseCurrency()->getRate($this->currecy);
            if ($rate > 0) {
                $percent *= $rate;
            }
        }

        $this->taxClasses[$taxClassId] = $percent;
        return $percent;
    }

    /**
     * Get product price with or without tax
     *
     * @param Product $product
     * @param float $price inputted product price
     * @param StoreInterface|null $store
     * @return float
     */
    private function processPrice(Product $product, float $price, ?StoreInterface $store): float
    {
        // The helper takes a Store or a store id, not the interface Magento hands us here.
        $storeArg = $store instanceof Store ? $store : ($store !== null ? (int)$store->getId() : null);

        return (float)$this->catalogHelper->getTaxPrice(
            $product,
            $price,
            true,
            null,
            null,
            null,
            $storeArg
        );
    }

    /**
     * Get product special price data range
     *
     * @param Product $product
     * @return string
     */
    private function getSpecialPriceDateRang(Product $product): string
    {
        if ($this->specialPrice === null) {
            return '';
        }

        if ($product->getSpecialFromDate() && $product->getSpecialToDate()) {
            $from = date('Y-m-d\TH:i:s', strtotime($product->getSpecialFromDate()));
            $to = date('Y-m-d\T23:59:59', strtotime($product->getSpecialToDate())); // Fix ending time
            return $from . '/' . $to;
        }

        return '';
    }

    /**
     * Get product discount based on price and sales price
     *
     * @return string
     */
    private function getDiscountPercentage(): string
    {
        if ($this->price > 0 && $this->salesPrice > 0) {
            $discount = ($this->salesPrice - $this->price) / $this->price;
            $discount = $discount * -100;
            if ($discount > 0) {
                return round($discount, 1) . '%';
            }
        }
        return '0%';
    }
}
