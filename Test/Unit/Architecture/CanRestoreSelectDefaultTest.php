<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * A select with "Use system value" shows its first option when nothing is stored, and stores
 * nothing while the checkbox is ticked. When that first option means something (Yes, Static
 * Values, Include) the admin shows one setting and the module reads another: a merchant picks
 * "Static Values" for the delivery time, leaves the checkbox as it is, and gets no delivery time
 * at all. Every such select needs a default in config.xml, so that what is shown is what is read.
 *
 * Source models whose first option is empty or 0 are fine: nothing stored reads the same.
 */
class CanRestoreSelectDefaultTest extends TestCase
{
    public function testEverySelectWithAMeaningfulFirstOptionHasADefault(): void
    {
        $root = dirname(__DIR__, 3);
        $defaults = $this->configXmlPaths($root . '/etc/config.xml');
        $missing = [];

        foreach (glob($root . '/etc/adminhtml/system/*.xml') ?: [] as $file) {
            $xml = simplexml_load_file($file);
            self::assertNotFalse($xml, "$file does not parse");
            foreach ($xml->xpath('//field[@canRestore="1"][@type="select"]') ?: [] as $field) {
                $path = (string)$field->config_path;
                $first = $this->firstOption($root, (string)$field->source_model);
                if ($path === '' || $first === null || $first === '' || $first === '0') {
                    continue;
                }
                if (!array_key_exists($path, $defaults)) {
                    $missing[] = sprintf('%s shows "%s" first but config.xml has no default for %s', $field['id'], $first, $path);
                }
            }
        }

        self::assertSame([], $missing, implode("\n", $missing));
    }

    /**
     * The first option's value when the source model spells it out as a literal, null when it is
     * built at runtime (attribute lists) and cannot be read from the file.
     */
    private function firstOption(string $root, string $sourceModel): ?string
    {
        if ($sourceModel === 'Magento\\Config\\Model\\Config\\Source\\Yesno') {
            return '1';
        }
        $prefix = 'TradeTracker\\Connect\\';
        if (!str_starts_with($sourceModel, $prefix)) {
            return null;
        }
        $file = $root . '/' . str_replace('\\', '/', substr($sourceModel, strlen($prefix))) . '.php';
        if (!is_file($file)) {
            return null;
        }
        if (!preg_match("/'value'\s*=>\s*('([^']*)'|null|(\d+))/", (string)file_get_contents($file), $m)) {
            return null;
        }
        if ($m[1] === 'null') {
            return '';
        }
        return $m[3] ?? $m[2];
    }

    /**
     * @return array<string, string>
     */
    private function configXmlPaths(string $file): array
    {
        $paths = [];
        $xml = simplexml_load_file($file);
        self::assertNotFalse($xml, "$file does not parse");
        foreach ($xml->default->children() as $section) {
            foreach ($section->children() as $group) {
                foreach ($group->children() as $field) {
                    $paths[sprintf('%s/%s/%s', $section->getName(), $group->getName(), $field->getName())] = (string)$field;
                }
            }
        }
        return $paths;
    }
}
