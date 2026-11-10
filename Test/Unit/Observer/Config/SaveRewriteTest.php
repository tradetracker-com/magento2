<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Observer\Config;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TradeTracker\Connect\Api\Config\System\DirectLinkingInterface;
use TradeTracker\Connect\Observer\Config\SaveRewrite;
use TradeTracker\Connect\Service\DirectLinking\UrlRewrite;

/**
 * The DirectLinking URL key becomes a URL rewrite per store view when the configuration is saved.
 * With "Use system value" ticked the form posts the field without a value; on Default Config that
 * used to produce one rewrite for store 0, which no request ever matches, so the default key gave a
 * 404 while an explicitly typed key worked.
 */
#[CoversClass(SaveRewrite::class)]
class SaveRewriteTest extends TestCase
{
    /** @var list<array{string, int}> request path and store id of every rewrite written */
    private array $rewrites = [];

    public function testAnInheritedKeyOnDefaultConfigWritesARewriteForEveryStoreView(): void
    {
        $this->run_(['url_key' => []], null, [1 => 'tradetracker-redirect', 2 => 'tt-nl']);

        self::assertSame([['tradetracker-redirect', 1], ['tt-nl', 2]], $this->rewrites);
    }

    public function testAnInheritedKeyOnAStoreViewWritesTheValueThatStoreInherits(): void
    {
        $this->run_(['url_key' => []], 2, [1 => 'tradetracker-redirect', 2 => 'tt-nl']);

        self::assertSame([['tt-nl', 2]], $this->rewrites);
    }

    public function testAnExplicitKeyOnAStoreViewWritesThatKey(): void
    {
        $this->run_(['url_key' => ['value' => 'offers']], 2, [1 => 'x', 2 => 'y']);

        self::assertSame([['offers', 2]], $this->rewrites);
    }

    public function testAnExplicitKeyOnDefaultConfigWritesWhatEveryStoreViewNowReads(): void
    {
        $this->run_(['url_key' => ['value' => 'offers']], null, [1 => 'offers', 2 => 'offers']);

        self::assertSame([['offers', 1], ['offers', 2]], $this->rewrites);
    }

    public function testAnEmptyKeyWritesNothing(): void
    {
        $this->run_(['url_key' => ['value' => '']], null, [1 => 'x']);

        self::assertSame([], $this->rewrites);
    }

    public function testASaveWithoutTheFieldWritesNothing(): void
    {
        $this->run_([], null, [1 => 'x']);

        self::assertSame([], $this->rewrites);
    }

    /**
     * @param array<string, array<string, string>> $fields the posted redirect_linking fields
     * @param array<int, string> $configured url key per store id as the configuration reads it
     */
    private function run_(array $fields, ?int $store, array $configured): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(
            static fn (string $name) => $name === 'groups'
                ? ['redirect_linking' => ['fields' => $fields]]
                : $store
        );

        $urlRewrite = $this->createMock(UrlRewrite::class);
        $urlRewrite->method('execute')->willReturnCallback(function (array $data): void {
            self::assertSame('tradetracker/redirect', $data['target_path']);
            $this->rewrites[] = [$data['request_path'], $data['store_id']];
        });

        $config = $this->createMock(DirectLinkingInterface::class);
        $config->method('getRedirectUrl')->willReturnCallback(
            static fn (?int $storeId) => $configured[$storeId] ?? ''
        );

        $stores = [];
        foreach (array_keys($configured) as $id) {
            $s = $this->createMock(StoreInterface::class);
            $s->method('getId')->willReturn($id);
            $stores[] = $s;
        }
        $storeRepository = $this->createMock(StoreRepositoryInterface::class);
        $storeRepository->method('getList')->willReturn($stores);

        (new SaveRewrite($request, $urlRewrite, $config, $storeRepository))->execute(new Observer());
    }
}
