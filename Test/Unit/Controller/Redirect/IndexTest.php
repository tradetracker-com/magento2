<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Controller\Redirect;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\Redirect as RedirectResult;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Encryption\Encryptor;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\Cookie\PublicCookieMetadata;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TradeTracker\Connect\Api\Config\System\DirectLinkingInterface;
use TradeTracker\Connect\Controller\Redirect\Index;

#[CoversClass(Index::class)]
class IndexTest extends TestCase
{
    public function testARedirectWithoutTheRParameterStillRedirects(): void
    {
        self::assertSame(
            'https://tc.tradetracker.net/?c=1&m=2&a=3&r=4&u=',
            $this->redirect(['tt' => '1_2_3_4'])
        );
    }

    public function testARedirectWithTheRParameterCarriesTheTargetUrl(): void
    {
        self::assertSame(
            'https://tc.tradetracker.net/?c=1&m=2&a=3&r=4&u=' . urlencode('https://shop.example/checkout'),
            $this->redirect(['tt' => '1_2_3_4', 'r' => 'https://shop.example/checkout'])
        );
    }

    /**
     * @param array<string, string> $params
     */
    private function redirect(array $params): string
    {
        $request = $this->createMock(HttpRequest::class);
        $request->method('getParam')->willReturnCallback(
            static fn (string $name) => $params[$name] ?? null
        );

        $config = $this->createMock(DirectLinkingInterface::class);
        $config->method('isEnabled')->willReturn(true);

        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->willReturn('https://example.test/');
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $date = $this->createMock(TimezoneInterface::class);
        $date->method('scopeTimeStamp')->willReturn(1700000000);

        $encryptor = $this->createMock(Encryptor::class);
        $encryptor->method('encrypt')->willReturn('checksum');

        $metadata = $this->createMock(PublicCookieMetadata::class);
        $metadata->method('setPath')->willReturnSelf();
        $metadata->method('setHttpOnly')->willReturnSelf();
        $metadata->method('setSecure')->willReturnSelf();
        $metadata->method('setDomain')->willReturnSelf();
        $metadata->method('setDuration')->willReturnSelf();
        $cookieMetadataFactory = $this->createMock(CookieMetadataFactory::class);
        $cookieMetadataFactory->method('createPublicCookieMetadata')->willReturn($metadata);

        $result = $this->createMock(RedirectResult::class);
        $path = '';
        $result->method('setPath')->willReturnCallback(
            function (string $value) use (&$path, $result) {
                $path = $value;
                return $result;
            }
        );
        $result->method('setHeader')->willReturnSelf();
        $result->method('setHttpResponseCode')->willReturnSelf();

        $resultFactory = $this->createMock(ResultFactory::class);
        $resultFactory->method('create')->willReturn($result);

        $context = $this->createMock(Context::class);
        $context->method('getResultFactory')->willReturn($resultFactory);

        (new Index(
            $context,
            $request,
            $encryptor,
            $storeManager,
            $date,
            $config,
            $this->createMock(CookieManagerInterface::class),
            $cookieMetadataFactory
        ))->execute();

        return $path;
    }
}
