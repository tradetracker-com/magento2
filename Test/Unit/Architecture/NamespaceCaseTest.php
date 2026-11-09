<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Every class's namespace has to match the directory it lives in, character for character.
 *
 * PSR-4 is case-sensitive and so is every Linux server this code runs on. macOS is not, which is
 * the whole problem: a developer adding `Model/Api/Sleeper.php` to a module that already has
 * `Model/API/` gets no error, no second directory and a working local test run — the file quietly
 * lands in the existing directory with a namespace that disagrees with it. It then fails to
 * autoload in production, and the first sign is a class-not-found during a deploy.
 *
 * This happened while adding a retry policy to four modules at once: three had `Model/Api`, one
 * had `Model/API`, and the generated file was identical for all four.
 */
class NamespaceCaseTest extends TestCase
{
    public function testEveryNamespaceMatchesItsDirectoryExactly(): void
    {
        $moduleDir = dirname(__DIR__, 3);
        $problems = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($moduleDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $path = $file->getPathname();
            $relative = substr($path, strlen($moduleDir) + 1);

            if ($file->getExtension() !== 'php'
                || str_contains('/' . $relative, '/Test/')
                || str_contains('/' . $relative, '/vendor/')
                || basename($path) === 'registration.php'
            ) {
                continue;
            }

            $contents = (string)file_get_contents($path);
            if (!preg_match('/^namespace\s+([^;]+);/m', $contents, $matches)) {
                continue;
            }

            $declared = trim($matches[1]);
            $prefix = 'TradeTracker\\Connect';

            if (!str_starts_with($declared, $prefix)) {
                $problems[] = sprintf('%s declares %s, which is outside this module.', $relative, $declared);
                continue;
            }

            $expected = trim(str_replace('/', '\\', dirname($relative)), '\\.');
            $actual = trim(substr($declared, strlen($prefix)), '\\');

            if ($actual !== $expected) {
                $problems[] = sprintf(
                    '%s declares %s\\%s but lives in %s — these differ%s.',
                    $relative,
                    $prefix,
                    $actual,
                    dirname($relative),
                    strcasecmp($actual, $expected) === 0 ? ' only in case, which macOS hides' : ''
                );
            }
        }

        $this->assertSame(
            [],
            $problems,
            "PSR-4 is case-sensitive and so is every Linux server this runs on:\n- "
            . implode("\n- ", $problems)
        );
    }
}
