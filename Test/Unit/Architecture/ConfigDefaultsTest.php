<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Every default in etc/config.xml has to land on a path the admin actually declares a field for.
 *
 * A default under a slightly different key is silently dead: the admin shows the field empty, the
 * code reads nothing, and the module behaves as if the setting were off. A default under
 * `feed/enable` while the field and the constant say `feed/enabled` is never noticed.
 *
 * Paths the module reads without offering a field for them are listed in ALLOWED: internal
 * bookkeeping, options that are only set programmatically, and Magento's own crontab node.
 */
class ConfigDefaultsTest extends TestCase
{
    /**
     * Config paths that are defaults on purpose and have no admin field.
     */
    private const ALLOWED = [
        'crontab',
    ];

    /**
     * Full paths the module writes itself and reads back without an admin field (a generation
     * result, a stored token). Name them here, one per line, so the gate keeps checking the rest.
     */
    private const ALLOWED_PATHS = [
        // Written by the module after each feed run and shown in the Feeds overview; no admin field.
        'tradetracker/feeds/results',
    ];

    public function testEveryDefaultHasADeclaredField(): void
    {
        $moduleDir = dirname(__DIR__, 3);
        $configFile = $moduleDir . '/etc/config.xml';
        if (!is_file($configFile)) {
            self::markTestSkipped('The module ships no etc/config.xml.');
        }

        $declared = $this->declaredFieldPaths($moduleDir);
        $orphans = [];

        $config = simplexml_load_file($configFile);
        foreach ($config->default->children() as $section) {
            if (in_array($section->getName(), self::ALLOWED, true)) {
                continue;
            }
            foreach ($section->children() as $group) {
                foreach ($group->children() as $field) {
                    $path = sprintf('%s/%s/%s', $section->getName(), $group->getName(), $field->getName());
                    if (in_array($path, self::ALLOWED_PATHS, true)) {
                        continue;
                    }
                    if (isset($declared['paths'][$path]) || isset($declared['groupField'][$group->getName() . '/' . $field->getName()])) {
                        continue;
                    }
                    $orphans[] = $path;
                }
            }
        }

        self::assertSame([], $orphans, "etc/config.xml defaults with no admin field:\n" . implode("\n", $orphans));
    }

    /**
     * Field paths the admin declares, both as <config_path> and as the group/field pair Magento
     * builds a path from when there is no <config_path>.
     *
     * @return array{paths: array<string, true>, groupField: array<string, true>}
     */
    private function declaredFieldPaths(string $moduleDir): array
    {
        $paths = [];
        $groupField = [];

        // system.xml plus every file it includes: the <include path> elements decide where a
        // module keeps its groups, not a fixed directory name.
        $files = glob($moduleDir . '/etc/adminhtml/system.xml') ?: [];
        foreach ($files as $main) {
            $dom = new \DOMDocument();
            $dom->load($main);
            foreach ($dom->getElementsByTagName('include') as $include) {
                /** @var \DOMElement $include */
                $included = $moduleDir . '/etc/adminhtml/'
                    . (string)preg_replace('#^[^:]+::#', '', $include->getAttribute('path'));
                if (is_file($included)) {
                    $files[] = $included;
                }
            }
        }
        foreach ($files as $file) {
            $source = (string)file_get_contents($file);
            preg_match_all('#<config_path>([^<]+)</config_path>#', $source, $matches);
            foreach ($matches[1] as $path) {
                $paths[trim($path)] = true;
            }
            preg_match_all('#<group id="([^"]+)"(.*?)</group>#s', $source, $groups, PREG_SET_ORDER);
            foreach ($groups as $group) {
                preg_match_all('#<field id="([^"]+)"#', $group[2], $fields);
                foreach ($fields[1] as $field) {
                    $groupField[$group[1] . '/' . $field] = true;
                }
            }
        }

        return ['paths' => $paths, 'groupField' => $groupField];
    }
}
