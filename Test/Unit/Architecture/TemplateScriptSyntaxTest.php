<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * A translated string that spans two lines inside a template's inline JavaScript ends up as a
 * line break inside a JavaScript string literal. The browser rejects the whole script, and every
 * button that script wires stops working: in 1.0.1 "Check Credentials" and "Import Info" did
 * nothing because of a `__('Done,` on one line and `reloading...')` on the next.
 */
class TemplateScriptSyntaxTest extends TestCase
{
    public function testNoTranslatedStringInATemplateSpansLines(): void
    {
        $offenders = [];
        foreach ($this->templates() as $file) {
            $contents = (string)file_get_contents($file);
            if (preg_match_all("/__\\('[^']*\\n/", $contents, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as [$text, $offset]) {
                    $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                    $offenders[] = sprintf('%s:%d %s', $this->relative($file), $line, trim($text));
                }
            }
        }

        $this->assertSame([], $offenders, "Translated strings must stay on one line:\n" . implode("\n", $offenders));
    }

    /**
     * @return list<string>
     */
    private function templates(): array
    {
        $root = dirname(__DIR__, 3) . '/view';
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'phtml') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    private function relative(string $file): string
    {
        return substr($file, strlen(dirname(__DIR__, 3)) + 1);
    }
}
