<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Every XML file the module ships has to parse and, when it names a Magento schema, validate against it.
 *
 * Magento merges etc/*.xml and view/**\/*.xml at runtime; an element in the wrong place is well-formed
 * XML that still breaks setup:upgrade and every admin page of the whole installation, not only this
 * module. The E2E environment finds it an hour later; this test finds it in seconds.
 */
class XmlValidityTest extends TestCase
{
    public function testEveryShippedXmlFileParses(): void
    {
        $moduleDir = dirname(__DIR__, 3);
        $problems = [];

        foreach (['etc', 'view'] as $dir) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($moduleDir . '/' . $dir));
            foreach ($iterator as $file) {
                if (!$file->isFile() || strtolower($file->getExtension()) !== 'xml') {
                    continue;
                }
                $previous = libxml_use_internal_errors(true);
                libxml_clear_errors();
                $document = simplexml_load_file($file->getPathname());
                $errors = libxml_get_errors();
                libxml_use_internal_errors($previous);
                $relative = substr($file->getPathname(), strlen($moduleDir) + 1);
                if ($document === false || $errors) {
                    $first = $errors[0] ?? null;
                    $problems[] = sprintf(
                        '%s: %s',
                        $relative,
                        $first ? trim($first->message) . ' (line ' . $first->line . ')' : 'does not parse'
                    );
                    continue;
                }
                foreach ($this->schemaViolations($file->getPathname()) as $violation) {
                    $problems[] = $relative . ': ' . $violation;
                }
            }
        }

        self::assertSame([], $problems, "XML files that do not parse or validate:\n" . implode("\n", $problems));
    }

    /**
     * Validates against the schema the file names in xsi:noNamespaceSchemaLocation, resolved the way
     * Magento does. Files without a schema, or whose schema is not installed, are not checked here.
     *
     * @return string[]
     */
    private function schemaViolations(string $path): array
    {
        $dom = new \DOMDocument();
        $dom->load($path);
        $root = $dom->documentElement;
        $urn = $root ? $root->getAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'noNamespaceSchemaLocation') : '';
        if ($urn === '' || !class_exists(\Magento\Framework\Config\Dom::class)) {
            return [];
        }

        try {
            $schema = (new \Magento\Framework\Config\Dom\UrnResolver())->getRealPath($urn);
        } catch (\Throwable $e) {
            return [];
        }

        return \Magento\Framework\Config\Dom::validateDomDocument($dom, $schema);
    }
}
