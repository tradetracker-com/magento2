<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Model\Config\System;

use Magento\Config\Model\ResourceModel\Config as ConfigData;
use Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory as ConfigDataCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TradeTracker\Connect\Api\Config\System\AdvancedInterface;
use TradeTracker\Connect\Model\Config\System\AdvancedRepository;

/**
 * The API timeout is always a positive number of seconds: an empty field, a zero or a negative
 * value fall back to the default rather than switching the timeout off.
 */
#[CoversClass(AdvancedRepository::class)]
class AdvancedRepositoryTest extends TestCase
{
    /**
     * @return iterable<string, array{string|null, int}>
     */
    public static function timeouts(): iterable
    {
        yield 'a configured value' => ['3', 3];
        yield 'empty falls back' => ['', AdvancedInterface::DEFAULT_API_TIMEOUT];
        yield 'nothing stored falls back' => [null, AdvancedInterface::DEFAULT_API_TIMEOUT];
        yield 'zero falls back' => ['0', AdvancedInterface::DEFAULT_API_TIMEOUT];
        yield 'negative falls back' => ['-5', AdvancedInterface::DEFAULT_API_TIMEOUT];
    }

    #[DataProvider('timeouts')]
    public function testTheTimeoutIsAlwaysPositive(?string $stored, int $expected): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->with(AdvancedInterface::API_TIMEOUT)->willReturn($stored);

        self::assertSame($expected, $this->build($scopeConfig)->getApiTimeout());
    }

    private function build(ScopeConfigInterface $scopeConfig): AdvancedRepository
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(0);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return new AdvancedRepository(
            $storeManager,
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
