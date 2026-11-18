<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Block\Adminhtml\Order\View\Tab;

use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TradeTracker\Connect\Api\Config\System\PixelInterface;
use TradeTracker\Connect\Block\Adminhtml\Order\View\Tab\TransactionDetail;

/**
 * The tab on the order page is a title and a URL, nothing more: it renders no body, it calls no
 * API, and it is there only when the module, the pixel and the setting say so and the admin may
 * view orders. Up to 1.9.0 it fetched the transaction while the order page loaded, for everyone.
 */
#[CoversClass(TransactionDetail::class)]
class TransactionDetailTest extends TestCase
{
    protected function setUp(): void
    {
        // Backend\Block\Template pulls optional collaborators from the static object manager.
        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->method('get')->willReturnCallback(fn (string $class) => $this->createMock($class));
        ObjectManager::setInstance($objectManager);
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(ObjectManager::class, '_instance'))->setValue(null, null);
    }

    public function testTheTabIsShownWhenEverythingIsOnAndTheAdminMayViewOrders(): void
    {
        self::assertTrue($this->block(true, true, true, true)->canShowTab());
    }

    public function testADisabledModuleHidesTheTab(): void
    {
        self::assertFalse($this->block(false, true, true, true)->canShowTab());
    }

    public function testAStoreViewWithoutThePixelHidesTheTab(): void
    {
        self::assertFalse($this->block(true, false, true, true)->canShowTab());
    }

    public function testTheSettingHidesTheTab(): void
    {
        self::assertFalse($this->block(true, true, false, true)->canShowTab());
    }

    public function testAStoreViewWithoutACampaignHidesTheTab(): void
    {
        self::assertFalse($this->block(true, true, true, true, true, '')->canShowTab());
    }

    public function testAnAdminWithoutOrderAccessDoesNotSeeTheTab(): void
    {
        self::assertFalse($this->block(true, true, true, false)->canShowTab());
    }

    public function testWithoutAnOrderThereIsNoTab(): void
    {
        self::assertFalse($this->block(true, true, true, true, false)->canShowTab());
    }

    public function testTheTabLoadsOverAjaxFromTheTabEndpointAndRendersNothingItself(): void
    {
        $block = $this->block(true, true, true, true);

        self::assertSame('ajax only', $block->getTabClass());
        self::assertSame('https://shop/admin/tradetracker/transaction/tab?order_id=7', $block->getTabUrl());
        self::assertSame('', $block->toHtml());
    }

    private function block(bool $enabled, bool $pixel, bool $tab, bool $allowed, bool $withOrder = true, string $campaign = '1234'): TransactionDetail
    {
        $authorization = $this->createMock(AuthorizationInterface::class);
        $authorization->method('isAllowed')->with('Magento_Sales::actions_view')->willReturn($allowed);
        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn (string $route, array $params) => 'https://shop/admin/' . $route . '?' . http_build_query($params)
        );
        $context = $this->createMock(Context::class);
        $context->method('getAuthorization')->willReturn($authorization);
        $context->method('getUrlBuilder')->willReturn($url);
        $context->method('getEventManager')->willReturn($this->createMock(ManagerInterface::class));
        $context->method('getScopeConfig')->willReturn($this->createMock(ScopeConfigInterface::class));

        $config = $this->createMock(PixelInterface::class);
        $config->method('isEnabled')->willReturn($enabled);
        $config->method('isPixelEnabled')->with(2)->willReturn($pixel);
        $config->method('isOrderTabEnabled')->with(2)->willReturn($tab);
        $config->method('getCampaignId')->with(2)->willReturn($campaign);

        $registry = new Registry();
        if ($withOrder) {
            $order = $this->createMock(Order::class);
            $order->method('getStoreId')->willReturn(2);
            $order->method('getId')->willReturn(7);
            $registry->register('current_order', $order);
        }

        return new TransactionDetail($context, $registry, $config);
    }
}
