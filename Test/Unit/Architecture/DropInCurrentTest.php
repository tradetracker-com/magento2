<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Service/ProductData is a drop-in: the same files in every feed module, only the namespace
 * differs, and module-standards/templates/feed-module is the source. A fix applied to one copy
 * and not the others is invisible in review and shows up as a feed that reports something else
 * for the same catalogue.
 *
 * This test compares the module's copy with the source when module-standards is checked out next
 * to the module, which is how the modules are laid out on a development machine. In CI the source
 * is not there and the test skips; the sync itself is `refactors/tools/dropin-sync.py`.
 */
class DropInCurrentTest extends TestCase
{
    /**
     * Spelled in parts so that a placeholder replacement over the whole file leaves it intact.
     */
    private const PLACEHOLDER = '__VENDOR' . '__\\__' . 'MODULE__';
    private const SUBTREES = ['Service/ProductData', 'Test/Unit/Service/ProductData'];

    public function testTheDropInMatchesTheSourceInModuleStandards(): void
    {
        $module = dirname(__DIR__, 3);
        if (!is_dir($module . '/Service/ProductData')) {
            self::markTestSkipped('This module carries no ProductData drop-in.');
        }

        $source = dirname($module) . '/module-standards/templates/feed-module';
        if (!is_dir($source)) {
            self::markTestSkipped('module-standards is not checked out next to this module.');
        }

        // The module's namespace prefix, read from this class rather than written down: whatever
        // tool scaffolds the module cannot get it wrong.
        $namespace = (string)preg_replace('/\\\\Test\\\\Unit\\\\Architecture$/', '', __NAMESPACE__);

        $wanted = [];
        foreach ($this->phpFiles($source) as $relative => $file) {
            $wanted[$relative] = str_replace(self::PLACEHOLDER, $namespace, (string)file_get_contents($file));
        }
        $current = $this->phpFiles($module);

        $problems = [];
        foreach (array_diff_key($wanted, $current) as $relative => $content) {
            $problems[] = "missing: $relative";
        }
        foreach (array_diff_key($current, $wanted) as $relative => $file) {
            $problems[] = "extra: $relative";
        }
        foreach (array_intersect_key($wanted, $current) as $relative => $content) {
            if ((string)file_get_contents($current[$relative]) !== $content) {
                $problems[] = "differs: $relative";
            }
        }

        self::assertSame(
            [],
            $problems,
            "The drop-in is behind module-standards. Sync it with:\n"
            . "  module-standards/refactors/tools/dropin-sync.py . '" . $namespace . "'\n"
            . "or, when the module has the newer code, put the change into the template first.\n"
            . implode("\n", $problems)
        );
    }

    /**
     * @return array<string, string> relative path => absolute path
     */
    private function phpFiles(string $root): array
    {
        $files = [];
        foreach (self::SUBTREES as $subtree) {
            $dir = $root . '/' . $subtree;
            if (!is_dir($dir)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                if ($file->getExtension() === 'php') {
                    $files[substr($file->getPathname(), strlen($root) + 1)] = $file->getPathname();
                }
            }
        }
        ksort($files);

        return $files;
    }
}
