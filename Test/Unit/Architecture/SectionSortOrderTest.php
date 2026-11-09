<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Every section under the Magmodules tab sorts alphabetically by its label.
 *
 * The tab lists the sections of every installed Magmodules module in one column, ordered by
 * sortOrder alone. A module that picks 1 or 10 lands at the top whatever its name, and with ten
 * modules installed the merchant scans the whole list for "TradeTracker". The rule: the
 * alphabetical position of the label's first letter times ten, plus 0-9 to break ties on the
 * second letter. So a section
 * labelled "Sitemap" sorts in 190-199, "Robots" in 180-189.
 */
class SectionSortOrderTest extends TestCase
{
    public function testEverySectionSortsAlphabeticallyByLabel(): void
    {
        $file = dirname(__DIR__, 3) . '/etc/adminhtml/system.xml';
        if (!is_file($file)) {
            self::markTestSkipped('The module ships no etc/adminhtml/system.xml.');
        }

        $dom = new \DOMDocument();
        $dom->load($file);
        $xpath = new \DOMXPath($dom);
        $wrong = [];
        $checked = 0;

        foreach ($xpath->query('//section[tab="magmodules"]') ?: [] as $section) {
            /** @var \DOMElement $section */
            $label = trim((string)$xpath->evaluate('string(label)', $section));
            $letter = strtoupper(substr($label, 0, 1));
            if ($letter < 'A' || $letter > 'Z') {
                continue;
            }
            $checked++;
            $min = (ord($letter) - ord('A') + 1) * 10;
            $sortOrder = (int)$section->getAttribute('sortOrder');
            if ($sortOrder < $min || $sortOrder > $min + 9) {
                $wrong[] = sprintf(
                    '%s ("%s") has sortOrder %d, expected %d-%d',
                    $section->getAttribute('id'),
                    $label,
                    $sortOrder,
                    $min,
                    $min + 9
                );
            }
        }

        self::assertGreaterThan(0, $checked, 'no section under the magmodules tab');
        self::assertSame([], $wrong, "sections out of alphabetical order:\n" . implode("\n", $wrong));
    }
}
