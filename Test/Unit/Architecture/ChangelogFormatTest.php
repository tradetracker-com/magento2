<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * The newest CHANGELOG.md section is copied verbatim into the GitHub release by draft-release.yml,
 * and from there onto magmodules.eu. It therefore has to be written in the release-notes format:
 * bold headings with a colon, asterisk bullets that end in a period (Compatibility bullets excepted,
 * those are version labels), a Compatibility block last.
 *
 * 2.3.0 shipped with Keep-a-Changelog headings ("### Features", "- item") and the release page
 * had to be edited by hand; this test turns that into a red PR instead.
 */
class ChangelogFormatTest extends TestCase
{
    /**
     * Tests sits between the fixes and Compatibility: it says what the release was verified with,
     * which is the last thing a reader wants before the version numbers.
     */
    private const HEADINGS = ['Features', 'Improvements', 'Bugfixes', 'Tests', 'Compatibility'];

    public function testComposerVersionHasAChangelogSection(): void
    {
        $version = $this->composerVersion();
        if ($version === '') {
            // The CI PHPUnit job strips "version" from composer.json before installing the module.
            $this->markTestSkipped('composer.json carries no version in this environment');
        }
        $this->assertArrayHasKey($version, $this->sections(), 'CHANGELOG.md has no section for the composer.json version');
    }

    public function testNewestSectionUsesTheReleaseNotesFormat(): void
    {
        $sections = $this->sections();
        $version = array_key_first($sections);
        $lines = $sections[$version];
        $problems = [];
        $seen = [];

        foreach ($lines as $number => $line) {
            if ($line === '') {
                continue;
            }
            if (preg_match('/^\*\*([A-Za-z ]+):\*\*$/', $line, $m)) {
                if (!in_array($m[1], self::HEADINGS, true)) {
                    $problems[] = sprintf('line %d: unknown heading "%s"; use one of %s', $number, $m[1], implode(', ', self::HEADINGS));
                }
                $seen[] = $m[1];
                continue;
            }
            if (preg_match('/^\* \S/', $line)) {
                // Compatibility bullets are version labels ("Magento 2.4.x"), not sentences.
                if (end($seen) !== 'Compatibility' && !preg_match('/[.!?)`]$/', $line)) {
                    $problems[] = sprintf('line %d: bullet does not end with a period: %s', $number, mb_substr($line, 0, 60));
                }
                continue;
            }
            $problems[] = sprintf('line %d: expected "**Heading:**" or "* item", got: %s', $number, mb_substr($line, 0, 60));
        }

        if ($seen === []) {
            $problems[] = 'no **Heading:** blocks at all';
        } elseif (end($seen) !== 'Compatibility') {
            $problems[] = 'the last block has to be **Compatibility:**';
        }
        $ordered = array_values(array_intersect(self::HEADINGS, $seen));
        if ($ordered !== $seen) {
            $problems[] = 'headings out of order; expected ' . implode(' → ', $ordered);
        }

        $this->assertSame([], $problems, sprintf("CHANGELOG.md section [%s] is not in release-notes format:\n%s", $version, implode("\n", $problems)));
    }

    /**
     * @return array<string, array<int, string>> version => lines (keyed by 1-based line number)
     */
    private function sections(): array
    {
        $path = dirname(__DIR__, 3) . '/CHANGELOG.md';
        $this->assertFileExists($path, 'CHANGELOG.md');

        $sections = [];
        $current = null;
        foreach (file($path, FILE_IGNORE_NEW_LINES) as $index => $line) {
            if (preg_match('/^## \[([^\]]+)\]/', $line, $m)) {
                $current = $m[1];
                $sections[$current] = [];
                continue;
            }
            if ($current !== null) {
                $sections[$current][$index + 1] = rtrim($line);
            }
        }
        $this->assertNotSame([], $sections, 'CHANGELOG.md has no "## [x.y.z]" sections');

        return $sections;
    }

    private function composerVersion(): string
    {
        $composer = json_decode((string)file_get_contents(dirname(__DIR__, 3) . '/composer.json'), true);

        return (string)($composer['version'] ?? '');
    }
}
