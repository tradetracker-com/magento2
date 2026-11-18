<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Model\Config\System\Config;

use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\Store;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TradeTracker\Connect\Api\Config\RepositoryInterface;
use TradeTracker\Connect\Model\Config\System\Config\Comment;

/**
 * The DirectLinking comment shows the redirect URL of the scope the admin looks at. The request
 * hands the store over as a string, and the configuration wants an int or nothing: the
 * configuration page at store view scope died on a TypeError until this cast.
 */
#[CoversClass(Comment::class)]
class CommentTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, int|null}>
     */
    public static function scopes(): iterable
    {
        yield 'store view from the request' => ['1', 1];
        yield 'default configuration' => [null, null];
        yield 'website scope sends no store' => ['', null];
    }

    #[DataProvider('scopes')]
    public function testTheStoreOfTheScopeIsAskedForAsAnInt($param, ?int $expected): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->with('store')->willReturn($param);
        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->willReturn('https://shop/');
        $config = $this->createMock(RepositoryInterface::class);
        $config->expects(self::once())->method('getStore')->with($expected)->willReturn($store);

        $text = (new Comment($request, $config))->getCommentText('offers');

        self::assertStringContainsString('https://shop/offers', $text);
    }
}
