<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Service\Feed;

use Magento\Framework\Filesystem\Io\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TradeTracker\Connect\Service\Feed\Create;
use TradeTracker\Connect\Service\Feed\Sanitizer;

/**
 * Catalogue values carry HTML entities (the Luma sample data has "LumaTech&trade; V-Tee"). The writer
 * escaped them a second time, so the feed said "&amp;trade;" and TradeTracker showed the entity as
 * text -- 174 titles in the sample catalogue.
 */
#[CoversClass(Create::class)]
#[CoversClass(Sanitizer::class)]
class CreateTest extends TestCase
{
    public function testAnHtmlEntityBecomesTheCharacterItStandsFor(): void
    {
        $xml = $this->writer()->createXml([['name' => 'LumaTech&trade; V-Tee']]);

        self::assertStringContainsString('<name>LumaTech™ V-Tee</name>', $xml);
        self::assertStringNotContainsString('&amp;trade;', $xml);
    }

    public function testTheCharactersXmlReservesAreStillEscaped(): void
    {
        $xml = $this->writer()->createXml([['description' => '<p>Fit & fast</p>']]);

        self::assertStringContainsString('<description>&lt;p&gt;Fit &amp; fast&lt;/p&gt;</description>', $xml);
    }

    public function testNestedValuesBecomeNestedElementsAndRowsBecomeItems(): void
    {
        $xml = $this->writer()->createXml([['additionalImages' => ['a.jpg', 'b.jpg']]]);

        self::assertStringContainsString('<item><additionalImages><item>a.jpg', str_replace("\n", '', $xml));
    }

    private function writer(): Create
    {
        return new Create($this->createMock(File::class), new Sanitizer());
    }
}
