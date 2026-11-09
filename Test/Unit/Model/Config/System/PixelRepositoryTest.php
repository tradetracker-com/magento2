<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Model\Config\System;

use Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory as ConfigDataCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TradeTracker\Connect\Api\Config\System\PixelInterface;
use TradeTracker\Connect\Model\Config\System\PixelRepository;
use Magento\Config\Model\ResourceModel\Config as ConfigData;

/**
 * The pixel sends a custom currency only when the merchant turned "Overwrite Currency" on and
 * entered a code. Up to 1.8.0 the check returned nothing exactly when the toggle was on, and the
 * code field wrote to the toggle's path, so the feature never worked.
 */
#[CoversClass(PixelRepository::class)]
class PixelRepositoryTest extends TestCase
{
    private const STORE_ID = 3;

    public function testTheCodeIsSentWhenTheToggleIsOn(): void
    {
        self::assertSame('EUR', $this->repository(true, 'EUR')->getCustomCurrencyCode(self::STORE_ID));
    }

    public function testNothingIsSentWhenTheToggleIsOff(): void
    {
        self::assertNull($this->repository(false, 'EUR')->getCustomCurrencyCode(self::STORE_ID));
    }

    public function testNothingIsSentWithoutACode(): void
    {
        self::assertNull($this->repository(true, '')->getCustomCurrencyCode(self::STORE_ID));
    }

    public function testTheCodeAndTheToggleAreReadFromTheirOwnPaths(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects(self::once())
            ->method('getValue')
            ->with(PixelInterface::XML_PATH_NON_DEFAULT_CURRENCY, ScopeInterface::SCOPE_STORE, self::STORE_ID)
            ->willReturn('USD');
        $scopeConfig->expects(self::once())
            ->method('isSetFlag')
            ->with(PixelInterface::XML_PATH_OVERWRITE_CURRENCY, ScopeInterface::SCOPE_STORE, self::STORE_ID)
            ->willReturn(true);

        self::assertSame('USD', $this->build($scopeConfig)->getCustomCurrencyCode(self::STORE_ID));
    }

    private function repository(bool $overwrite, string $code): PixelRepository
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path) => $path === PixelInterface::XML_PATH_NON_DEFAULT_CURRENCY ? $code : null
        );
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn (string $path) => $path === PixelInterface::XML_PATH_OVERWRITE_CURRENCY && $overwrite
        );

        return $this->build($scopeConfig);
    }

    private function build(ScopeConfigInterface $scopeConfig): PixelRepository
    {
        return new PixelRepository(
            $this->createMock(StoreManagerInterface::class),
            $scopeConfig,
            $this->createMock(ConfigDataCollectionFactory::class),
            $this->createMock(ConfigData::class),
            $this->createMock(Json::class),
            $this->createMock(ProductMetadataInterface::class),
            $this->createMock(ComponentRegistrarInterface::class),
            $this->createMock(FileDriver::class),
            $this->createMock(EncryptorInterface::class)
        );
    }
}
