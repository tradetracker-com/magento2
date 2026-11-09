<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Service\ProductData;

use Magento\Framework\Filter\FilterManager;

/**
 * Handles post-processing actions, description cleanup, UTM codes and shipping prices for product feed data.
 */
class ActionProcessor
{
    /**
     * @var FilterManager
     */
    private $filterManager;

    public function __construct(
        FilterManager $filterManager
    ) {
        $this->filterManager = $filterManager;
    }

    /**
     * Process an individual action for a data row key.
     *
     * @param array &$dataRow
     * @param string $key
     * @param mixed $action
     * @param array $productData
     * @return void
     */
    public function processAction(array &$dataRow, string $key, $action, array $productData): void
    {
        if (is_string($action) && isset($dataRow[$key]) && $dataRow[$key] !== '') {
            $dataRow[$key] = $this->applyAction($dataRow[$key], $action);
        } elseif (is_array($action) && isset($action['multi'])) {
            $this->processMultiAction($dataRow, $key, $action['multi'], $productData);
        } elseif (is_array($action) && isset($action['conditional'])) {
            $this->processConditionalAction($dataRow, $key, $action, $productData);
        }
    }

    /**
     * Applies a post-processing action to a value.
     *
     * @param mixed $value
     * @param string $action
     * @return mixed
     */
    private function applyAction($value, string $action)
    {
        if (strpos($action, 'currency_') === 0) {
            return number_format((float)$value, 2) . ' ' . explode('_', $action)[1];
        } elseif ($action === 'weight_g_to_kg') {
            return number_format((float)$value / 1000, 2) . ' kg';
        } elseif ($action === 'weight_oz_to_lb') {
            return number_format((float)$value / 16, 2) . ' lb';
        } elseif (strpos($action, 'weight_') === 0) {
            return number_format((float)$value, 2) . ' ' . explode('_', $action)[1];
        } elseif ($action === 'remove_tags') {
            return $this->cleanupDescription((string)$value);
        } elseif ($action === 'round') {
            return round((float)$value);
        } elseif ($action === 'number') {
            return number_format((float)$value, 2);
        } else {
            return $value;
        }
    }

    /**
     * Process a 'multi' action for a data row key.
     *
     * @param array &$dataRow
     * @param string $key
     * @param array $attributes
     * @param array $productData
     * @return void
     */
    private function processMultiAction(array &$dataRow, string $key, array $attributes, array $productData): void
    {
        foreach ($attributes as $attribute) {
            if (!empty($productData[$attribute])) {
                $dataRow[$key] = $productData[$attribute];
                break;
            }
        }
    }

    /**
     * Process a 'conditional' action for a data row key.
     *
     * @param array &$dataRow
     * @param string $key
     * @param array $action
     * @param array $productData
     * @return void
     */
    private function processConditionalAction(array &$dataRow, string $key, array $action, array $productData): void
    {
        $conditions = $action['conditional'] ?? [];
        $attributes = $action['conditional_attributes'] ?? [];

        $values = array_map(
            fn($attribute) => $productData[$attribute] ?? null,
            array_combine(
                array_map(fn($attr) => "{{{$attr}}}", $attributes),
                $attributes
            )
        );

        $dataIf = $dataElse = null;
        foreach ($conditions as $condition) {
            [$placeholder, $result] = explode(':', $condition) + [null, null];

            // A condition without a colon leaves the placeholder null, and $values[null] is a
            // fatal on PHP 8.5. An empty placeholder has nothing to match on either, so both
            // are skipped.
            if ($placeholder === null || $placeholder === '') {
                continue;
            }

            if ($placeholder === '*') {
                $dataElse = $result;
            } elseif (!empty($values[$placeholder])) {
                $dataIf = $result;
            }
        }

        $dataIf = $this->replacePlaceholders($dataIf, $values);
        $dataElse = $this->replacePlaceholders($dataElse, $values);

        $dataRow[$key] = $dataIf ?: $dataElse;
    }

    /**
     * Replace placeholders in a string with their corresponding values.
     *
     * @param string|null $string
     * @param array $values
     * @return string|null
     */
    private function replacePlaceholders(?string $string, array $values): ?string
    {
        if ($string === null) {
            return null;
        }

        foreach ($values as $placeholder => $value) {
            $string = str_replace($placeholder, (string)$value, $string);
        }

        return $string;
    }

    /**
     * Cleans up product description by removing unwanted HTML and formatting content for export.
     *
     * @param string $data Raw HTML product description.
     * @return string Cleaned plain-text description.
     */
    public function cleanupDescription(string $data): string
    {
        $data = preg_replace('/<li[^>]*>/i', '- ', $data) ?? $data;
        $data = preg_replace('/<\/li>/i', PHP_EOL, $data) ?? $data;
        $data = preg_replace('/<\/p>/i', PHP_EOL . PHP_EOL, $data) ?? $data;

        $data = preg_replace('/{{.*?}}/', '', $data) ?? $data;

        $data = preg_replace('/&lt;style\b[^&gt;]*&gt;.*?&lt;\/style&gt;/is', '', $data) ?? $data;
        $data = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $data) ?? $data;

        $data = $this->filterManager->removeTags($data);
        return rtrim($data, PHP_EOL);
    }

    /**
     * Appends a UTM code to the product URL, preserving any fragment identifier.
     *
     * @param array &$dataRow
     * @param string $utmCode
     * @return void
     */
    public function appendUtmCodeToProductUrl(array &$dataRow, string $utmCode): void
    {
        if (!empty($dataRow['link'])) {
            $urlParts = explode('#', $dataRow['link'], 2);
            $baseUrl = $urlParts[0];
            $fragment = $urlParts[1] ?? '';

            $baseUrl .= ($utmCode[0] === '?') ? $utmCode : '?' . $utmCode;

            if ($fragment !== '') {
                $baseUrl .= '#' . $fragment;
            }

            $dataRow['link'] = $baseUrl;
        }
    }

    /**
     * Add shipping prices to the data row based on configuration.
     *
     * @param array &$dataRow
     * @param array $shippingPrices
     * @param string $currency
     * @return void
     */
    public function addShippingPrices(array &$dataRow, array $shippingPrices, string $currency): void
    {
        if (!isset($dataRow['price'])) {
            return;
        }

        // 'sale_price' is the key the feed row carries; 'sales_price' never existed here, so every
        // discounted product was matched against the shipping tier of its undiscounted price.
        $price = preg_replace('/[^0-9.,]/', '', (string)($dataRow['sale_price'] ?? $dataRow['price'])) ?? '';
        $price = (float)str_replace(',', '.', $price);

        $filteredShippingPrices = array_filter($shippingPrices, function ($shipping) use ($price) {
            return $price >= $shipping['price_from'] && $price <= $shipping['price_to'];
        });

        $shipping = array_map(function ($shipping) use ($currency) {
            return [
                'country' => $shipping['code'] ?? '',
                'service' => $shipping['service'] ?? '',
                'price' => round((float)$shipping['price'], 2) . ' ' . $currency
            ];
        }, $filteredShippingPrices);

        $dataRow['shipping'] = array_values($shipping);
    }

    /**
     * Build the nested loyalty_program node(s) for the data row.
     *
     * Each configured row defines a loyalty tier: program_label and tier_label are
     * static identifiers (matching the program/tier configured in Google Merchant
     * Center), while price, loyalty_points and member_price_effective_date are pulled
     * per product from the mapped attribute codes found in $productData. A node is only
     * emitted when the product actually carries a member benefit (a price or points),
     * so products without loyalty pricing are left untouched.
     *
     * @param array &$dataRow
     * @param array $loyaltyConfig Rows from the loyalty program configuration grid.
     * @param array $productData Raw product data keyed by attribute code.
     * @param string $currency Currency code for member price formatting.
     * @return void
     */
    public function addLoyaltyProgram(
        array &$dataRow,
        array $loyaltyConfig,
        array $productData,
        string $currency
    ): void {
        $programs = [];

        foreach ($loyaltyConfig as $row) {
            $programLabel = trim((string)($row['program_label'] ?? ''));
            $tierLabel = trim((string)($row['tier_label'] ?? ''));

            if ($programLabel === '' || $tierLabel === '') {
                continue;
            }

            $price = $this->resolveLoyaltyValue($row['price'] ?? '', $productData);
            $points = $this->resolveLoyaltyValue($row['loyalty_points'] ?? '', $productData);
            $effectiveDate = $this->resolveLoyaltyValue($row['member_price_effective_date'] ?? '', $productData);

            // Skip products that carry no member benefit for this tier.
            if (($price === null || $price === '') && ($points === null || $points === '')) {
                continue;
            }

            $program = [
                'program_label' => $programLabel,
                'tier_label' => $tierLabel,
            ];

            if ($price !== null && $price !== '') {
                $program['price'] = sprintf('%s %s', number_format((float)$price, 2, '.', ''), $currency);
            }
            if ($points !== null && $points !== '') {
                $program['loyalty_points'] = (string)$points;
            }
            if ($effectiveDate !== null && $effectiveDate !== '') {
                $program['member_price_effective_date'] = (string)$effectiveDate;
            }

            $programs[] = $program;
        }

        if (!empty($programs)) {
            $dataRow['loyalty_program'] = $programs;
        }
    }

    /**
     * Resolve a loyalty grid cell that references a product attribute code.
     *
     * @param string $attributeCode
     * @param array $productData
     * @return mixed|null
     */
    private function resolveLoyaltyValue(string $attributeCode, array $productData)
    {
        $attributeCode = trim($attributeCode);
        if ($attributeCode === '') {
            return null;
        }

        return $productData[$attributeCode] ?? null;
    }

    /**
     * Force all URL fields to use HTTPS.
     *
     * @param array &$dataRow
     * @param array $fields URL field keys to process
     * @return void
     */
    public function forceSecureUrls(
        array &$dataRow,
        array $fields = ['image_link', 'link', 'additional_image_link']
    ): void {
        foreach ($fields as $field) {
            if (isset($dataRow[$field])) {
                $dataRow[$field] = is_array($dataRow[$field])
                    ? array_map(fn($url) => str_replace('http://', 'https://', $url), $dataRow[$field])
                    : str_replace('http://', 'https://', $dataRow[$field]);
            }
        }
    }

    /**
     * Set identifier_exists to 'no' when brand or both gtin and mpn are missing.
     *
     * @param array &$dataRow
     * @return void
     */
    public function processIdentifierExists(array &$dataRow): void
    {
        if (empty($dataRow['brand']) || (empty($dataRow['gtin']) && empty($dataRow['mpn']))) {
            $dataRow['identifier_exists'] = 'no';
        }
    }

    /**
     * Remove sale price data when final price is not lower than regular price.
     *
     * @param array &$dataRow
     * @param array $productData
     * @return void
     */
    public function processSalesPrices(array &$dataRow, array $productData): void
    {
        $price = $productData['price'] ?? null;
        $finalPrice = $productData['final_price'] ?? null;
        $salesDateRange = $productData['sale_price_effective_date'] ?? null;

        if ($finalPrice !== null && $price !== null && $finalPrice >= $price) {
            unset($dataRow['sale_price']);
            if (!empty($salesDateRange)) {
                unset($dataRow['sale_price_effective_date']);
            }
        }
    }

    /**
     * Prepare a single attribute value for feed output.
     *
     * @param string $attribute Source attribute key
     * @param array $productData Raw product data
     * @param string $index Result key / feed field name
     * @param string $currency Currency code for price formatting
     * @return mixed
     */
    public function prepareAttribute(
        string $attribute,
        array $productData,
        string $index,
        string $currency,
        string $inStock = 'in_stock',
        string $outOfStock = 'out_of_stock'
    ) {
        $value = $productData[$attribute] ?? null;

        switch ($index) {
            case 'parent_id':
                return $productData['parent_id'] ?? $productData['product_id'];
            case 'availability':
                // Google and Meta spell these with an underscore, TikTok with a space. The caller
                // decides; the default is the spelling the majority of the channels use.
                return $value ? $inStock : $outOfStock;
            case 'description':
                return $this->cleanupDescription((string)$value);
            case 'price':
            case 'sale_price':
                return $value === null
                    ? null
                    : sprintf('%s %s', number_format((float)$value, 2, '.', ''), $currency);
            default:
                return $value;
        }
    }
}
