<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Service\ProductData;

use Magento\Framework\Filter\FilterManager;
use PHPUnit\Framework\Attributes\DataProvider;
use TradeTracker\Connect\Service\ProductData\ActionProcessor;
use PHPUnit\Framework\TestCase;

/**
 * The post-processing the feed runs over every row: unit conversions, the conditional and multi
 * attribute mappings a merchant configures, description cleanup, UTM codes, shipping tiers,
 * loyalty tiers and the identifier_exists rule.
 *
 * All of it is array in, array out, and all of it decides what a channel receives for a product.
 * A wrong unit or a dropped sale price is not a crash; it is a disapproved or mispriced offer that
 * nobody notices until the channel complains.
 */
class ActionProcessorTest extends TestCase
{
    private ActionProcessor $processor;

    protected function setUp(): void
    {
        $this->processor = new ActionProcessor(new FilterManagerStub());
    }

    #[DataProvider('valueActions')]
    public function testAValueActionFormatsTheValue(string $action, string $value, string $expected): void
    {
        $row = ['weight' => $value];
        $this->processor->processAction($row, 'weight', $action, []);

        self::assertSame($expected, (string)$row['weight']);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function valueActions(): array
    {
        return [
            'grams to kilos' => ['weight_g_to_kg', '2500', '2.50 kg'],
            'ounces to pounds' => ['weight_oz_to_lb', '32', '2.00 lb'],
            'weight in its own unit' => ['weight_lb', '3', '3.00 lb'],
            'currency suffix' => ['currency_EUR', '19.9', '19.90 EUR'],
            'rounded' => ['round', '19.6', '20'],
            'two decimals' => ['number', '19.5', '19.50'],
            'an unknown action leaves the value alone' => ['something_else', '19.5', '19.5'],
        ];
    }

    public function testAnEmptyValueIsNotFormatted(): void
    {
        $row = ['weight' => ''];
        $this->processor->processAction($row, 'weight', 'weight_g_to_kg', []);

        // Without this guard an empty weight would be exported as "0.00 kg", which is a value.
        self::assertSame('', $row['weight']);
    }

    public function testAMissingKeyIsNotInvented(): void
    {
        $row = [];
        $this->processor->processAction($row, 'weight', 'weight_g_to_kg', []);

        self::assertArrayNotHasKey('weight', $row);
    }

    public function testAMultiActionTakesTheFirstAttributeThatHasAValue(): void
    {
        $row = [];
        $productData = ['ean' => '', 'upc' => '0885909950805', 'isbn' => '9780306406157'];

        $this->processor->processAction($row, 'gtin', ['multi' => ['ean', 'upc', 'isbn']], $productData);

        self::assertSame('0885909950805', $row['gtin']);
    }

    public function testAMultiActionWithNothingToTakeLeavesTheKeyUnset(): void
    {
        $row = [];
        $this->processor->processAction($row, 'gtin', ['multi' => ['ean', 'upc']], ['ean' => '', 'upc' => null]);

        self::assertArrayNotHasKey('gtin', $row);
    }

    public function testAConditionalTakesTheIfBranchWhenTheAttributeHasAValue(): void
    {
        $row = [];
        $action = [
            'conditional' => ['*:out of stock', '{{qty}}:in stock, {{qty}} left'],
            'conditional_attributes' => ['qty'],
        ];

        $this->processor->processAction($row, 'availability', $action, ['qty' => 7]);

        self::assertSame('in stock, 7 left', $row['availability']);
    }

    public function testAConditionalFallsBackToTheElseBranch(): void
    {
        $row = [];
        $action = [
            'conditional' => ['*:out of stock', '{{qty}}:in stock'],
            'conditional_attributes' => ['qty'],
        ];

        $this->processor->processAction($row, 'availability', $action, ['qty' => 0]);

        self::assertSame('out of stock', $row['availability']);
    }

    public function testADescriptionLosesItsMarkupAndKeepsItsStructure(): void
    {
        $html = '<p>A shirt</p><ul><li>Cotton</li><li>Washable</li></ul>'
            . '<style>.x{color:red}</style><p>{{widget type="foo"}}Done</p>';

        $description = $this->processor->cleanupDescription($html);

        self::assertStringNotContainsString('<', $description);
        self::assertStringNotContainsString('color:red', $description, 'style content is dropped, not just its tag');
        self::assertStringNotContainsString('widget', $description, 'Magento widget directives are dropped');
        self::assertStringContainsString('- Cotton', $description, 'list items become dashes');
        self::assertStringEndsWith('Done', $description, 'trailing newlines are trimmed');
    }

    public function testADescriptionWithEscapedStyleMarkupIsAlsoCleaned(): void
    {
        $description = $this->processor->cleanupDescription('Before&lt;style&gt;.x{color:red}&lt;/style&gt;After');

        self::assertStringNotContainsString('color:red', $description);
    }

    public function testTheUtmCodeIsAppendedBeforeTheFragment(): void
    {
        $row = ['link' => 'https://example.com/shirt.html#reviews'];
        $this->processor->appendUtmCodeToProductUrl($row, 'utm_source=google');

        self::assertSame('https://example.com/shirt.html?utm_source=google#reviews', $row['link']);
    }

    public function testTheUtmCodeIsNotGivenASecondQuestionMark(): void
    {
        $row = ['link' => 'https://example.com/shirt.html'];
        $this->processor->appendUtmCodeToProductUrl($row, '?utm_source=google');

        self::assertSame('https://example.com/shirt.html?utm_source=google', $row['link']);
    }

    public function testAProductWithoutALinkIsLeftAlone(): void
    {
        $row = ['link' => ''];
        $this->processor->appendUtmCodeToProductUrl($row, 'utm_source=google');

        self::assertSame('', $row['link']);
    }

    public function testShippingTiersAreMatchedOnThePriceAndFormattedWithTheCurrency(): void
    {
        $row = ['price' => '45.00 EUR'];
        $this->processor->addShippingPrices($row, self::SHIPPING_TIERS, 'EUR');

        self::assertSame(
            [['country' => 'NL', 'service' => 'Standard', 'price' => '4.95 EUR']],
            $row['shipping']
        );
    }

    public function testShippingTiersFollowTheSalePriceWhenThereIsOne(): void
    {
        // A product discounted below the free-shipping threshold must be offered with the shipping
        // cost the customer actually pays, not the one its undiscounted price would buy.
        $row = ['price' => '150.00 EUR', 'sale_price' => '45.00 EUR'];
        $this->processor->addShippingPrices($row, self::SHIPPING_TIERS, 'EUR');

        self::assertSame(
            [['country' => 'NL', 'service' => 'Standard', 'price' => '4.95 EUR']],
            $row['shipping']
        );
    }

    public function testAPriceOutsideEveryTierGetsAnEmptyShippingNode(): void
    {
        $row = ['price' => '5000.00 EUR'];
        $this->processor->addShippingPrices($row, self::SHIPPING_TIERS, 'EUR');

        self::assertSame([], $row['shipping']);
    }

    public function testARowWithoutAPriceGetsNoShippingNode(): void
    {
        $row = ['title' => 'A shirt'];
        $this->processor->addShippingPrices($row, self::SHIPPING_TIERS, 'EUR');

        self::assertArrayNotHasKey('shipping', $row);
    }

    public function testALoyaltyTierIsBuiltFromTheMappedAttributes(): void
    {
        $row = [];
        $config = [[
            'program_label' => 'my_program',
            'tier_label' => 'gold',
            'price' => 'member_price',
            'loyalty_points' => 'member_points',
            'member_price_effective_date' => 'member_from',
        ]];
        $productData = [
            'member_price' => '17.5',
            'member_points' => 120,
            'member_from' => '2026-01-01T00:00:00Z/2026-12-31T00:00:00Z',
        ];

        $this->processor->addLoyaltyProgram($row, $config, $productData, 'EUR');

        self::assertSame([[
            'program_label' => 'my_program',
            'tier_label' => 'gold',
            'price' => '17.50 EUR',
            'loyalty_points' => '120',
            'member_price_effective_date' => '2026-01-01T00:00:00Z/2026-12-31T00:00:00Z',
        ]], $row['loyalty_program']);
    }

    public function testAProductWithoutAMemberBenefitGetsNoLoyaltyNode(): void
    {
        $row = [];
        $config = [[
            'program_label' => 'my_program',
            'tier_label' => 'gold',
            'price' => 'member_price',
            'loyalty_points' => 'member_points',
        ]];

        $this->processor->addLoyaltyProgram($row, $config, ['member_price' => '', 'member_points' => null], 'EUR');

        self::assertArrayNotHasKey('loyalty_program', $row);
    }

    public function testALoyaltyRowWithoutLabelsIsSkipped(): void
    {
        $row = [];
        $config = [['program_label' => '', 'tier_label' => 'gold', 'price' => 'member_price']];

        $this->processor->addLoyaltyProgram($row, $config, ['member_price' => '17.5'], 'EUR');

        self::assertArrayNotHasKey('loyalty_program', $row);
    }

    public function testEveryUrlFieldIsForcedToHttps(): void
    {
        $row = [
            'link' => 'http://example.com/shirt.html',
            'image_link' => 'http://example.com/shirt.jpg',
            'additional_image_link' => ['http://example.com/a.jpg', 'https://example.com/b.jpg'],
        ];

        $this->processor->forceSecureUrls($row);

        self::assertSame('https://example.com/shirt.html', $row['link']);
        self::assertSame('https://example.com/shirt.jpg', $row['image_link']);
        self::assertSame(
            ['https://example.com/a.jpg', 'https://example.com/b.jpg'],
            $row['additional_image_link']
        );
    }

    #[DataProvider('identifierCases')]
    public function testIdentifierExistsSaysNoWhenTheChannelCannotIdentifyTheProduct(
        array $row,
        bool $expectNo
    ): void {
        $this->processor->processIdentifierExists($row);

        self::assertSame($expectNo, ($row['identifier_exists'] ?? null) === 'no');
    }

    /**
     * @return array<string, array{0: array, 1: bool}>
     */
    public static function identifierCases(): array
    {
        return [
            'brand and gtin' => [['brand' => 'Luma', 'gtin' => '0885909950805'], false],
            'brand and mpn' => [['brand' => 'Luma', 'mpn' => 'MS12'], false],
            'brand but neither gtin nor mpn' => [['brand' => 'Luma'], true],
            'gtin but no brand' => [['gtin' => '0885909950805'], true],
            'nothing at all' => [[], true],
        ];
    }

    public function testASalePriceThatIsNotLowerIsRemoved(): void
    {
        $row = ['sale_price' => '19.99 EUR', 'sale_price_effective_date' => '2026-01-01/2026-02-01'];
        $productData = ['price' => 19.99, 'final_price' => 19.99, 'sale_price_effective_date' => '2026-01-01/x'];

        $this->processor->processSalesPrices($row, $productData);

        self::assertArrayNotHasKey('sale_price', $row, 'a "sale" at the same price is not a sale');
        self::assertArrayNotHasKey('sale_price_effective_date', $row);
    }

    public function testARealSalePriceIsKept(): void
    {
        $row = ['sale_price' => '14.99 EUR'];
        $this->processor->processSalesPrices($row, ['price' => 19.99, 'final_price' => 14.99]);

        self::assertSame('14.99 EUR', $row['sale_price']);
    }

    public function testAProductWithoutAFinalPriceKeepsWhateverItHas(): void
    {
        $row = ['sale_price' => '14.99 EUR'];
        $this->processor->processSalesPrices($row, ['price' => 19.99]);

        self::assertSame('14.99 EUR', $row['sale_price']);
    }

    #[DataProvider('preparedAttributes')]
    public function testAnAttributeIsPreparedForItsFeedField(
        string $attribute,
        array $productData,
        string $index,
        $expected
    ): void {
        self::assertSame($expected, $this->processor->prepareAttribute($attribute, $productData, $index, 'EUR'));
    }

    /**
     * @return array<string, array{0: string, 1: array, 2: string, 3: mixed}>
     */
    public static function preparedAttributes(): array
    {
        return [
            'a price carries its currency' => ['price', ['price' => 19.9], 'price', '19.90 EUR'],
            'a missing price stays missing' => ['price', [], 'price', null],
            'availability is a channel keyword' => ['qty', ['qty' => 3], 'availability', 'in_stock'],
            'no stock is out_of_stock' => ['qty', ['qty' => 0], 'availability', 'out_of_stock'],
            'a simple product is its own parent' => ['x', ['product_id' => 42], 'parent_id', 42],
            'a variant points at its parent' => ['x', ['product_id' => 42, 'parent_id' => 7], 'parent_id', 7],
            'anything else passes through' => ['color', ['color' => 'Blue'], 'color', 'Blue'],
        ];
    }

    private const SHIPPING_TIERS = [
        ['code' => 'NL', 'service' => 'Standard', 'price' => 4.95, 'price_from' => 0, 'price_to' => 49.99],
        ['code' => 'NL', 'service' => 'Free', 'price' => 0, 'price_from' => 50, 'price_to' => 999],
    ];
}

/**
 * Magento's FilterManager dispatches every filter through __call, so neither a mock nor a stub can
 * be configured for removeTags(): the method does not exist to be configured. This declares it,
 * and does what the real filter does for the one call the processor makes.
 */
class FilterManagerStub extends FilterManager
{
    public function __construct()
    {
    }

    public function removeTags(string $value): string
    {
        return strip_tags($value);
    }
}
