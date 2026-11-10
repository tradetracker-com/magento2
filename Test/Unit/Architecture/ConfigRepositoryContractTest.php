<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Every method the module calls on a config interface has to exist on that interface.
 *
 * DebugLog\Repository::addDebugLog() called isDebugLoggingEnabled(), which the interface never
 * declared: PHP only notices on the call, so the admin debug log button and every debug log
 * line were a fatal error waiting for the first debug write.
 *
 * A module may split its configuration over several interfaces under Api/Config, each injected
 * under its own alias, so the check follows the `use ... as Alias;` lines instead of assuming one
 * repository interface.
 */
class ConfigRepositoryContractTest extends TestCase
{
    public function testEveryConfigRepositoryCallInTheModuleIsDeclared(): void
    {
        $missing = [];
        foreach ($this->phpFiles() as $file) {
            $source = (string)file_get_contents($file);
            preg_match_all(
                '/^use (TradeTracker\\\\Connect\\\\Api\\\\Config\\\\[\w\\\\]+Interface) as (\w+);/m',
                $source,
                $aliases,
                PREG_SET_ORDER
            );
            foreach ($aliases as [, $interface, $alias]) {
                if (!interface_exists($interface)) {
                    $missing[] = basename($file) . ' imports ' . $interface . ', which does not exist';
                    continue;
                }
                $declared = array_map(
                    static fn (\ReflectionMethod $method) => $method->getName(),
                    (new \ReflectionClass($interface))->getMethods()
                );
                if (!preg_match('/(?:private|protected|public)(?: readonly)? ' . $alias . ' \$(\w+)/', $source, $property)) {
                    continue;
                }
                preg_match_all('/\$this->' . $property[1] . '->(\w+)\(/', $source, $calls);
                foreach (array_unique($calls[1]) as $method) {
                    if (!in_array($method, $declared, true)) {
                        $missing[] = basename($file) . ' calls ' . $method . '() on ' . $alias;
                    }
                }
            }
        }

        self::assertSame([], $missing);
    }

    public function testEveryXmlPathConstantMatchesASystemXmlField(): void
    {
        // A section is declared in system.xml and its groups usually live in include files that
        // name no section of their own, so the mapping has to come from the <include> elements.
        // A module may ship several sections; assuming one silently skips the fields of the others.
        $root = dirname(__DIR__, 3);
        $sections = [];
        $groupsBySection = [];
        $main = @simplexml_load_file($root . '/etc/adminhtml/system.xml');
        self::assertNotFalse($main, 'etc/adminhtml/system.xml does not parse');

        foreach ($main->xpath('//section') as $section) {
            $id = (string)$section['id'];
            $sections[$id] = true;
            $groupsBySection[$id][] = $section;
            foreach ($section->xpath('./include') as $include) {
                $file = $root . '/etc/adminhtml/' . preg_replace('#^[^:]+::#', '', (string)$include['path']);
                $included = is_file($file) ? @simplexml_load_file($file) : false;
                if ($included !== false) {
                    $groupsBySection[$id][] = $included;
                }
            }
        }
        self::assertNotSame([], $sections, 'no configuration section found');

        $fields = [];
        foreach ($groupsBySection as $sectionId => $documents) {
            foreach ($documents as $document) {
                foreach ($document->xpath('.//group') as $group) {
                    foreach ($group->field as $field) {
                        // <config_path> wins when it is there: that is where Magento actually
                        // stores the value, and therefore the path the constant has to match.
                        $fields[] = isset($field->config_path)
                            ? (string)$field->config_path
                            : $sectionId . '/' . $group['id'] . '/' . $field['id'];
                    }
                }
            }
        }

        // Select on the value, not on the name: modules spell these constants XML_PATH_FOO in one
        // place and FOO in another, and a convention that only holds in half the repositories
        // silently turns this test into a no-op.
        // The paths are not always on one interface: a module with several sections splits them
        // over Api/Config/System/*Interface.php, and looking at the root one alone finds nothing.
        $constants = [];
        foreach (array_merge(glob($root . '/Api/Config/*.php') ?: [], glob($root . '/Api/Config/*/*.php') ?: []) as $file) {
            $class = 'TradeTracker\\Connect\\Api\\Config\\'
                . str_replace('/', '\\', substr($file, strlen($root . '/Api/Config/'), -4));
            if (interface_exists($class) || class_exists($class)) {
                $constants += (new \ReflectionClass($class))->getConstants();
            }
        }

        // A path may be backed by a default in config.xml instead of an admin field. That is the
        // right shape for a value a merchant must not change, such as the fixed upload host of the
        // channel itself, so it counts as declared.
        foreach ($this->configXmlPaths($root) as $path) {
            $fields[] = $path;
        }

        $checked = 0;
        $orphans = [];
        foreach ($constants as $name => $value) {
            // Only our own sections. A repository legitimately reads Magento's paths too
            // (general/store_information/country_id), and those live in Magento's system.xml.
            if (!is_string($value) || !preg_match('#^([a-z0-9_]+)/[a-z0-9_]+/[a-z0-9_]+$#', $value, $parts)) {
                continue;
            }
            // A constant belongs to this module when its first segment is one of our sections, or
            // shares a prefix with one: this module declares tradetracker_feed in system.xml and
            // stores under tradetracker via <config_path>.
            $ours = false;
            foreach (array_keys($sections) as $sectionId) {
                if ($sectionId === $parts[1]
                    || str_starts_with($sectionId, $parts[1] . '_')
                    || str_starts_with($parts[1], $sectionId . '_')
                ) {
                    $ours = true;
                    break;
                }
            }
            if (!$ours) {
                continue;
            }
            $checked++;
            // Collect rather than assert per constant: stopping at the first orphan hides the rest.
            if (!in_array($value, $fields, true)) {
                $orphans[] = $name . ' => ' . $value;
            }
        }
        self::assertGreaterThan(0, $checked, 'no configuration path constants found on the interface');
        self::assertSame([], $orphans, 'constants with no field in system.xml');
    }

    /**
     * Every section/group/field path that etc/config.xml gives a default.
     *
     * @param string $root
     *
     * @return string[]
     */
    private function configXmlPaths(string $root): array
    {
        $file = $root . '/etc/config.xml';
        if (!is_file($file)) {
            return [];
        }
        $xml = @simplexml_load_file($file);
        if ($xml === false) {
            return [];
        }

        $paths = [];
        foreach ($xml->xpath('/config/default/*') ?: [] as $section) {
            foreach ($section->children() as $group) {
                foreach ($group->children() as $field) {
                    $paths[] = $section->getName() . '/' . $group->getName() . '/' . $field->getName();
                }
            }
        }

        return $paths;
    }

    /**
     * @return string[]
     */
    private function phpFiles(): array
    {
        $moduleDir = dirname(__DIR__, 3);
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($moduleDir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $relative = substr($file->getPathname(), strlen($moduleDir) + 1);
            if ($file->getExtension() === 'php' && !str_starts_with($relative, 'Test/') && !str_starts_with($relative, 'vendor/')) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
